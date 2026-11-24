<?php

namespace App\Services;

use App\Models\CourtProcess;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Changes only an approved operational status and records the explicit transition. */
class ProcessStatusTransition
{
    public function __construct(private CourtProcessAccess $access) {}

    public function change(User $actor, int $processId, string $newStatus, mixed $mfaVerifiedAt): bool
    {
        if (! in_array($newStatus, CourtProcess::STATUSES, true)) {
            throw new \InvalidArgumentException('Unsupported Process status.');
        }

        return DB::transaction(function () use ($actor, $processId, $newStatus, $mfaVerifiedAt): bool {
            $currentActor = User::query()->lockForUpdate()->find($actor->getKey());
            if ($currentActor === null) {
                throw new AuthorizationException;
            }
            $candidate = CourtProcess::query()->lockForUpdate()->find($processId);
            if ($candidate === null) {
                return false;
            }
            $action = in_array($newStatus, [CourtProcess::STATUS_ARCHIVED_PROVISIONALLY, CourtProcess::STATUS_ARCHIVED_DEFINITIVELY], true)
                ? 'archive'
                : 'update';
            $process = $this->access->find($currentActor, $processId, $action, $mfaVerifiedAt);
            if (! $process instanceof CourtProcess) {
                return false;
            }
            if ($process->operational_status === $newStatus) {
                return true;
            }

            $previousStatus = $process->operational_status;
            $process->operational_status = $newStatus;
            $process->save();
            DB::table('process_status_events')->insert([
                'actor_user_id' => $currentActor->getKey(),
                'court_process_id' => $process->getKey(),
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'created_at' => now(),
            ]);

            return true;
        });
    }
}
