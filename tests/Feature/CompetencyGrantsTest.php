<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CompetencyCatalog;
use App\Services\CompetencyGrants;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompetencyGrantsTest extends TestCase
{
    use RefreshDatabase;

    private function account(bool $admin = false): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_synthetic_admin' => $admin, 'is_active' => true, 'mfa_enabled_at' => now()])->save();

        return $user;
    }

    public function test_snapshot_is_idempotent_and_grants_require_exact_code(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        app(CompetencyCatalog::class)->importSnapshot();
        $this->assertSame(39, DB::table('competencies')->count());
        $actor = $this->account(true);
        $user = $this->account();
        $service = app(CompetencyGrants::class);
        $this->assertFalse($service->hasExactGrant($user, 3));
        $service->set($actor, $user, 3, true);
        $service->set($actor, $user, 3, true);
        $this->assertTrue($service->hasExactGrant($user, 3));
        $this->assertFalse($service->hasExactGrant($user, 16));
        $this->assertFalse($service->hasExactGrant($user, null));
        $this->assertSame(1, DB::table('competency_grant_events')->count());
        $service->set($actor, $user, 3, false);
        $this->assertFalse($service->hasExactGrant($user, 3));
        $this->assertSame(['granted', 'revoked'], DB::table('competency_grant_events')->orderBy('id')->pluck('event')->all());
    }

    public function test_non_administrator_cannot_grant(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $this->expectException(AuthorizationException::class);
        app(CompetencyGrants::class)->set($this->account(), $this->account(), 3, true);
    }

    public function test_administrator_without_mfa_cannot_grant(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $actor = $this->account(true);
        $actor->forceFill(['mfa_enabled_at' => null])->save();
        $this->expectException(AuthorizationException::class);
        app(CompetencyGrants::class)->set($actor, $this->account(), 3, true);
    }

    public function test_inactive_administrator_cannot_grant(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $actor = $this->account(true);
        $actor->forceFill(['is_active' => false])->save();
        $this->expectException(AuthorizationException::class);
        app(CompetencyGrants::class)->set($actor, $this->account(), 3, true);
    }

    public function test_unknown_code_cannot_be_granted(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $this->expectException(ValidationException::class);
        app(CompetencyGrants::class)->set($this->account(true), $this->account(), 123456, true);
    }

    public function test_extra_database_entry_is_not_grantable_or_authorized(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        DB::table('competencies')->insert(['code' => 123456, 'description' => 'Extra', 'created_at' => now(), 'updated_at' => now()]);
        $actor = $this->account(true);
        $user = $this->account();
        try {
            app(CompetencyGrants::class)->set($actor, $user, 123456, true);
            $this->fail('Extra code must be rejected.');
        } catch (ValidationException) {
            $this->assertSame(0, DB::table('account_competency_grants')->count());
        }
        DB::table('account_competency_grants')->insert(['user_id' => $user->id, 'competency_code' => 123456, 'granted_by_user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertFalse(app(CompetencyGrants::class)->hasExactGrant($user, 123456));
    }

    public function test_grant_check_reloads_user_state(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $actor = $this->account(true);
        $user = $this->account();
        $service = app(CompetencyGrants::class);
        $service->set($actor, $user, 3, true);
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $this->assertFalse($service->hasExactGrant($user, 3));
        DB::table('users')->where('id', $user->id)->update(['is_active' => true, 'mfa_enabled_at' => null]);
        $this->assertFalse($service->hasExactGrant($user, 3));
    }

    public function test_revocation_remains_possible_if_catalog_description_changes(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $actor = $this->account(true);
        $user = $this->account();
        $service = app(CompetencyGrants::class);
        $service->set($actor, $user, 3, true);
        DB::table('competencies')->where('code', 3)->update(['description' => 'Alterada']);
        $service->set($actor, $user, 3, false);
        $this->assertSame(0, DB::table('account_competency_grants')->count());
        $this->assertSame(['granted', 'revoked'], DB::table('competency_grant_events')->orderBy('id')->pluck('event')->all());
    }

    public function test_unavailable_code_cannot_be_granted(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $this->expectException(ValidationException::class);
        app(CompetencyGrants::class)->set($this->account(true), $this->account(), 999, true);
    }

    public function test_rejects_non_synthetic_database_before_import_or_grant(): void
    {
        $actor = $this->account(true);
        $user = $this->account();
        config(['database.connections.sqlite.url' => 'sqlite:///not-synthetic']);
        foreach ([fn () => app(CompetencyCatalog::class)->importSnapshot(), fn () => app(CompetencyGrants::class)->set($actor, $user, 3, true)] as $operation) {
            try {
                $operation();
                $this->fail('Expected synthetic database guard to reject the operation.');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('Synthetic operations require', $e->getMessage());
            }
        }
    }

    public function test_audit_failure_rolls_back_grant(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $actor = $this->account(true);
        $user = $this->account();
        DB::statement("CREATE TRIGGER reject_competency_event BEFORE INSERT ON competency_grant_events BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
        try {
            app(CompetencyGrants::class)->set($actor, $user, 3, true);
            $this->fail('Expected audit failure');
        } catch (QueryException) {
            $this->assertSame(0, DB::table('account_competency_grants')->count());
        }
    }
}
