<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CompetencyCatalog;
use App\Services\ProcessClassCompetencyMap;
use App\Services\ProcessModuleSettings;
use App\Services\VerifiedSgtClassCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompetencyRetirementRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function arrangeMapping(): array
    {
        app(CompetencyCatalog::class)->importSnapshot();
        $actor = User::factory()->create();
        $actor->forceFill(['is_synthetic_admin' => true, 'is_active' => true, 'mfa_enabled_at' => now()])->save();
        $catalog = new class extends VerifiedSgtClassCatalog
        {
            public function isSelectable(int $code, string $justiceBranch, string $courtLevel): bool
            {
                return $code === 12345 && $justiceBranch === 'justica_estadual' && $courtLevel === 'primeiro_grau';
            }
        };
        $this->app->instance(VerifiedSgtClassCatalog::class, $catalog);
        $settings = app(ProcessModuleSettings::class);
        $map = app(ProcessClassCompetencyMap::class);
        $settings->configure($actor, 'justica_estadual', 'primeiro_grau', [3, 16], 0);
        $map->set($actor, 12345, 3, true, 1);

        return [$actor, $settings, $map];
    }

    public function test_retirement_deactivates_mapping_and_reactivation_requires_new_explicit_action(): void
    {
        [$actor, $settings, $map] = $this->arrangeMapping();
        $settings->configure($actor, 'justica_estadual', 'primeiro_grau', [16], 2);
        $this->assertDatabaseHas('process_class_competencies', ['class_code' => 12345, 'competency_code' => 3, 'active' => false]);
        $this->assertDatabaseHas('process_class_competency_events', ['actor_user_id' => $actor->id, 'class_code' => 12345, 'competency_code' => 3, 'event' => 'deactivated']);
        $settings->configure($actor, 'justica_estadual', 'primeiro_grau', [3, 16], 3);
        $this->assertSame([], $map->selectableCompetencies(12345));
        $map->set($actor, 12345, 3, true, 4);
        $this->assertSame([3], $map->selectableCompetencies(12345));
        $this->assertSame(['activated', 'deactivated', 'activated'], DB::table('process_class_competency_events')->orderBy('id')->pluck('event')->all());
    }

    public function test_retirement_rolls_back_if_mapping_audit_fails(): void
    {
        [$actor, $settings, $map] = $this->arrangeMapping();
        DB::statement("CREATE TRIGGER reject_retirement_event BEFORE INSERT ON process_class_competency_events BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
        try {
            $settings->configure($actor, 'justica_estadual', 'primeiro_grau', [16], 2);
            $this->fail('Expected audit failure');
        } catch (QueryException) {
            $this->assertSame([3, 16], $settings->current()['competencies']);
            $this->assertSame([3], $map->selectableCompetencies(12345));
            $this->assertSame(2, $settings->snapshot()['revision']);
            $this->assertSame(1, DB::table('process_class_competency_events')->count());
        }
    }
}
