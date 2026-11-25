<?php

namespace App\Http\Controllers;

use App\Mail\MfaRecoveryNotice;
use App\Models\MfaRecoveryRequest;
use App\Models\User;
use App\Services\AccountAudit;
use App\Support\MailTransport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MfaRecoveryAdminController extends Controller
{
    public function approve(Request $request, MfaRecoveryRequest $recoveryRequest, AccountAudit $audit): RedirectResponse
    {
        $request->validate(['confirmed_by_known_channel' => ['required', 'accepted']]);
        if (! MailTransport::configured()) {
            return back()->withErrors([
                'mail' => 'O envio de e-mail não está configurado. Defina MAIL_MAILER como smtp ou log antes de redefinir o MFA.',
            ]);
        }

        $target = DB::transaction(function () use ($request, $recoveryRequest, $audit): User {
            $lockedRequest = MfaRecoveryRequest::query()->lockForUpdate()->findOrFail($recoveryRequest->getKey());
            abort_unless($lockedRequest->status === 'pending', 409);

            $user = User::query()->lockForUpdate()->findOrFail($lockedRequest->user_id);
            abort_unless($user->is_active && $user->mfa_enabled_at !== null, 409);

            $user->forceFill([
                'mfa_secret' => null,
                'mfa_enabled_at' => null,
                'mfa_reset_required' => true,
                'mfa_last_accepted_counter' => null,
            ])->save();
            $user->recoveryCodes()->delete();

            $lockedRequest->forceFill([
                'status' => 'approved',
                'reviewed_by_user_id' => $request->user()->getKey(),
                'reviewed_at' => now(),
            ])->save();

            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            $audit->record($request->user(), 'mfa.recovery_approved', 'user', $user->getKey(), 'known_contact_confirmed');

            return $user;
        });

        try {
            Mail::mailer(MailTransport::name())->to($target->email)->send(new MfaRecoveryNotice);
        } catch (\Throwable $exception) {
            $audit->record($request->user(), 'mfa.recovery_notice_failed', 'user', $target->getKey(), 'failure');

            report($exception);

            return back()->withErrors([
                'mail' => 'O fator antigo foi revogado, mas a notificação não pôde ser enviada. Verifique MAIL_MAILER.',
            ]);
        }

        return back()->with('status', 'O fator antigo foi revogado. A conta só recupera acesso após cadastrar um novo TOTP. A notificação foi encaminhada pelo canal de e-mail configurado.');
    }
}
