<?php

namespace App\Services;

use App\Models\CourtProcess;
use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Jorge-only operation; it does not grant the actor access to the Process. */
class ProcessSecrecyClassification
{
    public function classify(User $actor, int $processId, int $secrecyCode): void
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();
        if ($secrecyCode < 0 || $secrecyCode > 5) {
            throw ValidationException::withMessages(['secrecy_code' => 'Classificação inválida.']);
        }

        DB::transaction(function () use ($actor, $processId, $secrecyCode): void {
            $currentActor = User::query()->lockForUpdate()->find($actor->getKey());
            if ($currentActor === null || ! $currentActor->is_synthetic_admin || ! $currentActor->is_active || $currentActor->mfa_enabled_at === null || $currentActor->mfa_reset_required) {
                throw new AuthorizationException;
            }

            $process = CourtProcess::query()->lockForUpdate()->find($processId);
            if ($process === null) {
                return;
            }
            if (! DB::table('secrecy_catalog')->where('code', $secrecyCode)->exists()) {
                throw ValidationException::withMessages(['secrecy_code' => 'Classificação indisponível.']);
            }
            $previousCode = $process->secrecy_code === null ? null : (int) $process->secrecy_code;
            if ($previousCode === $secrecyCode) {
                return;
            }

            $process->secrecy_code = $secrecyCode;
            $process->save();
            DB::table('process_secrecy_events')->insert([
                'actor_user_id' => $currentActor->getKey(),
                'court_process_id' => $process->getKey(),
                'operation' => $previousCode === null ? 'classified' : 'reclassified',
                'previous_secrecy_code' => $previousCode,
                'new_secrecy_code' => $secrecyCode,
                'created_at' => now(),
            ]);
        });
    }
}
