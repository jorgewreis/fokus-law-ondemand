<?php

namespace Tests\Feature;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class BootstrapSyntheticAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_bootstrap_outside_local_before_database_mutation(): void
    {
        $this->artisan('fokus:bootstrap-admin')
            ->expectsOutputToContain('exige APP_ENV=local')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('system_bootstrap_states', 0);
    }

    public function test_it_creates_only_the_configured_admin_once_and_displays_a_random_password_once(): void
    {
        $this->app->instance('env', 'local');
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
            'admin-bootstrap.name' => 'Jorge (administrador sintético)',
            'admin-bootstrap.email' => 'jorge@example.test',
        ]);
        $guard = Mockery::mock(SyntheticDatabaseGuard::class);
        $guard->shouldReceive('assertAllowed')->twice();
        $this->app->instance(SyntheticDatabaseGuard::class, $guard);

        $this->artisan('fokus:bootstrap-admin')
            ->expectsOutputToContain('Senha aleatória, exibida somente nesta execução: ')
            ->expectsOutputToContain('Nenhum grant ou sessão foi criado.')
            ->assertExitCode(0);

        $user = User::query()->sole();
        $this->assertSame('jorge@example.test', $user->email);
        $this->assertTrue($user->is_synthetic_admin);
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->mfa_reset_required);
        $this->assertNotSame('jorge@example.test', $user->password);
        $this->assertTrue(Hash::needsRehash($user->password) === false);
        $this->assertDatabaseCount('account_grants', 0);
        $this->assertDatabaseHas('system_bootstrap_states', [
            'key' => 'synthetic_administrator',
            'user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('account_audit_events', ['event' => 'administrator.bootstrap']);

        $this->artisan('fokus:bootstrap-admin')
            ->expectsOutputToContain('uso único já foi consumido')
            ->doesntExpectOutputToContain('Senha aleatória, exibida somente nesta execução:')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('system_bootstrap_states', 1);
    }
}
