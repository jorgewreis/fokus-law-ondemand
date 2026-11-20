<?php

namespace App\Services;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Internal synthetic-environment service; caller must also enforce session MFA. */
class CompetencyGrants
{
    public function set(User $actor, User $subject, int $code, bool $grant): void
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();

        DB::transaction(function () use ($actor, $subject, $code, $grant): void {
            // Consistent lock ordering prevents concurrent requests from racing on the target.
            $ids = array_values(array_unique([$actor->getKey(), $subject->getKey()]));
            sort($ids);
            $users = User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $currentActor = $users->get($actor->getKey());
            $currentSubject = $users->get($subject->getKey());
            if (! $currentActor || ! $currentActor->is_synthetic_admin || ! $currentActor->is_active || ! $currentActor->mfa_enabled_at || ! $currentSubject || $currentSubject->is_synthetic_admin) {
                throw new AuthorizationException;
            }
            if ($grant) {
                $description = app(CompetencyCatalog::class)->descriptionFor($code);
                $storedDescription = DB::table('competencies')->where('code', $code)->value('description');
                if ($code === 999 || $description === null || $storedDescription !== $description) {
                    throw ValidationException::withMessages(['competency' => 'Competência indisponível para concessão.']);
                }
            }
            $query = DB::table('account_competency_grants')->where('user_id', $currentSubject->getKey())->where('competency_code', $code);
            $exists = $query->exists();
            if ($grant === $exists) {
                return;
            }
            if ($grant) {
                DB::table('account_competency_grants')->insert(['user_id' => $currentSubject->getKey(), 'competency_code' => $code, 'granted_by_user_id' => $currentActor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $query->delete();
            }
            DB::table('competency_grant_events')->insert(['actor_user_id' => $currentActor->getKey(), 'subject_user_id' => $currentSubject->getKey(), 'competency_code' => $code, 'event' => $grant ? 'granted' : 'revoked', 'created_at' => now()]);
        });
    }

    /** This checks only the competency grant, not authorization to access a Process. */
    public function hasExactGrant(User $user, ?int $code): bool
    {
        if ($code === null || $code === 999) {
            return false;
        }
        $description = app(CompetencyCatalog::class)->descriptionFor($code);
        if ($description === null) {
            return false;
        }

        $storedDescription = DB::table('account_competency_grants')
            ->join('users', 'users.id', '=', 'account_competency_grants.user_id')
            ->join('competencies', 'competencies.code', '=', 'account_competency_grants.competency_code')
            ->where('users.id', $user->getKey())
            ->where('users.is_active', true)
            ->whereNotNull('users.mfa_enabled_at')
            ->where('competencies.code', $code)
            ->value('competencies.description');

        return $storedDescription === $description;
    }
}
