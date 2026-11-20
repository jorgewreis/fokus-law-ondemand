<?php

namespace App\Services;

use App\Models\User;

/** No persisted Unit grant verifier exists yet: fail closed. */
class ProcessUnitAccess
{
    public function allows(User $user, int|string $unitId): bool
    {
        return false;
    }
}
