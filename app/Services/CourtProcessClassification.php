<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the explicitly assigned Process competency under the same settings lock
 * the caller must hold until persisting the Process.
 */
class CourtProcessClassification
{
    public function __construct(
        private ProcessModuleSettings $settings,
        private ProcessClassCompetencyMap $classMap,
    ) {}

    /**
     * When class is absent, require an explicit active competency from the module configuration.
     * When class exists, use its active mappings: auto-resolve one; require explicit choice for many.
     * Call only within a DB transaction and keep that transaction open until the Process is saved.
     *
     * @throws ValidationException
     */
    public function resolveCompetencyInCurrentTransaction(?int $classCode, ?int $requestedCompetency): int
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Process classification must be resolved inside the persistence transaction.');
        }

        try {
            $lock = DB::table('process_module_setting_locks')->where('id', 1)->lockForUpdate()->first();
        } catch (QueryException) {
            $lock = null;
        }

        if ($lock === null) {
            throw ValidationException::withMessages(['competency_code' => 'A configuração de competência não está disponível.']);
        }

        if ($requestedCompetency === 999 || ($requestedCompetency !== null && ($requestedCompetency < 1 || $requestedCompetency > 4294967295))) {
            throw ValidationException::withMessages(['competency_code' => 'Competência inválida.']);
        }

        if ($classCode === null) {
            $configured = $this->settings->current();
            $allowed = $configured['competencies'] ?? [];

            if ($requestedCompetency === null || ! in_array($requestedCompetency, $allowed, true)) {
                throw ValidationException::withMessages(['competency_code' => 'Escolha uma competência ativa da configuração da Vara.']);
            }

            return $requestedCompetency;
        }

        if ($classCode < 1 || $classCode > 4294967295) {
            throw ValidationException::withMessages(['class_code' => 'Classe inválida.']);
        }

        $eligible = array_values(array_filter(
            $this->classMap->selectableCompetencies($classCode),
            static fn (int $code): bool => $code > 0 && $code <= 4294967295 && $code !== 999,
        ));
        if ($eligible === []) {
            throw ValidationException::withMessages(['competency_code' => 'A classe não possui competência ativa disponível.']);
        }

        if ($requestedCompetency !== null) {
            if (! in_array($requestedCompetency, $eligible, true)) {
                throw ValidationException::withMessages(['competency_code' => 'A competência não está ativa para a classe selecionada.']);
            }

            return $requestedCompetency;
        }

        if (count($eligible) === 1) {
            return $eligible[0];
        }

        throw ValidationException::withMessages(['competency_code' => 'Escolha uma competência entre as associações ativas da classe.']);
    }
}
