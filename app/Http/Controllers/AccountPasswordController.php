<?php

namespace App\Http\Controllers;

use App\Services\AccountAudit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountPasswordController extends Controller
{
    public function requestForm(): View
    {
        return view('account-password.request');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        abort_unless(config('mail.default') === 'smtp', 503, 'Configure o SMTP autorizado para entregar links de recuperação.');

        Password::sendResetLink(['email' => mb_strtolower(trim($validated['email']))]);

        return back()->with('status', 'Se a conta estiver ativa, um link de redefinição de uso único será enviado. O link não altera nem dispensa o TOTP.');
    }

    public function resetForm(Request $request): Response
    {
        abort_unless(is_string($request->query('token')) && is_string($request->query('email')), 404);

        return response()->view('account-password.reset', [
            'token' => $request->query('token'),
            'email' => $request->query('email'),
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function update(Request $request, AccountAudit $audit): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', 'confirmed', 'string', 'min:12', 'max:128'],
        ]);

        $status = Password::reset(
            [
                'email' => mb_strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'password_confirmation' => $validated['password'],
                'token' => $validated['token'],
            ],
            function ($user, string $password) use ($audit): void {
                DB::transaction(function () use ($user, $password, $audit): void {
                    $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                    $user->save();
                    DB::table('sessions')->where('user_id', $user->getKey())->delete();
                    $audit->record($user, 'account.password_reset', 'user', $user->getKey());
                    event(new PasswordReset($user));
                });
            },
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login.create')->with('status', 'Senha redefinida. O TOTP continua obrigatório para entrar.')
            : back()->withErrors(['email' => 'Não foi possível redefinir a senha com esse link.']);
    }
}
