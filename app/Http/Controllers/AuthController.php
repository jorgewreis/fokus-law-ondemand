<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Security\TotpService;
use App\Services\AccountAudit;
use App\Services\RecoveryCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    public function store(Request $request, TotpService $totp, RecoveryCodeService $recoveryCodes, AccountAudit $audit): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'required_without:identifier', 'string', 'email:rfc', 'max:254'],
            'identifier' => ['nullable', 'required_without:email', 'string', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
            'totp' => ['nullable', 'string', 'max:16'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ], [
            'identifier.required_without' => 'Informe o e-mail ou a matrícula.',
            'email.required_without' => 'Informe o e-mail ou a matrícula.',
            'password.required' => 'Informe a senha.',
        ]);

        $identifier = trim((string) ($validated['identifier'] ?? $validated['email'] ?? ''));
        $user = $this->userForIdentifier($identifier);

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['auth' => 'Credenciais ou segundo fator inválidos.'])->onlyInput('email', 'identifier');
        }

        if ($user->mfa_enabled_at === null && ($user->is_synthetic_admin || $user->mfa_reset_required)) {
            $request->session()->regenerate();
            $request->session()->put([
                'mfa_setup.user_id' => $user->getKey(),
                'mfa_setup.expires_at' => now()->addMinutes(15)->timestamp,
                'mfa_setup.purpose' => $user->is_synthetic_admin ? 'bootstrap' : 'recovery',
            ]);

            return redirect()->route('mfa-enrollment.create');
        }

        if (! $user->is_active) {
            return back()->withErrors(['auth' => 'Credenciais ou segundo fator inválidos.'])->onlyInput('email', 'identifier');
        }

        if ($user->mfa_enabled_at === null) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(route('home'));
        }

        if (filled($validated['recovery_code'] ?? null) === filled($validated['totp'] ?? null)) {
            return back()->withErrors(['auth' => 'Credenciais ou segundo fator inválidos.'])->onlyInput('email', 'identifier');
        }

        $recovered = false;
        if (filled($validated['recovery_code'] ?? null)) {
            $recovered = $recoveryCodes->consume($user, $validated['recovery_code']);
        } elseif (filled($validated['totp'] ?? null)) {
            $counter = $totp->matchingCounter($user->mfa_secret, $validated['totp'], now()->timestamp);
            $recovered = $counter !== null && $this->acceptCounterOnce($user, $counter);
        }

        if (! $recovered) {
            return back()->withErrors(['auth' => 'Credenciais ou segundo fator inválidos.'])->onlyInput('email', 'identifier');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('auth.mfa_verified_at', now()->timestamp);

        if (filled($validated['recovery_code'] ?? null)) {
            $audit->record($user, 'mfa.recovery_code_used', 'user', $user->getKey());
        }

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function acceptCounterOnce(User $user, int $counter): bool
    {
        return DB::transaction(function () use ($user, $counter): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($locked->mfa_last_accepted_counter !== null && $counter <= $locked->mfa_last_accepted_counter) {
                return false;
            }

            $locked->forceFill(['mfa_last_accepted_counter' => $counter])->save();

            return true;
        });
    }

    private function userForIdentifier(string $identifier): ?User
    {
        if ($identifier === '') {
            return null;
        }

        if (str_contains($identifier, '@')) {
            return User::query()->where('email', mb_strtolower($identifier))->first();
        }

        return User::query()->where('matricula', $identifier)->first();
    }
}
