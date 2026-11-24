<?php

namespace App\Http\Controllers;

use App\Models\CourtProcess;
use App\Services\AccountAccess;
use App\Services\CompetencyCatalog;
use App\Services\CompetencyGrants;
use App\Services\CourtProcessAccess;
use App\Services\PersonAccessPolicy;
use App\Services\ProcessCreation;
use App\Services\ProcessModuleSettings;
use App\Services\ProcessStatusTransition;
use App\Services\ProcessUnitAccess;
use App\Services\ProcessUnitConfiguration;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourtProcessController extends Controller
{
    public function create(Request $request, AccountAccess $access, ProcessModuleSettings $settings, CompetencyGrants $grants, CompetencyCatalog $catalog, ProcessUnitConfiguration $unitConfiguration, ProcessUnitAccess $unitAccess): View
    {
        $user = $request->user();
        abort_unless($access->allows($user, 'processes', 'create'), 403);
        $configured = $settings->current();
        $unit = $unitConfiguration->snapshot()['unit'];
        $personId = $request->query('person_id');
        if (! is_string($personId) || ! ctype_digit($personId) || strlen($personId) > 18) {
            $personId = null;
        } else {
            $personId = (int) $personId;
        }
        $competencies = collect($configured['competencies'] ?? [])
            ->filter(fn (int $code): bool => $grants->hasExactGrant($user, $code))
            ->map(fn (int $code): array => ['code' => $code, 'description' => $catalog->descriptionFor($code)])
            ->filter(fn (array $item): bool => is_string($item['description']))
            ->values();

        return view('processes.create', [
            'unit' => $unit,
            'hasUnitGrant' => $unit !== null && $unitAccess->allows($user, (string) $unit->id),
            'competencies' => $competencies,
            'canLinkPeople' => app(PersonAccessPolicy::class)->canRead($user),
            'personId' => $personId,
        ]);
    }

    public function store(Request $request, ProcessCreation $creation): RedirectResponse
    {
        try {
            $data = Validator::make($request->all(), [
                'cnj' => ['required', 'string', 'max:32'],
                'class_code' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
                'competency_code' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
                'person_id' => ['nullable', 'integer', 'min:1'],
                'role' => ['required_with:person_id', 'nullable', 'in:reu,vitima,testemunha,defensor'],
                'witness_origin' => ['required_if:role,testemunha', 'nullable', 'in:denuncia,defesa,ambas'],
            ])->validate();
            $data['class_code'] = isset($data['class_code']) ? (int) $data['class_code'] : null;
            $data['competency_code'] = isset($data['competency_code']) ? (int) $data['competency_code'] : null;
            $data['person_id'] = isset($data['person_id']) ? (int) $data['person_id'] : null;
            $creation->create($request->user(), $data);
        } catch (AuthorizationException) {
            abort(404);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput([]);
        }

        return redirect()->route('processes.create')->with('status', 'Cadastro recebido. A consulta ficará disponível após a classificação do sigilo e a concessão dos grants correspondentes.');
    }

    public function show(Request $request, int $processId, CourtProcessAccess $access): View
    {
        $process = $access->find($request->user(), $processId, 'read', $request->session()->get('auth.mfa_verified_at'));
        abort_unless($process instanceof CourtProcess, 404);

        $people = collect();
        if (app(PersonAccessPolicy::class)->canRead($request->user())) {
            $people = $process->personLinks()->with('person:id,person_type,name')->get();
        }

        $verifiedAt = $request->session()->get('auth.mfa_verified_at');

        return view('processes.show', [
            'process' => $process,
            'people' => $people,
            'canUpdate' => $access->allows($request->user(), $processId, 'update', $verifiedAt),
            'canArchive' => $access->allows($request->user(), $processId, 'archive', $verifiedAt),
        ]);
    }

    public function updateStatus(Request $request, int $processId, ProcessStatusTransition $transition): RedirectResponse
    {
        $data = $request->validate(['operational_status' => ['required', 'in:ativo,suspenso,arquivado_provisoriamente,arquivado_definitivamente']]);
        abort_unless($transition->change($request->user(), $processId, $data['operational_status'], $request->session()->get('auth.mfa_verified_at')), 404);

        return redirect()->route('processes.show', $processId)->with('status', 'Situação do Processo atualizada.');
    }
}
