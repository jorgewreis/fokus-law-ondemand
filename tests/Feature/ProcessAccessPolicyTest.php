<?php

namespace Tests\Feature;

use App\Data\ProcessAccessScope;
use App\Models\User;
use App\Services\CompetencyCatalog;
use App\Services\CompetencyGrants;
use App\Services\ProcessAccessPolicy;
use App\Services\ProcessSecrecyAccess;
use App\Services\ProcessUnitAccess;
use App\Services\ProcessUnitConfiguration;
use App\Services\SecrecyGrants;
use App\Services\UnitGrants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ProcessAccessPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User} */
    private function accounts(): array
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_active' => true, 'is_synthetic_admin' => true, 'mfa_enabled_at' => now()->subMinute()])->save();
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true, 'mfa_enabled_at' => now()->subMinute()])->save();
        app(CompetencyCatalog::class)->importSnapshot();
        app(CompetencyGrants::class)->set($admin, $user, 3, true);
        DB::table('account_grants')->insert(['user_id' => $user->getKey(), 'module' => 'processes', 'action' => 'read', 'granted_by_user_id' => $admin->getKey(), 'created_at' => now(), 'updated_at' => now()]);

        return [$admin, $user];
    }

    private function allowSyntheticScopes(): void
    {
        $this->app->instance(ProcessUnitAccess::class, new class extends ProcessUnitAccess
        {
            public function allows(User $user, int|string $unitId): bool
            {
                return $unitId === 'unit-synthetic';
            }
        });
        $this->app->instance(ProcessSecrecyAccess::class, new class extends ProcessSecrecyAccess
        {
            public function allows(User $user, string $secrecyCode): bool
            {
                return $secrecyCode === 'synthetic-secret';
            }
        });
    }

    private function scope(): ProcessAccessScope
    {
        return new ProcessAccessScope('unit-synthetic', 3, 'synthetic-secret');
    }

    public function test_default_verifiers_deny_even_when_other_gates_pass(): void
    {
        [, $user] = $this->accounts();
        $this->assertFalse(app(ProcessAccessPolicy::class)->allows($user, 'read', $this->scope(), now()->timestamp));
    }

    public function test_default_secrecy_verifier_denies_even_when_unit_is_allowed(): void
    {
        [, $user] = $this->accounts();
        $this->app->instance(ProcessUnitAccess::class, new class extends ProcessUnitAccess
        {
            public function allows(User $user, int|string $unitId): bool
            {
                return true;
            }
        });
        $this->assertFalse(app(ProcessAccessPolicy::class)->allows($user, 'read', $this->scope(), now()->timestamp));
    }

    public function test_all_gates_must_pass_with_doubles_for_unimplemented_verifiers(): void
    {
        [, $user] = $this->accounts();
        $this->allowSyntheticScopes();
        $policy = app(ProcessAccessPolicy::class);
        $this->assertTrue($policy->allows($user, 'read', $this->scope(), now()->timestamp));
        foreach ([new ProcessAccessScope(null, 3, 'synthetic-secret'), new ProcessAccessScope('unit-synthetic', null, 'synthetic-secret'), new ProcessAccessScope('unit-synthetic', 3, null), new ProcessAccessScope('other-unit', 3, 'synthetic-secret'), new ProcessAccessScope('unit-synthetic', 16, 'synthetic-secret'), new ProcessAccessScope('unit-synthetic', 3, 'other-secret')] as $scope) {
            $this->assertFalse($policy->allows($user, 'read', $scope, now()->timestamp));
        }
        $this->assertFalse($policy->allows(null, 'read', $this->scope(), now()->timestamp));
        $this->assertFalse($policy->allows($user, 'create', $this->scope(), now()->timestamp));
        $this->assertFalse($policy->allows($user, 'delete', $this->scope(), now()->timestamp));
    }

    public function test_composition_uses_persisted_account_unit_competency_and_secrecy_grants(): void
    {
        [$admin, $user] = $this->accounts();
        app(ProcessUnitConfiguration::class)->set($admin, '1ª Vara Crime de Ilhéus', 0);
        $unitId = (string) DB::table('process_module_unit_settings')->value('unit_id');
        app(UnitGrants::class)->set($admin, $user, $unitId, true);
        app(SecrecyGrants::class)->set($admin, $user, 2, true);
        $scope = new ProcessAccessScope($unitId, 3, '2');
        $policy = app(ProcessAccessPolicy::class);

        $this->assertTrue($policy->allows($user, 'read', $scope, now()->timestamp));
        $this->assertFalse($policy->allows($user, 'read', new ProcessAccessScope($unitId, 16, '2'), now()->timestamp));
        $this->assertFalse($policy->allows($user, 'read', new ProcessAccessScope($unitId, 3, '1'), now()->timestamp));
        $this->assertFalse($policy->allows($user, 'read', new ProcessAccessScope('00000000-0000-4000-8000-000000000001', 3, '2'), now()->timestamp));

        app(SecrecyGrants::class)->set($admin, $user, 2, false);
        $this->assertFalse($policy->allows($user, 'read', $scope, now()->timestamp));
        app(SecrecyGrants::class)->set($admin, $user, 2, true);
        User::query()->whereKey($user->getKey())->update(['mfa_reset_required' => true]);
        $this->assertFalse($policy->allows($user, 'read', $scope, now()->timestamp));
    }

    public function test_session_mfa_must_be_recent_and_bound_to_now(): void
    {
        [, $user] = $this->accounts();
        $this->allowSyntheticScopes();
        $policy = app(ProcessAccessPolicy::class);
        foreach ([null, '', 'invalid', true, now()->subHours(12)->subSecond()->timestamp, now()->addSecond()->timestamp] as $timestamp) {
            $this->assertFalse($policy->allows($user, 'read', $this->scope(), $timestamp));
        }
        $this->assertTrue($policy->allows($user, 'read', $this->scope(), (string) now()->timestamp));
    }

    public function test_update_requires_read_and_update_grants(): void
    {
        [$admin, $user] = $this->accounts();
        $this->allowSyntheticScopes();
        $policy = app(ProcessAccessPolicy::class);
        $this->assertFalse($policy->allows($user, 'update', $this->scope(), now()->timestamp));
        DB::table('account_grants')->insert(['user_id' => $user->getKey(), 'module' => 'processes', 'action' => 'update', 'granted_by_user_id' => $admin->getKey(), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue($policy->allows($user, 'update', $this->scope(), now()->timestamp));
        DB::table('account_grants')->where('user_id', $user->getKey())->where('module', 'processes')->where('action', 'read')->delete();
        $this->assertFalse($policy->allows($user, 'update', $this->scope(), now()->timestamp));
    }

    public function test_archive_requires_both_read_and_archive(): void
    {
        [$admin, $user] = $this->accounts();
        $this->allowSyntheticScopes();
        $policy = app(ProcessAccessPolicy::class);
        $this->assertFalse($policy->allows($user, 'archive', $this->scope(), now()->timestamp));
        DB::table('account_grants')->insert(['user_id' => $user->getKey(), 'module' => 'processes', 'action' => 'archive', 'granted_by_user_id' => $admin->getKey(), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue($policy->allows($user, 'archive', $this->scope(), now()->timestamp));
        DB::table('account_grants')->where('user_id', $user->getKey())->where('module', 'processes')->where('action', 'read')->delete();
        $this->assertFalse($policy->allows($user, 'archive', $this->scope(), now()->timestamp));
    }

    public function test_account_is_reloaded_and_revocation_takes_effect(): void
    {
        [, $user] = $this->accounts();
        $this->allowSyntheticScopes();
        $policy = app(ProcessAccessPolicy::class);
        $this->assertTrue($policy->allows($user, 'read', $this->scope(), now()->timestamp));
        User::query()->whereKey($user->getKey())->update(['is_active' => false]);
        $this->assertFalse($policy->allows($user, 'read', $this->scope(), now()->timestamp));
    }

    public function test_session_mfa_predating_current_enrollment_is_rejected(): void
    {
        [, $user] = $this->accounts();
        $this->allowSyntheticScopes();
        $enrollment = now()->subMinute()->startOfSecond();
        User::query()->whereKey($user->getKey())->update(['mfa_enabled_at' => $enrollment]);
        $policy = app(ProcessAccessPolicy::class);
        $this->assertFalse($policy->allows($user, 'read', $this->scope(), $enrollment->timestamp - 1));
        $this->assertFalse($policy->allows($user, 'read', $this->scope(), $enrollment->timestamp));
        $this->assertTrue($policy->allows($user, 'read', $this->scope(), $enrollment->timestamp + 1));
        User::query()->whereKey($user->getKey())->update(['mfa_reset_required' => true]);
        $this->assertFalse($policy->allows($user, 'read', $this->scope(), now()->timestamp));
    }

    public function test_verifier_exceptions_deny_without_leaking(): void
    {
        [, $user] = $this->accounts();
        $this->app->instance(ProcessUnitAccess::class, new class extends ProcessUnitAccess
        {
            public function allows(User $user, int|string $unitId): bool
            {
                throw new RuntimeException('sensitive internal details');
            }
        });
        $this->assertFalse(app(ProcessAccessPolicy::class)->allows($user, 'read', $this->scope(), now()->timestamp));
    }

    public function test_invalid_access_configuration_fails_closed(): void
    {
        [, $user] = $this->accounts();
        $this->allowSyntheticScopes();
        config(['access.grantable.processes' => 'invalid']);
        $this->assertFalse(app(ProcessAccessPolicy::class)->allows($user, 'read', $this->scope(), now()->timestamp));
    }
}
