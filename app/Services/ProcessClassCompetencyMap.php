<?php

namespace App\Services;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Internal service only: callers must also enforce authenticated session MFA. */
class ProcessClassCompetencyMap
{
    public function __construct(private VerifiedSgtClassCatalog $classes) {}

    public function set(User $actor, int $classCode, int $competencyCode, bool $active, int $expectedRevision): void
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();

        if ($classCode < 1 || $classCode > 4294967295 || $competencyCode < 1 || $competencyCode > 4294967295) {
            throw ValidationException::withMessages(['mapping' => 'Associação inválida.']);
        }

        DB::transaction(function () use ($actor, $classCode, $competencyCode, $active, $expectedRevision): void {
            $currentActor = User::query()->lockForUpdate()->find($actor->getKey());
            if (! $currentActor || ! $currentActor->is_synthetic_admin || ! $currentActor->is_active || ! $currentActor->mfa_enabled_at) {
                throw new AuthorizationException;
            }
            $lock = DB::table('process_module_setting_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ($expectedRevision !== (int) $lock->revision) {
                throw ValidationException::withMessages(['revision' => 'A configuração foi alterada. Recarregue a página antes de salvar.']);
            }
            $query = DB::table('process_class_competencies')->where('class_code', $classCode)->where('competency_code', $competencyCode);
            $existing = $query->first();
            if ((bool) ($existing->active ?? false) === $active) {
                return;
            }
            if ($active) {
                $settings = app(ProcessModuleSettings::class)->current();
                if ($settings === null || ! in_array($competencyCode, $settings['competencies'], true) || ! $this->classes->isSelectable($classCode, $settings['justice_branch'], $settings['court_level'])) {
                    throw ValidationException::withMessages(['mapping' => 'Classe ou competência indisponível para associação.']);
                }
                if ($existing === null) {
                    DB::table('process_class_competencies')->insert(['class_code' => $classCode, 'competency_code' => $competencyCode, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
                } else {
                    $query->update(['active' => true, 'updated_at' => now()]);
                }
            } else {
                $query->update(['active' => false, 'updated_at' => now()]);
            }
            DB::table('process_module_setting_locks')->where('id', 1)->update(['revision' => $expectedRevision + 1]);
            DB::table('process_class_competency_events')->insert(['actor_user_id' => $currentActor->getKey(), 'class_code' => $classCode, 'competency_code' => $competencyCode, 'event' => $active ? 'activated' : 'deactivated', 'created_at' => now()]);
        });
    }

    /**
     * Eligible mappings only; this does not authorize access to any Process.
     * A future Process write must validate and persist under one transaction
     * holding the same configuration lock; this method releases its lock on return.
     * Retirement deactivates mappings; reactivation requires an explicit
     * administrator action and a currently verified SGT class.
     *
     * @return list<int>
     */
    public function selectableCompetencies(int $classCode): array
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();
        if ($classCode < 1 || $classCode > 4294967295) {
            return [];
        }

        return DB::transaction(function () use ($classCode): array {
            DB::table('process_module_setting_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            $settings = app(ProcessModuleSettings::class)->current();
            if ($settings === null || ! $this->classes->isSelectable($classCode, $settings['justice_branch'], $settings['court_level'])) {
                return [];
            }

            return DB::table('process_class_competencies')
                ->where('class_code', $classCode)
                ->where('active', true)
                ->whereIn('competency_code', $settings['competencies'])
                ->orderBy('competency_code')
                ->pluck('competency_code')
                ->map(fn ($code): int => (int) $code)
                ->all();
        });
    }
}
