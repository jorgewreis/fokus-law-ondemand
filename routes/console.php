<?php

use App\Mail\MfaRecoveryNotice;
use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use App\Services\AccountAudit;
use App\Services\AccountRequestLifecycle;
use App\Support\MailTransport;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('account-requests:expire', function (AccountRequestLifecycle $lifecycle): void {
    $expired = $lifecycle->expirePending();

    $this->info("Expired {$expired} account requests.");
})->purpose('Expire old synthetic account requests and remove their personal data')->hourly();

Artisan::command('law:recover-admin-mfa', function (AccountAudit $audit, SyntheticDatabaseGuard $databaseGuard): int {
    try {
        $databaseGuard->assertAllowed();
    } catch (Throwable) {
        $this->error('Recuperação recusada: exige APP_ENV=local e MySQL sintético dedicado.');

        return Command::FAILURE;
    }

    if (! app()->environment('local') || blank(config('app.key')) || ! MailTransport::configured()) {
        $this->error('Recuperação recusada: exige APP_ENV=local, MySQL sintético dedicado e MAIL_MAILER=smtp ou log para a notificação.');

        return Command::FAILURE;
    }

    $email = mb_strtolower(trim((string) $this->ask('E-mail do administrador a recuperar')));
    $administrator = User::query()->where('email', $email)->where('is_synthetic_admin', true)->first();

    if ($administrator === null || $administrator->mfa_enabled_at === null) {
        $this->error('Conta administrativa não elegível; nenhuma alteração foi feita.');

        return Command::FAILURE;
    }

    $password = (string) $this->secret('Senha atual da conta');
    if (! Hash::check($password, $administrator->password)) {
        $this->error('Senha inválida; nenhuma alteração foi feita.');

        return Command::FAILURE;
    }

    if (! $this->confirm('Confirma que validou a identidade por um canal de contato previamente conhecido?')) {
        $this->error('Verificação não confirmada; nenhuma alteração foi feita.');

        return Command::FAILURE;
    }

    DB::transaction(function () use ($administrator, $audit): void {
        $locked = User::query()->lockForUpdate()->findOrFail($administrator->getKey());
        abort_unless($locked->is_synthetic_admin && $locked->mfa_enabled_at !== null, 409);

        $locked->forceFill([
            'mfa_secret' => null,
            'mfa_enabled_at' => null,
            'mfa_reset_required' => true,
            'mfa_last_accepted_counter' => null,
        ])->save();
        $locked->recoveryCodes()->delete();
        DB::table('sessions')->where('user_id', $locked->getKey())->delete();
        $audit->record($locked, 'administrator.mfa_recovery', 'user', $locked->getKey(), 'known_contact_confirmed');
    });

    try {
        Mail::mailer(MailTransport::name())->to($administrator->email)->send(new MfaRecoveryNotice);
    } catch (Throwable $exception) {
        $audit->record($administrator, 'administrator.mfa_recovery_notice_failed', 'user', $administrator->getKey(), 'failure');

        $this->error('O fator foi revogado, mas a notificação falhou. Verifique o canal SMTP antes de concluir o novo cadastro.');

        return Command::FAILURE;
    }

    $this->info('Fator anterior e códigos revogados. A conta só recupera acesso após novo cadastro de TOTP.');

    return Command::SUCCESS;
})->purpose('Recover the one-time local administrator after known-channel verification; requires current password and SMTP');
