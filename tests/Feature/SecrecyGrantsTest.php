<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProcessSecrecyAccess;
use App\Services\SecrecyGrants;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecrecyGrantsTest extends TestCase
{
    use RefreshDatabase;

    private function account(bool $admin = false): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_synthetic_admin' => $admin, 'is_active' => true, 'mfa_enabled_at' => now()])->save();

        return $user;
    }

    public function test_catalog_is_fixed_and_grant_is_exact_without_inheritance(): void
    {
        $this->assertSame([0, 1, 2, 3, 4, 5], DB::table('secrecy_catalog')->orderBy('code')->pluck('code')->map(fn ($code): int => (int) $code)->all());
        $admin = $this->account(true);
        $user = $this->account();
        $service = app(SecrecyGrants::class);
        $access = app(ProcessSecrecyAccess::class);

        $this->assertFalse($access->allows($user, '2'));
        $service->set($admin, $user, 2, true);
        $service->set($admin, $user, 2, true);
        $this->assertTrue($access->allows($user, '2'));
        $this->assertFalse($access->allows($user, '1'));
        $this->assertFalse($access->allows($user, '02'));
        $this->assertFalse($access->allows($user, '6'));
        $this->assertSame(1, DB::table('secrecy_grant_events')->count());

        $service->set($admin, $user, 2, false);
        $service->set($admin, $user, 2, false);
        $this->assertFalse($access->allows($user, '2'));
        $this->assertSame(['granted', 'revoked'], DB::table('secrecy_grant_events')->orderBy('id')->pluck('event')->all());
    }

    public function test_only_active_synthetic_admin_with_mfa_can_manage_grants(): void
    {
        $subject = $this->account();
        foreach ([$this->account(), tap($this->account(true), fn (User $u) => $u->forceFill(['mfa_enabled_at' => null])->save()), tap($this->account(true), fn (User $u) => $u->forceFill(['is_active' => false])->save())] as $actor) {
            try {
                app(SecrecyGrants::class)->set($actor, $subject, 1, true);
                $this->fail('Unauthorized grant should be rejected.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('account_secrecy_grants', 0);
            }
        }
    }

    public function test_invalid_or_damaged_catalog_fails_closed_but_revocation_is_available(): void
    {
        $admin = $this->account(true);
        $user = $this->account();
        $service = app(SecrecyGrants::class);
        $service->set($admin, $user, 4, true);
        DB::table('secrecy_catalog')->where('code', 3)->delete();
        $this->assertFalse(app(ProcessSecrecyAccess::class)->allows($user, '4'));
        try {
            $service->set($admin, $user, 2, true);
            $this->fail('New grants require the canonical catalog.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('account_secrecy_grants', 1);
        }
        $service->set($admin, $user, 4, false);
        $this->assertDatabaseCount('account_secrecy_grants', 0);
    }

    public function test_audit_failure_rolls_back_grant(): void
    {
        $admin = $this->account(true);
        $user = $this->account();
        DB::statement("CREATE TRIGGER reject_secrecy_event BEFORE INSERT ON secrecy_grant_events BEGIN SELECT RAISE(ABORT, 'audit failure'); END");

        try {
            app(SecrecyGrants::class)->set($admin, $user, 3, true);
            $this->fail('Expected audit failure.');
        } catch (QueryException) {
            $this->assertDatabaseCount('account_secrecy_grants', 0);
        }
    }

    public function test_admin_screen_and_mutation_require_session_mfa_and_jorges_admin_marker(): void
    {
        $admin = $this->account(true);
        $subject = $this->account();
        $this->get(route('secrecy-admin.index'))->assertRedirect(route('login.create'));
        $this->actingAs($this->account(true))->get(route('secrecy-admin.index'))->assertForbidden();
        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->timestamp]);
        $this->get(route('secrecy-admin.index'))->assertOk()->assertSeeText('rótulo de classificação sintética');
        $this->post(route('secrecy-admin.grant', $subject), ['secrecy' => 2, 'operation' => 'grant'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('account_secrecy_grants', ['user_id' => $subject->id, 'secrecy_code' => 2]);
        $this->post(route('secrecy-admin.grant', $subject), ['secrecy' => 6, 'operation' => 'grant'])->assertSessionHasErrors('secrecy');
    }
}
