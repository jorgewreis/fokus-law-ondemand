<?php

namespace App\Services;

use App\AccountRequestStatus;
use App\Models\AccountRequest;

class AccountRequestLifecycle
{
    public function expirePending(?string $email = null): int
    {
        $now = now();

        $requests = AccountRequest::query()
            ->whereIn('status', [
                AccountRequestStatus::PendingEmailConfirmation->value,
                AccountRequestStatus::PendingDeveloperApproval->value,
            ])
            ->where('expires_at', '<=', $now);

        if ($email !== null) {
            $requests->where('email', $email);
        }

        return $requests->update([
            'display_name' => null,
            'email' => null,
            'status' => AccountRequestStatus::Expired->value,
            'updated_at' => $now,
        ]);
    }
}
