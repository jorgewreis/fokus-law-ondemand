<?php

namespace App\Services;

use App\Models\User;

class ProcessSecrecyAccess
{
    public function __construct(private ?SecrecyGrants $grants = null) {}

    public function allows(User $user, string $secrecyCode): bool
    {
        return ($this->grants ?? app(SecrecyGrants::class))->hasExactGrant($user, $secrecyCode);
    }
}
