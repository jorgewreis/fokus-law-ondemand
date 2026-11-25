<?php

namespace App\Services;

use App\AccountRequestStatus;
use App\Mail\AccountActivationInviteEmail;
use App\Models\AccountActivationInvite;
use App\Models\AccountRequest;
use App\Models\User;
use App\Support\MailTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class AccountActivation
{
    public function approve(AccountRequest $accountRequest, User $administrator): void
    {
        abort_unless(MailTransport::configured(), 503, 'Defina MAIL_MAILER como smtp ou log antes de aprovar e emitir convites.');

        $inviteData = DB::transaction(function () use ($accountRequest, $administrator): array {
            $locked = AccountRequest::query()->lockForUpdate()->findOrFail($accountRequest->getKey());

            abort_unless(
                $locked->status === AccountRequestStatus::PendingDeveloperApproval
                    && $locked->email_confirmed_at !== null
                    && $locked->expires_at->isFuture(),
                409,
            );

            abort_if(User::query()->where('email', $locked->email)->exists(), 409);

            if (filled($locked->matricula) && User::query()->where('matricula', $locked->matricula)->exists()) {
                abort(409, 'A matrícula informada já está em uso.');
            }

            $user = User::query()->create([
                'name' => $locked->display_name,
                'email' => $locked->email,
                'matricula' => $locked->matricula,
                'email_verified_at' => $locked->email_confirmed_at,
                'password' => bin2hex(random_bytes(48)),
            ]);
            $user->forceFill([
                'is_active' => false,
            ])->save();

            $locked->forceFill([
                'status' => AccountRequestStatus::Approved,
                'reviewed_by_user_id' => $administrator->getKey(),
                'linked_user_id' => $user->getKey(),
                'reviewed_at' => now(),
            ])->save();

            app(AccountAudit::class)->record($administrator, 'account_request.approved', 'account_request', $locked->getKey());

            return $this->createInvite($user, $administrator, 'account_request', $locked->getKey());
        });

        $this->deliverInvite($inviteData);
    }

    public function reject(AccountRequest $accountRequest, User $administrator): void
    {
        DB::transaction(function () use ($accountRequest, $administrator): void {
            $locked = AccountRequest::query()->lockForUpdate()->findOrFail($accountRequest->getKey());

            abort_unless(
                $locked->status === AccountRequestStatus::PendingDeveloperApproval
                    && $locked->email_confirmed_at !== null
                    && $locked->expires_at->isFuture(),
                409,
            );

            $locked->forceFill([
                'status' => AccountRequestStatus::Rejected,
                'reviewed_by_user_id' => $administrator->getKey(),
                'reviewed_at' => now(),
            ])->save();

            app(AccountAudit::class)->record($administrator, 'account_request.rejected', 'account_request', $locked->getKey());
        });
    }

    public function reissue(User $user, User $administrator): void
    {
        abort_unless(MailTransport::configured(), 503, 'Defina MAIL_MAILER como smtp ou log antes de emitir convites.');

        abort_unless($user->is_synthetic_admin === false && ! $user->is_active, 409);

        $inviteData = DB::transaction(function () use ($user, $administrator): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            abort_unless(! $locked->is_active && $locked->mfa_enabled_at === null, 409);

            return $this->createInvite($locked, $administrator, 'user', $locked->getKey());
        });

        $this->deliverInvite($inviteData);
    }

    /** @return array{email: string, token: string, expires_at: Carbon} */
    private function createInvite(User $user, User $administrator, string $subjectType, int|string $subjectId): array
    {
        AccountActivationInvite::query()->where('user_id', $user->getKey())->whereNull('consumed_at')->delete();

        $token = bin2hex(random_bytes(32));
        $expiresAt = now()->addHours(24);

        AccountActivationInvite::query()->create([
            'user_id' => $user->getKey(),
            'issued_by_user_id' => $administrator->getKey(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        app(AccountAudit::class)->record($administrator, 'account_activation.invite_issued', $subjectType, $subjectId);

        return ['email' => (string) $user->email, 'token' => $token, 'expires_at' => $expiresAt, 'user_id' => (string) $user->getKey()];
    }

    /** @param array{email: string, token: string, expires_at: Carbon} $inviteData */
    private function deliverInvite(array $inviteData): void
    {
        $mailer = MailTransport::name();
        abort_unless(MailTransport::configured(), 503, 'Defina MAIL_MAILER como smtp ou log antes de emitir convites.');

        $url = URL::temporarySignedRoute(
            'account-activation.show',
            $inviteData['expires_at'],
            ['token' => $inviteData['token']],
        );

        try {
            Mail::mailer($mailer)->to($inviteData['email'])->send(new AccountActivationInviteEmail($url));
        } catch (\Throwable $exception) {
            app(AccountAudit::class)->record(null, 'account_activation.invite_delivery_failed', 'user', $inviteData['user_id'], 'failure');

            throw $exception;
        }
    }
}
