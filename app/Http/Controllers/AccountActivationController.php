<?php

namespace App\Http\Controllers;

use App\Models\AccountActivationInvite;
use App\Models\User;
use App\Services\AccountAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class AccountActivationController extends Controller
{
    public function show(Request $request): Response
    {
        $token = $request->query('token');

        abort_unless(
            is_string($token)
                && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1
                && URL::hasCorrectSignature($request)
                && $this->findInvite($token) !== null,
            410,
        );

        return response()->view('account-activation.show', ['token' => $token])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, AccountAudit $audit): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
            'password' => ['required', 'confirmed', 'string', 'min:12', 'max:128'],
        ]);

        $user = DB::transaction(function () use ($validated, $audit): User {
            $invite = AccountActivationInvite::query()
                ->where('token_hash', hash('sha256', $validated['token']))
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            abort_unless($invite !== null, 410);

            $account = User::query()->lockForUpdate()->findOrFail($invite->user_id);
            abort_unless(! $account->is_active && ! $account->is_synthetic_admin, 410);

            $invite->forceFill(['consumed_at' => now()])->save();
            $account->forceFill([
                'password' => $validated['password'],
                'email_verified_at' => $account->email_verified_at ?? now(),
            ])->save();
            $audit->record(null, 'account_activation.started', 'user', $account->getKey());

            return $account;
        });

        $request->session()->regenerate();
        $request->session()->put([
            'mfa_setup.user_id' => $user->getKey(),
            'mfa_setup.expires_at' => now()->addMinutes(15)->timestamp,
            'mfa_setup.purpose' => 'activation',
        ]);

        return redirect()->route('mfa-enrollment.create');
    }

    private function findInvite(string $token): ?AccountActivationInvite
    {
        return AccountActivationInvite::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->whereHas('user', fn ($query) => $query->where('is_active', false)->where('is_synthetic_admin', false))
            ->first();
    }
}
