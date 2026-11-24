<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Explicit Jorge-only Account–Person linking; this service never compares email. */
class PersonAccountLinking
{
    public function link(User $actor, int $personId, int $userId): bool
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();

        return DB::transaction(function () use ($actor, $personId, $userId): bool {
            $ids = array_values(array_unique([$actor->getKey(), $userId]));
            sort($ids);
            $users = User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $currentActor = $users->get($actor->getKey());
            $subject = $users->get($userId);
            if ($currentActor === null || ! $currentActor->is_synthetic_admin || ! $currentActor->is_active
                || $currentActor->mfa_enabled_at === null || $currentActor->mfa_reset_required
                || $subject === null || ! $subject->is_active || $subject->is_synthetic_admin) {
                throw new AuthorizationException;
            }

            $person = Person::query()->lockForUpdate()->find($personId);
            if ($person === null || $person->user_id !== null
                || Person::query()->where('user_id', $subject->getKey())->exists()) {
                return false;
            }

            $person->user_id = $subject->getKey();
            $person->save();
            DB::table('person_account_link_events')->insert([
                'actor_user_id' => $currentActor->getKey(),
                'person_id' => $person->getKey(),
                'user_id' => $subject->getKey(),
                'operation' => 'linked',
                'outcome' => 'success',
                'created_at' => now(),
            ]);

            return true;
        });
    }
}
