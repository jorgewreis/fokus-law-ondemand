<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Checks only an explicit grant for the exact persisted Unit identifier. */
class UnitAccess
{
    public function allows(User $user, int|string $unitId): bool
    {
        if (! is_string($unitId) || ! Str::isUuid($unitId)) {
            return false;
        }

        return DB::table('account_unit_grants')
            ->join('users', 'users.id', '=', 'account_unit_grants.user_id')
            ->join('units', 'units.id', '=', 'account_unit_grants.unit_id')
            ->where('users.id', $user->getKey())
            ->where('users.is_active', true)
            ->whereNotNull('users.mfa_enabled_at')
            ->where('users.mfa_reset_required', false)
            ->where('units.id', $unitId)
            ->exists();
    }
}
