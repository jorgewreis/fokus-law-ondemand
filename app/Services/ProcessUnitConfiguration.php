<?php

namespace App\Services;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Jorge-only singleton Unit configuration. Switching never rewrites old Unit IDs. */
class ProcessUnitConfiguration
{
    public function set(User $actor, string $name, int $expectedRevision): void
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages(['unit' => 'Informe um nome de Unidade válido.']);
        }

        DB::transaction(function () use ($actor, $name, $expectedRevision): void {
            $currentActor = User::query()->lockForUpdate()->find($actor->getKey());
            if (! $currentActor || ! $currentActor->is_synthetic_admin || ! $currentActor->is_active || ! $currentActor->mfa_enabled_at) {
                throw new AuthorizationException;
            }
            $setting = DB::table('process_module_unit_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ((int) $setting->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['revision' => 'A Unidade foi alterada. Recarregue a página antes de salvar.']);
            }
            $unit = DB::table('units')->where('name', $name)->first();
            if ($unit === null) {
                $unitId = (string) Str::uuid();
                DB::table('units')->insert(['id' => $unitId, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $unitId = (string) $unit->id;
            }
            if ($setting->unit_id === $unitId) {
                return;
            }
            DB::table('process_module_unit_settings')->where('id', 1)->update(['unit_id' => $unitId, 'revision' => $expectedRevision + 1, 'updated_at' => now()]);
            DB::table('process_module_unit_events')->insert(['actor_user_id' => $currentActor->getKey(), 'before_unit_id' => $setting->unit_id, 'after_unit_id' => $unitId, 'created_at' => now()]);
        });
    }

    /** @return array{unit: object|null, revision: int, units: Collection} */
    public function snapshot(): array
    {
        $setting = DB::table('process_module_unit_settings')->where('id', 1)->first();
        $unit = $setting?->unit_id === null ? null : DB::table('units')->where('id', $setting->unit_id)->first();

        return ['unit' => $unit, 'revision' => (int) ($setting->revision ?? 0), 'units' => DB::table('units')->orderBy('name')->get()];
    }
}
