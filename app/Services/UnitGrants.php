<?php

namespace App\Services;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UnitGrants
{
    public function set(User $actor, User $subject, string $unitId, bool $grant): void
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();
        if (! Str::isUuid($unitId)) {
            throw ValidationException::withMessages(['unit' => 'Unidade inválida.']);
        }

        DB::transaction(function () use ($actor, $subject, $unitId, $grant): void {
            $ids = array_values(array_unique([$actor->getKey(), $subject->getKey()]));
            sort($ids);
            $users = User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $currentActor = $users->get($actor->getKey());
            $currentSubject = $users->get($subject->getKey());
            if (! $currentActor || ! $currentActor->is_synthetic_admin || ! $currentActor->is_active || ! $currentActor->mfa_enabled_at || ! $currentSubject || $currentSubject->is_synthetic_admin) {
                throw new AuthorizationException;
            }
            if (! DB::table('units')->where('id', $unitId)->exists()) {
                throw ValidationException::withMessages(['unit' => 'Unidade não cadastrada.']);
            }
            $query = DB::table('account_unit_grants')->where('user_id', $currentSubject->getKey())->where('unit_id', $unitId);
            $exists = $query->exists();
            if ($grant === $exists) {
                return;
            }
            if ($grant) {
                $query->insert(['user_id' => $currentSubject->getKey(), 'unit_id' => $unitId, 'granted_by_user_id' => $currentActor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $query->delete();
            }
            DB::table('unit_grant_events')->insert(['actor_user_id' => $currentActor->getKey(), 'subject_user_id' => $currentSubject->getKey(), 'unit_id' => $unitId, 'event' => $grant ? 'granted' : 'revoked', 'created_at' => now()]);
        });
    }
}
