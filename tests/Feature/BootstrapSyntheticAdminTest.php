<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BootstrapSyntheticAdminTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'one-time-secret-token-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin-bootstrap.name' => 'Jorge Wreis',
            'admin-bootstrap.email' => 'jorge@example.test',
            'admin-bootstrap.token_sha256' => hash('sha256', self::TOKEN),
        ]);
    }

    public function test_it_creates_only_the_configured_admin_and_consumes_the_bootstrap_once(): void
    {
        $this->artisan('fokus:bootstrap-admin')
            ->expectsOutputToContain('No grants or session will be created.')
            ->expectsQuestion('Bootstrap token', self::TOKEN)
            ->expectsQuestion('Choose an initial password (at least 16 characters)', 'test-password-that-is-long')
            ->expectsQuestion('Confirm the initial password', 'test-password-that-is-long')
            ->expectsOutputToContain('bootstrap token is now consumed')
            ->doesntExpectOutputToContain(self::TOKEN)
            ->doesntExpectOutputToContain('test-password-that-is-long')
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'name' => 'Jorge Wreis',
            'email' => 'jorge@example.test',
            'is_synthetic_admin' => true,
        ]);
        $this->assertDatabaseHas('system_bootstrap_states', [
            'key' => 'synthetic_administrator',
            'user_id' => User::query()->value('id'),
        ]);
        $this->assertDatabaseMissing('system_bootstrap_states', ['key' => self::TOKEN]);
        $this->assertDatabaseMissing('users', ['email' => self::TOKEN]);

        $this->artisan('fokus:bootstrap-admin')
            ->expectsOutputToContain('one-time bootstrap was already used')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_rejects_an_invalid_token_without_creating_an_account(): void
    {
        $this->artisan('fokus:bootstrap-admin')
            ->expectsOutputToContain('No grants or session will be created.')
            ->expectsQuestion('Bootstrap token', 'incorrect-token')
            ->expectsOutputToContain('token was invalid')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('system_bootstrap_states', 0);
    }

    public function test_it_rejects_an_unsafe_database_before_prompting_for_secrets(): void
    {
        $this->app->instance('env', 'production');

        $this->artisan('fokus:bootstrap-admin')
            ->expectsOutputToContain('active database is not an approved synthetic destination')
            ->assertExitCode(1);
    }

    public function test_it_requires_password_confirmation_and_keeps_token_out_of_output(): void
    {
        $this->artisan('fokus:bootstrap-admin')
            ->expectsQuestion('Bootstrap token', self::TOKEN)
            ->expectsQuestion('Choose an initial password (at least 16 characters)', 'test-password-that-is-long')
            ->expectsQuestion('Confirm the initial password', 'different-password-that-is-long')
            ->expectsOutputToContain('password was too short or confirmation did not match')
            ->doesntExpectOutput(self::TOKEN)
            ->doesntExpectOutput('test-password-that-is-long')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('system_bootstrap_states', 0);
    }

    public function test_it_rolls_back_the_account_if_consuming_the_token_fails(): void
    {
        DB::statement("CREATE TRIGGER fail_bootstrap_state BEFORE INSERT ON system_bootstrap_states BEGIN SELECT RAISE(ABORT, 'forced failure'); END");

        $this->artisan('fokus:bootstrap-admin')
            ->expectsQuestion('Bootstrap token', self::TOKEN)
            ->expectsQuestion('Choose an initial password (at least 16 characters)', 'test-password-that-is-long')
            ->expectsQuestion('Confirm the initial password', 'test-password-that-is-long')
            ->expectsOutputToContain('Bootstrap failed; no account was created.')
            ->doesntExpectOutputToContain(self::TOKEN)
            ->doesntExpectOutputToContain('test-password-that-is-long')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('system_bootstrap_states', 0);
    }
}
