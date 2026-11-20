<?php

namespace App\Services;

/**
 * Fail closed until official class eligibility and provenance are verified.
 * Never configure this decision from request input or application settings.
 */
class VerifiedSgtClassCatalog
{
    public function isSelectable(int $code, string $justiceBranch, string $courtLevel): bool
    {
        return false;
    }
}
