<?php

namespace Tests\Feature;

use App\AccountRequestStatus;
use App\Mail\AccountActivationInviteEmail;
use App\Mail\MfaRecoveryNotice;
use App\Models\AccountActivationInvite;
use App\Models\AccountGrant;
use App\Models\AccountRequest;
use App\Models\MfaRecoveryCode;
use App\Models\MfaRecoveryRequest;
use App\Models\User;
use App\Services\AccountAccess;
use App\Services\RecoveryCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccountLifecycleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
    }

    public function test_module_action_grants_are_explicit_and_deny_by_default(): void
    {
        $user = $this->activeUser();
        $access = app(AccountAccess::class);

        $this->assertFalse($access->allows($user, 'people', 'read'));

        AccountGrant::query()->create([
            'user_id' => $user->getKey(),
            'module' => 'people',
            'action' => 'read',
            'granted_by_user_id' => User::factory()->create()->getKey(),
        ]);

        $this->assertTrue($access->allows($user, 'people', 'read'));
        $this->assertFalse($access->allows($user, 'people', 'delete'));
        $this->assertFalse($access->allows($user, 'processes', 'read'));
    }

    public function test_grant_middleware_blocks_missing_or_expired_mfa_session(): void
    {
        Route::middleware(['web', 'auth', 'grant:people,read'])->get('/_test/granted-people', fn () => response('allowed'));
        $user = $this->activeUser();

        $this->actingAs($user)->get('/_test/granted-people')->assertForbidden();

        AccountGrant::query()->create([
            'user_id' => $user->getKey(),
            'module' => 'people',
            'action' => 'read',
            'granted_by_user_id' => User::factory()->create()->getKey(),
        ]);

        $this->withSession(['auth.mfa_verified_at' => now()->timestamp])
            ->actingAs($user)
            ->get('/_test/granted-people')
            ->assertOk()
            ->assertSeeText('allowed');

        $this->withSession(['auth.mfa_verified_at' => now()->subHours(13)->timestamp])
            ->actingAs($user)
            ->get('/_test/granted-people')
            ->assertForbidden();
    }

    public function test_totp_login_accepts_counter_once_and_rejects_replay(): void
    {
        $this->travelTo(now()->setTimestamp(59));
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user = $this->activeUser(['mfa_secret' => $secret]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
            'totp' => '287082',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, $user->fresh()->mfa_last_accepted_counter);

        auth()->logout();

        $this->from(route('login.create'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
            'totp' => '287082',
        ])->assertRedirect(route('login.create'))
            ->assertSessionHasErrors('auth');
    }

    public function test_recovery_code_is_consumed_once_and_totp_is_required_for_login(): void
    {
        $user = $this->activeUser();
        $codes = app(RecoveryCodeService::class)->replaceForEnrollment($user);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
            'recovery_code' => $codes[0],
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('mfa_recovery_codes', [
            'user_id' => $user->getKey(),
            'used_at' => now()->toDateTimeString(),
        ]);

        auth()->logout();
        $this->from(route('login.create'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
            'recovery_code' => $codes[0],
        ])->assertRedirect(route('login.create'))
            ->assertSessionHasErrors('auth');

        $this->assertDatabaseMissing('mfa_recovery_codes', ['code_hash' => $codes[0]]);
    }

    public function test_password_reset_page_does_not_fail_from_an_unresolved_return_type(): void
    {
        $this->get(route('account-password.request'))->assertOk();
    }

    public function test_admin_approval_creates_an_inactive_account_without_grants_and_issues_one_time_invite(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $admin = $this->activeUser(['is_synthetic_admin' => true]);
        $request = AccountRequest::factory()->create([
            'display_name' => 'Pessoa Avaliadora',
            'email' => 'reviewer@example.test',
            'status' => AccountRequestStatus::PendingDeveloperApproval,
            'email_confirmed_at' => now(),
        ]);

        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->timestamp])
            ->post(route('account-admin.approve', $request))
            ->assertRedirect();

        $request->refresh();
        $user = User::query()->where('email', 'reviewer@example.test')->sole();
        $this->assertSame(AccountRequestStatus::Approved, $request->status);
        $this->assertSame($user->getKey(), $request->linked_user_id);
        $this->assertFalse($user->is_active);
        $this->assertFalse($user->is_synthetic_admin);
        $this->assertDatabaseCount('account_grants', 0);
        $invite = AccountActivationInvite::query()->where('user_id', $user->getKey())->sole();
        $this->assertNull($invite->consumed_at);
        $this->assertTrue($invite->expires_at->between(now()->addHours(23), now()->addHours(25)));
        $this->assertSame(64, strlen($invite->token_hash));
        $this->assertDatabaseHas('account_audit_events', ['event' => 'account_request.approved']);
        $this->assertDatabaseHas('account_audit_events', ['event' => 'account_activation.invite_issued']);
        Mail::assertSent(AccountActivationInviteEmail::class, fn (AccountActivationInviteEmail $mail): bool => $mail->hasTo('reviewer@example.test'));
    }

    public function test_admin_grants_only_allowlisted_module_actions_and_audit_each_change(): void
    {
        $admin = $this->activeUser(['is_synthetic_admin' => true]);
        $user = $this->activeUser(['email' => 'grantee@example.test']);

        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->timestamp])
            ->post(route('account-admin.grants', $user), ['grants' => ['people.read', 'processes.create']])
            ->assertRedirect();

        $this->assertDatabaseCount('account_grants', 2);
        $this->assertDatabaseHas('account_grants', [
            'user_id' => $user->getKey(),
            'module' => 'people',
            'action' => 'read',
            'granted_by_user_id' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('account_audit_events', [
            'event' => 'account_grant.granted',
            'module' => 'people',
            'action' => 'read',
        ]);

        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->timestamp])
            ->post(route('account-admin.grants', $user), ['grants' => ['processes.delete']])
            ->assertSessionHasErrors('grants.0');

        $this->assertDatabaseCount('account_grants', 2);
    }

    public function test_mfa_recovery_requires_known_contact_confirmation_and_revokes_old_factors(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $admin = $this->activeUser(['is_synthetic_admin' => true]);
        $user = $this->activeUser(['email' => 'recovery-target@example.test']);
        MfaRecoveryCode::query()->create([
            'user_id' => $user->getKey(),
            'code_hash' => Hash::make('AABBCCDDEEFF'),
        ]);
        $recoveryRequest = MfaRecoveryRequest::query()->create([
            'user_id' => $user->getKey(),
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->timestamp])
            ->post(route('mfa-recovery.approve', $recoveryRequest))
            ->assertSessionHasErrors('confirmed_by_known_channel');
        $this->assertNotNull($user->fresh()->mfa_enabled_at);
        $this->assertDatabaseHas('mfa_recovery_codes', ['user_id' => $user->getKey(), 'used_at' => null]);

        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->timestamp])
            ->post(route('mfa-recovery.approve', $recoveryRequest), ['confirmed_by_known_channel' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertNull($user->mfa_enabled_at);
        $this->assertNull($user->mfa_secret);
        $this->assertTrue($user->mfa_reset_required);
        $this->assertDatabaseCount('mfa_recovery_codes', 0);
        $this->assertDatabaseHas('mfa_recovery_requests', ['id' => $recoveryRequest->getKey(), 'status' => 'approved']);
        $this->assertDatabaseHas('account_audit_events', ['event' => 'mfa.recovery_approved', 'outcome' => 'known_contact_confirmed']);
        Mail::assertSent(MfaRecoveryNotice::class, fn (MfaRecoveryNotice $mail): bool => $mail->hasTo('recovery-target@example.test'));
    }

    public function test_bootstrap_and_admin_recovery_commands_fail_closed_outside_local_mysql(): void
    {
        $this->artisan('fokus:bootstrap-admin')->assertExitCode(1);
        $this->artisan('law:recover-admin-mfa')->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
    }

    private function activeUser(array $overrides = []): User
    {
        $user = User::factory()->create([
            'email' => $overrides['email'] ?? 'active-user@example.test',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $user->forceFill(array_merge([
            'is_active' => true,
            'mfa_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'mfa_enabled_at' => now(),
            'mfa_reset_required' => false,
        ], $overrides))->save();

        return $user->fresh();
    }
}
