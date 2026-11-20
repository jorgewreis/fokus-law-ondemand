<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Security\TotpService;
use App\Services\AccountAudit;
use App\Services\RecoveryCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class MfaEnrollmentController extends Controller
{
    public function create(Request $request, TotpService $totp): Response
    {
        /** @var User $user */
        $user = $request->attributes->get('pending_mfa_user');
        $encryptedSecret = $request->session()->get('mfa_setup.secret');

        if (! is_string($encryptedSecret)) {
            $encryptedSecret = Crypt::encryptString($totp->generateSecret());
            $request->session()->put('mfa_setup.secret', $encryptedSecret);
        }

        $secret = Crypt::decryptString($encryptedSecret);

        return response()->view('auth.mfa-enroll', [
            'email' => $user->email,
            'issuer' => (string) config('app.name'),
            'secret' => $secret,
            'provisioningUri' => $totp->provisioningUri((string) config('app.name'), $user->email, $secret),
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, TotpService $totp, RecoveryCodeService $recoveryCodes, AccountAudit $audit): Response|RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        /** @var User $user */
        $user = $request->attributes->get('pending_mfa_user');
        $encryptedSecret = $request->session()->get('mfa_setup.secret');

        abort_unless(is_string($encryptedSecret), 403);

        $secret = Crypt::decryptString($encryptedSecret);
        $counter = $totp->matchingCounter($secret, $validated['code'], now()->timestamp);

        if ($counter === null) {
            return back()->withErrors(['code' => 'Código inválido ou expirado.'])->header('Cache-Control', 'no-store');
        }

        $codes = DB::transaction(function () use ($user, $secret, $counter, $recoveryCodes, $audit): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            abort_unless($locked->mfa_enabled_at === null, 409);

            $locked->forceFill([
                'mfa_secret' => $secret,
                'mfa_enabled_at' => now(),
                'mfa_last_accepted_counter' => $counter,
                'mfa_reset_required' => false,
                'is_active' => true,
                'email_verified_at' => $locked->email_verified_at ?? now(),
            ])->save();

            $issued = $recoveryCodes->replaceForEnrollment($locked);
            $audit->record($locked, 'mfa.enrolled', 'user', $locked->getKey());

            return $issued;
        });

        $request->session()->forget(['mfa_setup.user_id', 'mfa_setup.expires_at', 'mfa_setup.purpose', 'mfa_setup.secret']);
        Auth::login($user->fresh());
        $request->session()->regenerate();
        $request->session()->put('auth.mfa_verified_at', now()->timestamp);

        return response()->view('auth.recovery-codes', ['recoveryCodes' => $codes])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
