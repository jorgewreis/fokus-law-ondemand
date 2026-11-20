<?php

namespace App\Services;

use App\Models\AccountAuditEvent;
use App\Models\User;
use InvalidArgumentException;

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
        $this->assertToken($event, 96, 'event', false);
        $this->assertToken($subjectType, 48, 'subject type', true);
        $this->assertToken($module, 64, 'module', true);
        $this->assertToken($action, 64, 'action', true);
        $this->assertToken($outcome, 24, 'outcome', false);

        if ($subjectId !== null && ! preg_match('/\A(?:[0-9]{1,20}|[A-Fa-f0-9-]{36})\z/', (string) $subjectId)) {
            throw new InvalidArgumentException('Audit subject ID must be an internal numeric or UUID identifier.');
        }

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

    private function assertToken(?string $value, int $maxLength, string $field, bool $nullable): void
    {
        if ($value === null && $nullable) {
            return;
        }

        if ($value === null || strlen($value) > $maxLength || ! preg_match('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $value)) {
            throw new InvalidArgumentException("Audit {$field} must be a bounded machine-readable token.");
        }
    }
}
