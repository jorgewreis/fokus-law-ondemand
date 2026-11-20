<?php

namespace Tests\Feature;

use App\Mail\MfaRecoveryNotice;
use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use App\Services\RecoveryCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class AdminMfaRecoveryCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance('env', 'local');
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('r', 32)),
            'mail.default' => 'smtp',
        ]);
        $guard = Mockery::mock(SyntheticDatabaseGuard::class);
        $guard->shouldReceive('assertAllowed')->once();
        $this->app->instance(SyntheticDatabaseGuard::class, $guard);
    }

    public function test_recovery_requires_password_and_known_contact_confirmation_then_revokes_factors(): void
    {
        Mail::fake();
        $admin = $this->administrator();
        app(RecoveryCodeService::class)->replaceForEnrollment($admin);
        DB::table('sessions')->insert([
            'id' => 'admin-session-to-revoke',
            'user_id' => $admin->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'synthetic-test',
            'payload' => 'fixture',
            'last_activity' => now()->timestamp,
        ]);

        $this->artisan('law:recover-admin-mfa')
            ->expectsQuestion('E-mail do administrador a recuperar', $admin->email)
            ->expectsQuestion('Senha atual da conta', 'current-admin-password')
            ->expectsConfirmation('Confirma que validou a identidade por um canal de contato previamente conhecido?', 'yes')
            ->expectsOutputToContain('Fator anterior e códigos revogados.')
            ->assertExitCode(0);

        $admin->refresh();
        $this->assertNull($admin->mfa_enabled_at);
        $this->assertNull($admin->mfa_secret);
        $this->assertNull($admin->mfa_last_accepted_counter);
        $this->assertTrue($admin->mfa_reset_required);
        $this->assertDatabaseCount('mfa_recovery_codes', 0);
        $this->assertDatabaseMissing('sessions', ['id' => 'admin-session-to-revoke']);
        $this->assertDatabaseHas('account_audit_events', [
            'event' => 'administrator.mfa_recovery',
            'outcome' => 'known_contact_confirmed',
        ]);
        Mail::assertSent(MfaRecoveryNotice::class, fn (MfaRecoveryNotice $mail): bool => $mail->hasTo($admin->email));
    }

    public function test_recovery_confirmation_no_preserves_the_current_factor(): void
    {
        Mail::fake();
        $admin = $this->administrator();

        $this->artisan('law:recover-admin-mfa')
            ->expectsQuestion('E-mail do administrador a recuperar', $admin->email)
            ->expectsQuestion('Senha atual da conta', 'current-admin-password')
            ->expectsConfirmation('Confirma que validou a identidade por um canal de contato previamente conhecido?', 'no')
            ->expectsOutputToContain('Verificação não confirmada; nenhuma alteração foi feita.')
            ->assertExitCode(1);

        $admin->refresh();
        $this->assertNotNull($admin->mfa_enabled_at);
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $admin->mfa_secret);
        $this->assertFalse($admin->mfa_reset_required);
        $this->assertDatabaseCount('account_audit_events', 0);
        Mail::assertNothingSent();
    }

    private function administrator(): User
    {
        $admin = User::factory()->create([
            'name' => 'Jorge sintético',
            'email' => 'jorge@example.test',
            'password' => Hash::make('current-admin-password'),
        ]);
        $admin->forceFill([
            'is_synthetic_admin' => true,
            'is_active' => true,
            'mfa_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'mfa_enabled_at' => now(),
            'mfa_reset_required' => false,
        ])->save();

        return $admin->fresh();
    }
}
