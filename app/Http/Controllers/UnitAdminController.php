<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use App\Services\ProcessUnitConfiguration;
use App\Services\UnitGrants;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitAdminController extends Controller
{
    public function index(SyntheticDatabaseGuard $guard, ProcessUnitConfiguration $configuration): View
    {
        $guard->assertAllowed();

        return view('admin.units.index', [
            ...$configuration->snapshot(),
            'users' => User::query()->where('is_synthetic_admin', false)->with('unitGrants.unit')->orderBy('email')->get(),
        ]);
    }

    public function configure(Request $request, ProcessUnitConfiguration $configuration, SyntheticDatabaseGuard $guard): RedirectResponse
    {
        $guard->assertAllowed();
        $data = $request->validate(['unit' => ['required', 'string', 'max:160'], 'revision' => ['required', 'integer', 'min:0']]);
        $configuration->set($request->user(), $data['unit'], (int) $data['revision']);

        return back()->with('status', 'Unidade do módulo configurada. Processos existentes mantêm sua referência histórica.');
    }

    public function grant(Request $request, User $user, UnitGrants $grants, SyntheticDatabaseGuard $guard): RedirectResponse
    {
        $guard->assertAllowed();
        $data = $request->validate(['unit' => ['required', 'uuid'], 'operation' => ['required', 'in:grant,revoke']]);
        $grants->set($request->user(), $user, $data['unit'], $data['operation'] === 'grant');

        return back()->with('status', $data['operation'] === 'grant' ? 'Unidade concedida à conta.' : 'Concessão de Unidade revogada.');
    }
}
