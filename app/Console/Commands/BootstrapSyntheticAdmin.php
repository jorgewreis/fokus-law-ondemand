<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use App\Services\AccountAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BootstrapSyntheticAdmin extends Command
{
    protected $signature = 'fokus:bootstrap-admin';

    protected $description = 'Create the configured synthetic administrator once with a one-time random password';

    public function handle(SyntheticDatabaseGuard $databaseGuard, AccountAudit $audit): int
    {
        if (! app()->environment('local') || blank(config('app.key'))) {
            $this->error('Bootstrap recusado: exige APP_ENV=local e APP_KEY configurada.');

            return self::FAILURE;
        }

        try {
            $databaseGuard->assertAllowed();
        } catch (Throwable) {
            $this->error('Bootstrap recusado: o banco ativo não é o MySQL sintético local aprovado.');

            return self::FAILURE;
        }

        $name = config('admin-bootstrap.name');
        $email = is_string(config('admin-bootstrap.email'))
            ? mb_strtolower(trim(config('admin-bootstrap.email')))
            : null;

        if (! is_string($name) || trim($name) === ''
            || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Bootstrap recusado: configure nome e e-mail de Jorge em docker.env antes de executar.');

            return self::FAILURE;
        }

        $password = bin2hex(random_bytes(24));

        try {
            $user = DB::transaction(function () use ($name, $email, $password, $audit): User {
                if (DB::table('system_bootstrap_states')->where('key', 'synthetic_administrator')->lockForUpdate()->exists()
                    || User::query()->where('is_synthetic_admin', true)->exists()
                    || User::query()->where('email', $email)->exists()) {
                    throw new BootstrapAlreadyConsumed;
                }

                $user = User::query()->create([
                    'name' => trim($name),
                    'email' => $email,
                    'password' => $password,
                ]);
                $user->forceFill([
                    'is_synthetic_admin' => true,
                    'is_active' => false,
                    'mfa_reset_required' => true,
                ])->save();

                DB::table('system_bootstrap_states')->insert([
                    'key' => 'synthetic_administrator',
                    'user_id' => $user->getKey(),
                    'completed_at' => now(),
                ]);
                $audit->record($user, 'administrator.bootstrap', 'user', $user->getKey());

                return $user;
            });
        } catch (BootstrapAlreadyConsumed) {
            $this->error('Bootstrap recusado: já existe conta administrativa ou o uso único já foi consumido.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Bootstrap falhou; nenhuma conta foi criada. Consulte diagnósticos locais sem expor segredos.');

            return self::FAILURE;
        }

        $this->info('Conta administrativa sintética criada. Nenhum grant ou sessão foi criado.');
        $this->line('Senha aleatória, exibida somente nesta execução: '.$password);
        $this->warn('Guarde a senha em local seguro. Ela não pode ser recuperada pelo sistema. Cadastre TOTP antes de entrar.');

        return self::SUCCESS;
    }
}

class BootstrapAlreadyConsumed extends \RuntimeException {}
