<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CompetencyCatalog;
use App\Services\ProcessClassCompetencyMap;
use App\Services\ProcessModuleSettings;
use App\Services\VerifiedSgtClassCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcessClassCompetencyMapTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $admin = false): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_synthetic_admin' => $admin, 'is_active' => true, 'mfa_enabled_at' => now()])->save();

        return $user;
    }

    private function configure(User $actor): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        app(ProcessModuleSettings::class)->configure($actor, 'justica_estadual', 'primeiro_grau', [3, 16], 0);
    }

    private function simulatedClassCatalog(bool $allowed): void
    {
        $catalog = new class($allowed) extends VerifiedSgtClassCatalog
        {
            public function __construct(private bool $allowed) {}

            public function isSelectable(int $code, string $justiceBranch, string $courtLevel): bool
            {
                return $this->allowed && $code === 12345 && $justiceBranch === 'justica_estadual' && $courtLevel === 'primeiro_grau';
            }
        };
        $this->app->instance(VerifiedSgtClassCatalog::class, $catalog);
    }

    public function test_unverified_catalog_fails_closed_without_writing(): void
    {
        $actor = $this->user(true);
        $this->configure($actor);
        $service = app(ProcessClassCompetencyMap::class);
        try {
            $service->set($actor, 12345, 3, true, 1);
            $this->fail('Expected unverified class rejection');
        } catch (ValidationException) {
            $this->assertSame(0, DB::table('process_class_competencies')->count());
            $this->assertSame(0, DB::table('process_class_competency_events')->count());
            $this->assertSame(1, app(ProcessModuleSettings::class)->snapshot()['revision']);
            $this->assertSame([], $service->selectableCompetencies(12345));
        }
    }

    public function test_simulated_verified_class_mapping_is_audited_and_retained_on_removal(): void
    {
        $actor = $this->user(true);
        $this->configure($actor);
        $this->simulatedClassCatalog(true);
        $service = app(ProcessClassCompetencyMap::class);
        $service->set($actor, 12345, 3, true, 1);
        $service->set($actor, 12345, 16, true, 2);
        $this->assertSame([3, 16], $service->selectableCompetencies(12345));
        app(ProcessModuleSettings::class)->configure($actor, 'justica_estadual', 'primeiro_grau', [16], 3);
        $this->assertSame([16], $service->selectableCompetencies(12345));
        $this->simulatedClassCatalog(false);
        $service = app(ProcessClassCompetencyMap::class);
        $service->set($actor, 12345, 3, false, 4);
        $this->assertSame(0, (int) DB::table('process_class_competencies')->where('competency_code', 3)->value('active'));
        $this->assertSame(['activated', 'activated', 'deactivated'], DB::table('process_class_competency_events')->orderBy('id')->pluck('event')->all());
        $this->assertSame([], $service->selectableCompetencies(12345));
        $this->assertSame(4, app(ProcessModuleSettings::class)->snapshot()['revision']);
    }

    public function test_stale_write_and_audit_failure_are_atomic(): void
    {
        $actor = $this->user(true);
        $this->configure($actor);
        $this->simulatedClassCatalog(true);
        $service = app(ProcessClassCompetencyMap::class);
        $service->set($actor, 12345, 3, true, 1);
        try {
            $service->set($actor, 12345, 16, true, 1);
            $this->fail('Expected stale revision');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('revision', $exception->errors());
        }
        DB::statement("CREATE TRIGGER reject_mapping_event BEFORE INSERT ON process_class_competency_events BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
        try {
            $service->set($actor, 12345, 16, true, 2);
            $this->fail('Expected audit failure');
        } catch (QueryException) {
            $this->assertSame([3], $service->selectableCompetencies(12345));
            $this->assertSame(1, DB::table('process_class_competency_events')->count());
            $this->assertSame(2, app(ProcessModuleSettings::class)->snapshot()['revision']);
        }
    }

    public function test_non_admin_cannot_modify_mapping(): void
    {
        $actor = $this->user(true);
        $this->configure($actor);
        $this->simulatedClassCatalog(true);
        $this->expectException(AuthorizationException::class);
        app(ProcessClassCompetencyMap::class)->set($this->user(), 12345, 3, true, 1);
    }
}
