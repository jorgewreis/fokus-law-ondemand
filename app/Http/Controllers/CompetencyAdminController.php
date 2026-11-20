<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use App\Services\CompetencyCatalog;
use App\Services\CompetencyGrants;
use App\Services\ProcessModuleSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class CompetencyAdminController extends Controller
{
    public function index(SyntheticDatabaseGuard $guard, ProcessModuleSettings $settings, CompetencyCatalog $catalog): View
    {
        $guard->assertAllowed();

        $rows = DB::table('competencies')->orderBy('description')->get();
        $catalogComplete = $this->catalogComplete($catalog, $rows);
        $competencies = $catalogComplete ? $rows->where('code', '!=', 999)->values() : collect();

        $snapshot = $settings->snapshot();

        return view('admin.competencies.index', [
            'competencies' => $competencies,
            'catalogComplete' => $catalogComplete,
            'catalogEmpty' => $rows->isEmpty(),
            'settings' => $snapshot['settings'],
            'revision' => $snapshot['revision'],
            'users' => User::query()->where('is_synthetic_admin', false)->orderBy('email')->get(),
            'grants' => DB::table('account_competency_grants')->join('competencies', 'competencies.code', '=', 'account_competency_grants.competency_code')->orderBy('competencies.description')->get(['account_competency_grants.user_id', 'competencies.code', 'competencies.description'])->groupBy('user_id'),
        ]);
    }

    public function import(CompetencyCatalog $catalog): RedirectResponse
    {
        try {
            $catalog->importSnapshot();
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['catalog' => 'Não foi possível carregar o catálogo. Confira a referência e os dados já armazenados.']);
        }

        return back()->with('status', 'Catálogo de referência carregado.');
    }

    public function settings(Request $request, ProcessModuleSettings $settings, CompetencyCatalog $catalog, SyntheticDatabaseGuard $guard): RedirectResponse
    {
        $guard->assertAllowed();
        $this->requireCompleteCatalog($catalog);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0'], 'competencies' => ['required', 'array', 'min:1', 'max:39'], 'competencies.*' => ['required', 'integer', 'distinct', 'min:1']]);
        $settings->configure($request->user(), 'justica_estadual', 'primeiro_grau', array_map('intval', $data['competencies']), (int) $data['revision']);

        return back()->with('status', 'Competências da Vara atualizadas.');
    }

    public function grant(Request $request, User $user, CompetencyGrants $grants, CompetencyCatalog $catalog, SyntheticDatabaseGuard $guard): RedirectResponse
    {
        $guard->assertAllowed();
        $data = $request->validate(['competency' => ['required', 'integer', 'min:1'], 'operation' => ['required', 'in:grant,revoke']]);
        if ($data['operation'] === 'grant') {
            $this->requireCompleteCatalog($catalog);
        }
        $grants->set($request->user(), $user, (int) $data['competency'], $data['operation'] === 'grant');

        return back()->with('status', $data['operation'] === 'grant' ? 'Competência concedida à conta.' : 'Concessão de competência revogada.');
    }

    private function requireCompleteCatalog(CompetencyCatalog $catalog): void
    {
        if (! $this->catalogComplete($catalog, DB::table('competencies')->get())) {
            throw ValidationException::withMessages(['catalog' => 'O catálogo está incompleto ou inválido. Carregue a referência antes de continuar.']);
        }
    }

    private function catalogComplete(CompetencyCatalog $catalog, Collection $rows): bool
    {
        if ($rows->count() !== 39) {
            return false;
        }
        try {
            return $rows->every(fn ($item): bool => $catalog->descriptionFor((int) $item->code) === $item->description);
        } catch (RuntimeException) {
            return false;
        }
    }
}
