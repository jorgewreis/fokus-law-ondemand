<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CompetencyCatalog;
use App\Services\ProcessModuleSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcessModuleSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $admin = false): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_synthetic_admin' => $admin, 'is_active' => true, 'mfa_enabled_at' => now()])->save();

        return $user;
    }

    public function test_configuration_is_audited_idempotent_and_retains_removed_competencies(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $service = app(ProcessModuleSettings::class);
        $admin = $this->user(true);
        $this->assertSame(['revision' => 0, 'settings' => null], $service->snapshot());
        $service->configure($admin, 'justica_estadual', 'primeiro_grau', [16, 3], 0);
        $service->configure($admin, 'justica_estadual', 'primeiro_grau', [3, 16], 1);
        $this->assertSame(1, DB::table('process_module_setting_events')->count());
        $service->configure($admin, 'justica_estadual', 'primeiro_grau', [16], 1);
        $this->assertSame([16], $service->current()['competencies']);
        $this->assertSame(0, (int) DB::table('process_module_competencies')->where('competency_code', 3)->value('active'));
        $event = DB::table('process_module_setting_events')->orderByDesc('id')->first();
        $this->assertSame([3, 16], json_decode($event->before_state, true)['competencies']);
        $this->assertSame([16], json_decode($event->after_state, true)['competencies']);
        $service->configure($admin, 'justica_estadual', 'primeiro_grau', [3, 16], 2);
        $this->assertSame([3, 16], $service->current()['competencies']);
        $this->assertSame(3, $service->snapshot()['revision']);
    }

    public function test_non_admin_cannot_configure(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $this->expectException(AuthorizationException::class);
        app(ProcessModuleSettings::class)->configure($this->user(), 'justica_estadual', 'primeiro_grau', [3], 0);
    }

    public function test_current_reads_settings_and_competencies_in_one_query(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $service = app(ProcessModuleSettings::class);
        $service->configure($this->user(true), 'justica_estadual', 'primeiro_grau', [3, 16], 0);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->assertSame([3, 16], $service->current()['competencies']);
        $this->assertCount(1, $queries);
    }

    public function test_revoked_mfa_is_read_from_database_not_stale_actor(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $actor = $this->user(true);
        User::query()->whereKey($actor->getKey())->update(['mfa_enabled_at' => null]);
        $this->expectException(AuthorizationException::class);
        app(ProcessModuleSettings::class)->configure($actor, 'justica_estadual', 'primeiro_grau', [3], 0);
    }

    public function test_remote_database_configuration_is_rejected_before_mutation(): void
    {
        $actor = $this->user(true);
        config(['database.connections.sqlite.database' => 'unsafe.sqlite']);
        try {
            app(ProcessModuleSettings::class)->configure($actor, 'justica_estadual', 'primeiro_grau', [3], 0);
            $this->fail('Expected guard rejection');
        } catch (\LogicException) {
            $this->assertSame(0, DB::table('process_module_settings')->count());
        }
    }

    public function test_invalid_catalog_entry_and_outside_scope_are_rejected(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $service = app(ProcessModuleSettings::class);
        $actor = $this->user(true);
        foreach ([['justica_federal', 'primeiro_grau', [3]], ['justica_estadual', 'segundo_grau', [3]], ['justica_estadual', 'primeiro_grau', [999]], ['justica_estadual', 'primeiro_grau', []], ['justica_estadual', 'primeiro_grau', [8000]]] as [$branch, $level, $codes]) {
            try {
                $service->configure($actor, $branch, $level, $codes, 0);
                $this->fail('Expected validation error');
            } catch (ValidationException) {
                $this->assertNull($service->current());
            }
        }
    }

    public function test_audit_failure_rolls_back_configuration(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $service = app(ProcessModuleSettings::class);
        $actor = $this->user(true);
        $service->configure($actor, 'justica_estadual', 'primeiro_grau', [3], 0);
        DB::statement("CREATE TRIGGER reject_setting_event BEFORE INSERT ON process_module_setting_events BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
        try {
            $service->configure($actor, 'justica_estadual', 'primeiro_grau', [16], 1);
            $this->fail('Expected audit failure');
        } catch (QueryException) {
            $this->assertSame([3], $service->current()['competencies']);
            $this->assertSame(1, DB::table('process_module_setting_events')->count());
            $this->assertSame(1, $service->snapshot()['revision']);
        }
    }

    public function test_stale_revision_rejects_write_without_audit_or_changes(): void
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $service = app(ProcessModuleSettings::class);
        $actor = $this->user(true);
        $revision = $service->snapshot()['revision'];
        $service->configure($actor, 'justica_estadual', 'primeiro_grau', [3], $revision);
        try {
            $service->configure($actor, 'justica_estadual', 'primeiro_grau', [16], $revision);
            $this->fail('Expected stale revision rejection');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('revision', $exception->errors());
            $this->assertSame([3], $service->current()['competencies']);
            $this->assertSame(1, $service->snapshot()['revision']);
            $this->assertSame(1, DB::table('process_module_setting_events')->count());
        }
    }
}
