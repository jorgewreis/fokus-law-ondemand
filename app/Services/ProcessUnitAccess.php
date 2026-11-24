<?php

namespace App\Services;

use App\Models\User;

class ProcessUnitAccess
{
    public function __construct(private ?UnitAccess $access = null) {}

    public function allows(User $user, int|string $unitId): bool
    {
        return ($this->access ?? app(UnitAccess::class))->allows($user, $unitId);
    }
}
