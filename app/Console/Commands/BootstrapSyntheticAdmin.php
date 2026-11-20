<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BootstrapSyntheticAdmin extends Command
{
    protected $signature = 'fokus:bootstrap-admin';

    protected $description = 'Create the configured synthetic administrator once, with no grants';

    public function handle(SyntheticDatabaseGuard $databaseGuard): int
    {
        try {
            $databaseGuard->assertAllowed();
        } catch (Throwable) {
            $this->error('Bootstrap refused: the active database is not an approved synthetic destination.');

            return self::FAILURE;
        }

        $name = config('admin-bootstrap.name');
        $email = config('admin-bootstrap.email');
        $tokenDigest = config('admin-bootstrap.token_sha256');

        if (! is_string($name) || trim($name) === ''
            || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ! is_string($tokenDigest) || preg_match('/\A[a-f0-9]{64}\z/i', $tokenDigest) !== 1) {
            $this->error('Bootstrap refused: external administrator configuration is incomplete or invalid.');

            return self::FAILURE;
        }

        try {
            if (DB::table('system_bootstrap_states')->where('key', 'synthetic_administrator')->exists()
                || User::query()->where('email', strtolower(trim($email)))->exists()) {
                $this->error('Bootstrap refused: the one-time bootstrap was already used or the configured account exists.');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('Bootstrap failed before prompting for secrets; no account was created.');

            return self::FAILURE;
        }

        $this->warn('This one-time command creates only the configured synthetic administrator. No grants or session will be created.');
        $token = $this->secret('Bootstrap token', false);

        if (! is_string($token) || $token === '' || ! hash_equals(strtolower($tokenDigest), hash('sha256', $token))) {
            $this->error('Bootstrap refused: token was invalid.');

            return self::FAILURE;
        }

        $password = $this->secret('Choose an initial password (at least 16 characters)', false);
        $passwordConfirmation = $this->secret('Confirm the initial password', false);

        if (! is_string($password) || strlen($password) < 16
            || ! is_string($passwordConfirmation) || ! hash_equals($password, $passwordConfirmation)) {
            $this->error('Bootstrap refused: password was too short or confirmation did not match.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($name, $email, $password): void {
                if (DB::table('system_bootstrap_states')->where('key', 'synthetic_administrator')->exists()) {
                    throw new BootstrapAlreadyConsumed;
                }

                if (User::query()->where('email', strtolower(trim($email)))->exists()) {
                    throw new BootstrapAlreadyConsumed;
                }

                $user = User::query()->create([
                    'name' => trim($name),
                    'email' => strtolower(trim($email)),
                    'password' => $password,
                ]);
                $user->is_synthetic_admin = true;
                $user->save();

                DB::table('system_bootstrap_states')->insert([
                    'key' => 'synthetic_administrator',
                    'user_id' => $user->id,
                    'completed_at' => now(),
                ]);
            });
        } catch (BootstrapAlreadyConsumed) {
            $this->error('Bootstrap refused: the one-time bootstrap was already used or the configured account exists.');

            return self::FAILURE;
        } catch (Throwable) {
            // Avoid surfacing database exception details that could include connection configuration.
            $this->error('Bootstrap failed; no account was created. Review server-side diagnostics without exposing secrets.');

            return self::FAILURE;
        }

        $this->info('Configured synthetic administrator created. The bootstrap token is now consumed.');

        return self::SUCCESS;
    }
}

class BootstrapAlreadyConsumed extends \RuntimeException {}
