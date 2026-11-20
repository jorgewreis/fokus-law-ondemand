<?php

namespace App\Services;

use App\Models\User;

/** No persisted secrecy grant verifier exists yet: fail closed. */
class ProcessSecrecyAccess
{
    public function allows(User $user, string $secrecyCode): bool
    {
        return false;
    }
}
