<?php

namespace App\Services;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit exact-code grants; labels do not imply any ordering or inheritance. */
class SecrecyGrants
{
    public function set(User $actor, User $subject, int $code, bool $grant): void
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();

        DB::transaction(function () use ($actor, $subject, $code, $grant): void {
            $ids = array_values(array_unique([$actor->getKey(), $subject->getKey()]));
            sort($ids);
            $users = User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $currentActor = $users->get($actor->getKey());
            $currentSubject = $users->get($subject->getKey());
            if (! $currentActor || ! $currentActor->is_synthetic_admin || ! $currentActor->is_active || ! $currentActor->mfa_enabled_at || ! $currentSubject || $currentSubject->is_synthetic_admin) {
                throw new AuthorizationException;
            }
            if ($grant && ! $this->catalogIsCanonical()) {
                throw ValidationException::withMessages(['secrecy' => 'O catálogo de sigilo está incompleto ou inválido.']);
            }
            if (! DB::table('secrecy_catalog')->where('code', $code)->exists()) {
                throw ValidationException::withMessages(['secrecy' => 'Código de sigilo indisponível.']);
            }

            $query = DB::table('account_secrecy_grants')->where('user_id', $currentSubject->getKey())->where('secrecy_code', $code);
            $exists = $query->exists();
            if ($grant === $exists) {
                return;
            }
            if ($grant) {
                $query->insert(['user_id' => $currentSubject->getKey(), 'secrecy_code' => $code, 'granted_by_user_id' => $currentActor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $query->delete();
            }
            DB::table('secrecy_grant_events')->insert(['actor_user_id' => $currentActor->getKey(), 'subject_user_id' => $currentSubject->getKey(), 'secrecy_code' => $code, 'event' => $grant ? 'granted' : 'revoked', 'created_at' => now()]);
        });
    }

    public function hasExactGrant(User $user, ?string $code): bool
    {
        if ($code === null || ! preg_match('/^(0|[1-5])$/D', $code) || ! $this->catalogIsCanonical()) {
            return false;
        }

        return DB::table('account_secrecy_grants')
            ->join('users', 'users.id', '=', 'account_secrecy_grants.user_id')
            ->join('secrecy_catalog', 'secrecy_catalog.code', '=', 'account_secrecy_grants.secrecy_code')
            ->where('users.id', $user->getKey())
            ->where('users.is_active', true)
            ->whereNotNull('users.mfa_enabled_at')
            ->where('users.mfa_reset_required', false)
            ->where('secrecy_catalog.code', (int) $code)
            ->exists();
    }

    private function catalogIsCanonical(): bool
    {
        $rows = DB::table('secrecy_catalog')->orderBy('code')->get();

        return $rows->count() === 6 && $rows->values()->every(fn ($row, int $index): bool => (int) $row->code === $index && $row->label === "CNJ DataJud {$index}");
    }
}
