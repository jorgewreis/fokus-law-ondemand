<?php

namespace App\Services;

use App\Data\ProcessAccessScope;
use App\Models\User;
use Throwable;

/**
 * Internal composition for an existing Process, not an HTTP entrypoint.
 * A caller must authenticate the session, bind MFA timestamp to that session,
 * and obtain scope from the same persisted Process it intends to access.
 * Any eventual protected read/write must also prevent concurrent scope or grant
 * changes from invalidating the decision before the operation is complete.
 * Rejecting same-second MFA is conservative; it does not revoke sessions globally.
 * The persisted Unit and secrecy gates are independent; Process reads still
 * require scope from the same persisted Process and a protected call site.
 */
class ProcessAccessPolicy
{
    public function __construct(
        private AccountAccess $accountAccess,
        private CompetencyGrants $competencyGrants,
        private ProcessUnitAccess $unitAccess,
        private ProcessSecrecyAccess $secrecyAccess,
    ) {}

    public function allows(?User $user, string $action, ProcessAccessScope $scope, mixed $mfaVerifiedAt): bool
    {
        try {
            if ($user === null || ! in_array($action, ['read', 'update', 'archive'], true) || ! in_array($action, config('access.grantable.processes', []), true)) {
                return false;
            }
            if ($scope->unitId === null || (is_int($scope->unitId) && $scope->unitId < 1) || (is_string($scope->unitId) && (trim($scope->unitId) !== $scope->unitId || $scope->unitId === '')) || $scope->competencyCode === null || $scope->competencyCode < 1 || $scope->secrecyCode === null || trim($scope->secrecyCode) !== $scope->secrecyCode || $scope->secrecyCode === '') {
                return false;
            }
            if ((! is_int($mfaVerifiedAt) && (! is_string($mfaVerifiedAt) || ! ctype_digit($mfaVerifiedAt))) || ($verified = filter_var($mfaVerifiedAt, FILTER_VALIDATE_INT)) === false) {
                return false;
            }
            $now = now()->timestamp;
            if ($verified < $now - 12 * 60 * 60 || $verified > $now) {
                return false;
            }

            $currentUser = User::query()->find($user->getKey());
            if ($currentUser === null || ! $currentUser->is_active || $currentUser->mfa_enabled_at === null || $currentUser->mfa_reset_required || $verified <= $currentUser->mfa_enabled_at->timestamp) {
                return false;
            }
            if (! $this->accountAccess->allows($currentUser, 'processes', $action) || ($action !== 'read' && ! $this->accountAccess->allows($currentUser, 'processes', 'read'))) {
                return false;
            }

            return $this->competencyGrants->hasExactGrant($currentUser, $scope->competencyCode)
                && $this->unitAccess->allows($currentUser, $scope->unitId)
                && $this->secrecyAccess->allows($currentUser, $scope->secrecyCode);
        } catch (Throwable) {
            return false;
        }
    }
}
