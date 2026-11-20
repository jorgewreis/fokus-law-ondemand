<?php

namespace App\Services;

use App\Models\AccountAuditEvent;
use App\Models\User;

class AccountAudit
{
    public function record(
        ?User $actor,
        string $event,
        ?string $subjectType,
        int|string|null $subjectId,
        string $outcome = 'success',
        ?string $module = null,
        ?string $action = null,
    ): void {
        AccountAuditEvent::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId === null ? null : (string) $subjectId,
            'module' => $module,
            'action' => $action,
            'outcome' => $outcome,
            'created_at' => now(),
        ]);
    }
}
