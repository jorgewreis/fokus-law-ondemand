<?php

namespace Tests\Feature;

use App\AccountRequestStatus;
use App\Mail\AccountActivationInviteEmail;
use App\Models\AccountRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountActivationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    public function test_approved_one_time_invite_activates_only_after_totp_enrollment(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $admin = User::factory()->create();
        $admin->forceFill([
            'is_synthetic_admin' => true,
            'is_active' => true,
            'mfa_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'mfa_enabled_at' => now(),
        ])->save();
        $request = AccountRequest::factory()->create([
            'display_name' => 'Pessoa Avaliadora',
            'email' => 'activation@example.test',
            'status' => AccountRequestStatus::PendingDeveloperApproval,
            'email_confirmed_at' => now(),
        ]);

        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->timestamp])
            ->post(route('account-admin.approve', $request))
            ->assertRedirect();

        $user = User::query()->where('email', 'activation@example.test')->sole();
        $this->assertFalse($user->is_active);
        $this->assertDatabaseCount('account_grants', 0);

        /** @var AccountActivationInviteEmail $inviteMail */
        $inviteMail = Mail::sent(AccountActivationInviteEmail::class)->sole();
        $activationUrl = $inviteMail->activationUrl;
        $query = [];
        parse_str((string) parse_url($activationUrl, PHP_URL_QUERY), $query);
        $token = $query['token'] ?? null;
        $this->assertIsString($token);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $token);

        $this->get($activationUrl)->assertOk()->assertSee('Ativar conta');
        $this->post(route('account-activation.store'), [
            'token' => $token,
            'password' => 'a-long-synthetic-password',
            'password_confirmation' => 'a-long-synthetic-password',
        ])->assertRedirect(route('mfa-enrollment.create'))
            ->assertSessionHas('mfa_setup.user_id', $user->getKey());

        $this->assertFalse($user->fresh()->is_active);
        $this->get(route('mfa-enrollment.create'))->assertOk();
        $encryptedSecret = session('mfa_setup.secret');
        $secret = Crypt::decryptString($encryptedSecret);
        $code = $this->totpCode($secret, now()->timestamp);

        $this->post(route('mfa-enrollment.store'), ['code' => $code])
            ->assertOk()
            ->assertSee('Códigos de recuperação');

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->mfa_enabled_at);
        $this->assertSame($secret, $user->mfa_secret);
        $this->assertDatabaseCount('account_grants', 0);
        $this->assertDatabaseCount('mfa_recovery_codes', 10);
        $this->assertDatabaseHas('account_audit_events', ['event' => 'account_activation.started']);
        $this->assertDatabaseHas('account_audit_events', ['event' => 'mfa.enrolled']);

        $this->post(route('account-activation.store'), [
            'token' => $token,
            'password' => 'a-long-synthetic-password',
            'password_confirmation' => 'a-long-synthetic-password',
        ])->assertGone();
    }

    private function totpCode(string $secret, int $timestamp): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        $buffer = 0;
        $bits = 0;

        foreach (str_split($secret) as $character) {
            $buffer = ($buffer << 5) | strpos($alphabet, $character);
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $binary .= chr(($buffer >> $bits) & 0xFF);
                $buffer &= (1 << $bits) - 1;
            }
        }

        $counter = intdiv($timestamp, 30);
        $digest = hash_hmac('sha1', pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000), $binary, true);
        $offset = ord($digest[19]) & 0x0F;
        $number = unpack('Nvalue', substr($digest, $offset, 4))['value'] & 0x7FFFFFFF;

        return str_pad((string) ($number % 1_000_000), 6, '0', STR_PAD_LEFT);
    }
}
