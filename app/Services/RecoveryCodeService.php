<?php

namespace App\Services;

use App\Models\MfaRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RecoveryCodeService
{
    /** @return list<string> Plaintext codes are returned once and never persisted. */
    public function replaceForEnrollment(User $user): array
    {
        $codes = [];

        DB::transaction(function () use ($user, &$codes): void {
            MfaRecoveryCode::query()->where('user_id', $user->getKey())->delete();

            for ($index = 0; $index < 10; $index++) {
                $code = strtoupper(bin2hex(random_bytes(6)));
                $codes[] = $code;

                MfaRecoveryCode::query()->create([
                    'user_id' => $user->getKey(),
                    'code_hash' => Hash::make($code),
                ]);
            }
        });

        return $codes;
    }

    public function consume(User $user, string $candidate): bool
    {
        $candidate = strtoupper(trim($candidate));

        if (preg_match('/\A[A-F0-9]{12}\z/', $candidate) !== 1) {
            return false;
        }

        return DB::transaction(function () use ($user, $candidate): bool {
            $codes = MfaRecoveryCode::query()
                ->where('user_id', $user->getKey())
                ->whereNull('used_at')
                ->lockForUpdate()
                ->get();

            foreach ($codes as $code) {
                if (Hash::check($candidate, $code->code_hash)) {
                    $code->forceFill(['used_at' => now()])->save();

                    return true;
                }
            }

            return false;
        });
    }
}
