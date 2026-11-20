<?php

namespace App\Services;

use App\Models\CourtProcess;
use App\Models\User;

/**
 * Resolves access scope only from persisted Process data, never from request input.
 * Callers should return not-found for null results so inaccessible records are not disclosed.
 */
class CourtProcessAccess
{
    public function __construct(private ?ProcessAccessPolicy $policy = null) {}

    public function allows(?User $user, int|string $processId, string $action, mixed $mfaVerifiedAt): bool
    {
        $process = CourtProcess::query()->find($processId);

        if ($process === null) {
            return false;
        }

        return ($this->policy ?? app(ProcessAccessPolicy::class))->allows(
            $user,
            $action,
            $process->accessScope(),
            $mfaVerifiedAt,
        );
    }

    /**
     * Return an authorized persisted row, or null for both nonexistent and inaccessible IDs.
     * Call inside the caller's transaction and lock the row before mutation.
     */
    public function find(?User $user, int|string $processId, string $action, mixed $mfaVerifiedAt): ?CourtProcess
    {
        $process = CourtProcess::query()->find($processId);

        if ($process === null || ! ($this->policy ?? app(ProcessAccessPolicy::class))->allows(
            $user,
            $action,
            $process->accessScope(),
            $mfaVerifiedAt,
        )) {
            return null;
        }

        return $process;
    }
}
