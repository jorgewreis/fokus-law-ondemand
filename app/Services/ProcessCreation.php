<?php

namespace App\Services;

use App\Models\CourtProcess;
use App\Models\Person;
use App\Models\PersonProcess;
use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Creates a minimal synthetic Process and optional typed Person association. */
class ProcessCreation
{
    public function __construct(
        private AccountAccess $accountAccess,
        private ProcessUnitAccess $unitAccess,
        private CompetencyGrants $competencyGrants,
        private CourtProcessClassification $classification,
        private CnjNumber $cnj,
    ) {}

    /** @param array{cnj: string, class_code?: int|null, competency_code?: int|null, person_id?: int|null, role?: string|null, witness_origin?: string|null} $input */
    public function create(User $actor, array $input): CourtProcess
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();
        if (! $this->accountAccess->allows($actor, 'processes', 'create')) {
            throw new AuthorizationException;
        }

        $normalizedCnj = $this->cnj->normalize($input['cnj']);
        $classCode = $input['class_code'] ?? null;
        $requestedCompetency = $input['competency_code'] ?? null;
        $personId = $input['person_id'] ?? null;

        try {
            return DB::transaction(function () use ($actor, $normalizedCnj, $classCode, $requestedCompetency, $personId, $input): CourtProcess {
                DB::table('process_module_setting_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
                $unitSetting = DB::table('process_module_unit_settings')->where('id', 1)->lockForUpdate()->first();
                $unitId = $unitSetting?->unit_id;
                if (! is_string($unitId) || $unitId === '') {
                    throw ValidationException::withMessages(['cnj' => 'A unidade do módulo ainda não está configurada.']);
                }

                $competency = $this->classification->resolveCompetencyInCurrentTransaction($classCode, $requestedCompetency);
                $currentActor = User::query()->lockForUpdate()->find($actor->getKey());
                if ($currentActor === null
                    || ! $currentActor->is_active
                    || ! $this->accountAccess->allows($currentActor, 'processes', 'create')
                    || ! $this->unitAccess->allows($currentActor, $unitId)
                    || ! $this->competencyGrants->hasExactGrant($currentActor, $competency)) {
                    throw new AuthorizationException;
                }

                $person = null;
                if ($personId !== null) {
                    $person = Person::query()->lockForUpdate()->find($personId);
                    if ($person === null || ! app(PersonAccessPolicy::class)->canRead($currentActor)) {
                        throw new AuthorizationException;
                    }
                }

                $process = CourtProcess::query()->create([
                    'unit_id' => $unitId,
                    'created_by_user_id' => $currentActor->getKey(),
                    'cnj_normalized' => $normalizedCnj,
                    'class_code' => $classCode,
                    'competency_code' => $competency,
                    'secrecy_code' => null,
                ]);

                if ($person !== null) {
                    PersonProcess::query()->create([
                        'person_id' => $person->getKey(),
                        'court_process_id' => $process->getKey(),
                        'created_by_user_id' => $currentActor->getKey(),
                        'role' => $input['role'],
                        'witness_origin' => $input['witness_origin'] ?? null,
                    ]);
                }

                return $process;
            });
        } catch (QueryException $exception) {
            // Do not expose whether a duplicate belongs to an inaccessible Process.
            throw ValidationException::withMessages(['cnj' => 'Não foi possível concluir o cadastro. Verifique os dados e tente novamente.']);
        }
    }
}
