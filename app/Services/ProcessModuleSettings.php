<?php

namespace App\Services;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Internal service. Callers must enforce session authentication and MFA. */
class ProcessModuleSettings
{
    /**
     * The synthetic MVP is scoped to the state court of first instance.
     * These are internal labels, not SGT identifiers. This service does not grant
     * access to Processes or authorize class selection.
     *
     * @param  list<int>  $codes
     */
    public function configure(User $actor, string $branch, string $level, array $codes, int $expectedRevision): void
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();

        if ($branch !== 'justica_estadual' || $level !== 'primeiro_grau' || $codes === [] || count($codes) > 39) {
            throw ValidationException::withMessages(['settings' => 'Configuração inválida.']);
        }
        foreach ($codes as $code) {
            if (! is_int($code) || $code <= 0 || $code === 999) {
                throw ValidationException::withMessages(['competencies' => 'Competência inválida.']);
            }
        }
        $codes = array_values(array_unique($codes));
        sort($codes, SORT_NUMERIC);

        DB::transaction(function () use ($actor, $branch, $level, $codes, $expectedRevision): void {
            $currentActor = User::query()->lockForUpdate()->find($actor->getKey());
            if (! $currentActor || ! $currentActor->is_synthetic_admin || ! $currentActor->is_active || ! $currentActor->mfa_enabled_at) {
                throw new AuthorizationException;
            }
            // Serialize the singleton even if two administrator records exist accidentally.
            $lock = DB::table('process_module_setting_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ($expectedRevision !== (int) $lock->revision) {
                throw ValidationException::withMessages(['revision' => 'A configuração foi alterada. Recarregue a página antes de salvar.']);
            }
            foreach ($codes as $code) {
                $description = app(CompetencyCatalog::class)->descriptionFor($code);
                $storedDescription = DB::table('competencies')->where('code', $code)->value('description');
                if ($description === null || $storedDescription !== $description) {
                    throw ValidationException::withMessages(['competencies' => 'Competência indisponível.']);
                }
            }
            $before = $this->current();
            $after = ['justice_branch' => $branch, 'court_level' => $level, 'competencies' => $codes];
            if ($before === $after) {
                return;
            }
            $retired = array_values(array_diff($before['competencies'] ?? [], $codes));
            if ($retired !== []) {
                $mappings = DB::table('process_class_competencies')
                    ->whereIn('competency_code', $retired)
                    ->where('active', true)
                    ->orderBy('competency_code')
                    ->orderBy('class_code')
                    ->get(['class_code', 'competency_code']);
                foreach ($mappings as $mapping) {
                    DB::table('process_class_competencies')
                        ->where('class_code', $mapping->class_code)
                        ->where('competency_code', $mapping->competency_code)
                        ->update(['active' => false, 'updated_at' => now()]);
                    DB::table('process_class_competency_events')->insert([
                        'actor_user_id' => $currentActor->getKey(),
                        'class_code' => $mapping->class_code,
                        'competency_code' => $mapping->competency_code,
                        'event' => 'deactivated',
                        'created_at' => now(),
                    ]);
                }
            }
            if ($before === null) {
                DB::table('process_module_settings')->insert(['id' => 1, 'justice_branch' => $branch, 'court_level' => $level, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table('process_module_settings')->where('id', 1)->update(['justice_branch' => $branch, 'court_level' => $level, 'updated_at' => now()]);
            }
            DB::table('process_module_competencies')->whereNotIn('competency_code', $codes)->where('active', true)->update(['active' => false, 'updated_at' => now()]);
            foreach ($codes as $code) {
                $row = DB::table('process_module_competencies')->where('competency_code', $code)->first();
                if ($row === null) {
                    DB::table('process_module_competencies')->insert(['competency_code' => $code, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
                } elseif (! $row->active) {
                    DB::table('process_module_competencies')->where('competency_code', $code)->update(['active' => true, 'updated_at' => now()]);
                }
            }
            DB::table('process_module_setting_locks')->where('id', 1)->update(['revision' => $expectedRevision + 1]);
            DB::table('process_module_setting_events')->insert([
                'actor_user_id' => $currentActor->getKey(),
                'before_state' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                'after_state' => json_encode($after, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });
    }

    /** @return array{justice_branch: string, court_level: string, competencies: list<int>}|null */
    public function current(): ?array
    {
        return $this->snapshot()['settings'];
    }

    /** @return array{revision: int, settings: array{justice_branch: string, court_level: string, competencies: list<int>}|null} */
    public function snapshot(): array
    {
        // One statement gives readers a coherent snapshot even during a configuration change.
        $rows = DB::table('process_module_setting_locks')
            ->leftJoin('process_module_settings', 'process_module_settings.id', '=', 'process_module_setting_locks.id')
            ->leftJoin('process_module_competencies', fn ($join) => $join->where('process_module_competencies.active', true)->whereNotNull('process_module_settings.id'))
            ->where('process_module_setting_locks.id', 1)
            ->orderBy('process_module_competencies.competency_code')
            ->get(['process_module_setting_locks.revision', 'process_module_settings.justice_branch', 'process_module_settings.court_level', 'process_module_competencies.competency_code']);
        if ($rows->isEmpty()) {
            throw new \RuntimeException('Process module setting lock missing.');
        }

        return [
            'revision' => (int) $rows[0]->revision,
            'settings' => $rows[0]->justice_branch === null ? null : [
                'justice_branch' => $rows[0]->justice_branch,
                'court_level' => $rows[0]->court_level,
                'competencies' => $rows->pluck('competency_code')->filter(fn ($code): bool => $code !== null)->map(fn ($code): int => (int) $code)->values()->all(),
            ],
        ];
    }
}
