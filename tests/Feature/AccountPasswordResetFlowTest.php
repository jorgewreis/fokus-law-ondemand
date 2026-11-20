<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountPasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('p', 32))]);
    }

    public function test_reset_token_is_one_use_and_does_not_remove_mfa_or_grants(): void
    {
        $user = User::factory()->create(['email' => 'password-reset@example.test']);
        $user->forceFill([
            'is_active' => true,
            'mfa_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'mfa_enabled_at' => now(),
            'mfa_reset_required' => false,
        ])->save();
        $token = Password::createToken($user);
        DB::table('sessions')->insert([
            'id' => 'existing-auth-session',
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'synthetic-test',
            'payload' => 'fixture',
            'last_activity' => now()->timestamp,
        ]);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-long-synthetic-password',
            'password_confirmation' => 'new-long-synthetic-password',
        ];

        $this->post(route('account-password.update'), $payload)
            ->assertRedirect(route('login.create'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('new-long-synthetic-password', $user->password));
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->mfa_enabled_at);
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $user->mfa_secret);
        $this->assertFalse($user->mfa_reset_required);
        $this->assertDatabaseMissing('sessions', ['id' => 'existing-auth-session']);
        $this->assertDatabaseHas('account_audit_events', ['event' => 'account.password_reset']);
        $this->assertDatabaseCount('account_grants', 0);

        $this->from(route('account-password.request'))->post(route('account-password.update'), $payload)
            ->assertRedirect(route('account-password.request'))
            ->assertSessionHasErrors('email');
    }

    public function test_reset_token_expires_after_configured_thirty_minutes(): void
    {
        $user = User::factory()->create(['email' => 'expired-reset@example.test']);
        $user->forceFill([
            'is_active' => true,
            'mfa_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'mfa_enabled_at' => now(),
        ])->save();
        $token = Password::createToken($user);

        $this->travel(31)->minutes();
        $this->from(route('account-password.request'))->post(route('account-password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-long-synthetic-password',
            'password_confirmation' => 'new-long-synthetic-password',
        ])->assertRedirect(route('account-password.request'))
            ->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('new-long-synthetic-password', $user->fresh()->password));
        $this->assertDatabaseMissing('account_audit_events', ['event' => 'account.password_reset']);
    }
}
