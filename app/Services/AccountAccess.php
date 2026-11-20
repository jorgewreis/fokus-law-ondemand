<?php

namespace App\Services;

use App\Models\AccountGrant;
use App\Models\User;

class AccountAccess
{
    public function allows(User $user, string $module, string $action): bool
    {
        if (! $user->is_active || $user->mfa_enabled_at === null) {
            return false;
        }

        return AccountGrant::query()
            ->where('user_id', $user->getKey())
            ->where('module', $module)
            ->where('action', $action)
            ->exists();
    }
}
