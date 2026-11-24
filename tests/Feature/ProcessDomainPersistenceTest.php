<?php

namespace Tests\Feature;

use App\Models\CourtProcess;
use App\Models\Person;
use App\Models\PersonProcess;
use App\Models\User;
use App\Services\CnjNumber;
use App\Services\CourtProcessAccess;
use App\Services\CourtProcessClassification;
use App\Services\ProcessClassCompetencyMap;
use App\Services\ProcessModuleSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcessDomainPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cnj_is_validated_and_stored_as_twenty_digits(): void
    {
        $service = app(CnjNumber::class);

        $this->assertSame('00001005020088270000', $service->normalize('0000100-50.2008.8.27.0000'));
        $this->assertSame('00001005020088270000', $service->normalize('00001005020088270000'));
    }

    public function test_cnj_with_incorrect_check_digits_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(CnjNumber::class)->normalize('0000100-15.2008.8.27.0000');
    }

    public function test_process_persistence_keeps_unit_and_cnj_unique_without_cascading(): void
    {
        $user = User::factory()->create();
        $unitId = '00000000-0000-4000-8000-000000000001';
        DB::table('units')->insert(['id' => $unitId, 'name' => 'Unidade sintética', 'created_at' => now(), 'updated_at' => now()]);

        $process = CourtProcess::query()->create([
            'unit_id' => $unitId,
            'created_by_user_id' => $user->getKey(),
            'cnj_normalized' => '00001005020088270000',
        ]);

        $this->assertSame($unitId, $process->fresh()->unit_id);
        $this->assertNull($process->fresh()->competency_code);
        $this->assertNull($process->fresh()->secrecy_code);

        try {
            CourtProcess::query()->create([
                'unit_id' => $unitId,
                'created_by_user_id' => $user->getKey(),
                'cnj_normalized' => '00001005020088270000',
            ]);
            $this->fail('The same normalized CNJ must be unique within the same Unit.');
        } catch (QueryException) {
            $this->assertDatabaseCount('court_processes', 1);
        }

        $this->expectException(QueryException::class);
        DB::table('units')->where('id', $unitId)->delete();
    }

    public function test_missing_process_scope_denies_access_and_hides_the_row(): void
    {
        $user = User::factory()->create();
        $unitId = '00000000-0000-4000-8000-000000000002';
        DB::table('units')->insert(['id' => $unitId, 'name' => 'Unidade sem grants', 'created_at' => now(), 'updated_at' => now()]);
        $process = CourtProcess::query()->create([
            'unit_id' => $unitId,
            'created_by_user_id' => $user->getKey(),
            'cnj_normalized' => '00001005020088270000',
        ]);

        $access = app(CourtProcessAccess::class);
        $this->assertFalse($access->allows($user, $process->getKey(), 'read', now()->timestamp));
        $this->assertNull($access->find($user, $process->getKey(), 'read', now()->timestamp));
        $this->assertNull($access->find($user, 999999, 'read', now()->timestamp));
    }

    public function test_classless_process_requires_an_explicit_configured_competency(): void
    {
        $this->configureCompetencies([3]);
        $classification = new CourtProcessClassification(app(ProcessModuleSettings::class), $this->classMap([]));

        try {
            DB::transaction(fn () => $classification->resolveCompetencyInCurrentTransaction(null, null));
            $this->fail('A classless Process must have an explicitly selected competence.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('competency_code', $exception->errors());
        }

        $resolved = DB::transaction(fn () => $classification->resolveCompetencyInCurrentTransaction(null, 3));
        $this->assertSame(3, $resolved);
    }

    public function test_competency_999_cannot_be_selected_even_if_bad_configuration_contains_it(): void
    {
        $this->configureCompetencies([999]);
        $classification = new CourtProcessClassification(app(ProcessModuleSettings::class), $this->classMap([]));

        $this->expectException(ValidationException::class);
        DB::transaction(fn () => $classification->resolveCompetencyInCurrentTransaction(null, 999));
    }

    public function test_class_map_auto_selects_one_and_requires_explicit_choice_for_multiple(): void
    {
        $this->configureCompetencies([3, 4]);
        $classification = new CourtProcessClassification(app(ProcessModuleSettings::class), $this->classMap([41 => [3], 42 => [3, 4], 43 => []]));

        $this->assertSame(3, DB::transaction(fn () => $classification->resolveCompetencyInCurrentTransaction(41, null)));
        $this->assertSame(4, DB::transaction(fn () => $classification->resolveCompetencyInCurrentTransaction(42, 4)));

        try {
            DB::transaction(fn () => $classification->resolveCompetencyInCurrentTransaction(42, null));
            $this->fail('A class with several active competencies requires explicit selection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('competency_code', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        DB::transaction(fn () => $classification->resolveCompetencyInCurrentTransaction(43, 3));
    }

    public function test_person_process_role_and_witness_origin_are_one_association(): void
    {
        $user = User::factory()->create();
        $unitId = '00000000-0000-4000-8000-000000000003';
        DB::table('units')->insert(['id' => $unitId, 'name' => 'Unidade do vínculo', 'created_at' => now(), 'updated_at' => now()]);
        $person = $this->person($user);
        $process = CourtProcess::query()->create([
            'unit_id' => $unitId,
            'created_by_user_id' => $user->getKey(),
            'cnj_normalized' => '00001005020088270000',
        ]);

        $link = PersonProcess::query()->create([
            'person_id' => $person->getKey(),
            'court_process_id' => $process->getKey(),
            'created_by_user_id' => $user->getKey(),
            'role' => PersonProcess::ROLE_TESTEMUNHA,
            'witness_origin' => PersonProcess::WITNESS_ORIGIN_AMBAS,
        ]);

        $this->assertSame(PersonProcess::ROLE_TESTEMUNHA, $link->fresh()->role);
        $this->assertSame(PersonProcess::WITNESS_ORIGIN_AMBAS, $link->fresh()->witness_origin);

        $this->expectException(QueryException::class);
        DB::table('people')->where('id', $person->getKey())->delete();
    }

    public function test_witness_requires_origin_and_other_roles_reject_origin(): void
    {
        $user = User::factory()->create();
        $person = $this->person($user);
        $unitId = '00000000-0000-4000-8000-000000000004';
        DB::table('units')->insert(['id' => $unitId, 'name' => 'Unidade de papéis', 'created_at' => now(), 'updated_at' => now()]);
        $process = CourtProcess::query()->create([
            'unit_id' => $unitId,
            'created_by_user_id' => $user->getKey(),
            'cnj_normalized' => '00001005020088270000',
        ]);

        try {
            PersonProcess::query()->create([
                'person_id' => $person->getKey(), 'court_process_id' => $process->getKey(),
                'created_by_user_id' => $user->getKey(), 'role' => PersonProcess::ROLE_TESTEMUNHA,
            ]);
            $this->fail('A testemunha exige a origem Denúncia, Defesa ou ambas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('witness_origin', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        PersonProcess::query()->create([
            'person_id' => $person->getKey(), 'court_process_id' => $process->getKey(),
            'created_by_user_id' => $user->getKey(), 'role' => PersonProcess::ROLE_REU,
            'witness_origin' => PersonProcess::WITNESS_ORIGIN_DENUNCIA,
        ]);
    }

    /** @param list<int> $codes */
    private function configureCompetencies(array $codes): void
    {
        foreach (array_unique([...$codes, 3, 4, 999]) as $code) {
            DB::table('competencies')->insert(['code' => $code, 'description' => "Competência {$code}", 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('process_module_settings')->insert(['id' => 1, 'justice_branch' => 'justica_estadual', 'court_level' => 'primeiro_grau', 'created_at' => now(), 'updated_at' => now()]);
        foreach ($codes as $code) {
            DB::table('process_module_competencies')->insert(['competency_code' => $code, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function person(User $creator): Person
    {
        return Person::query()->create([
            'created_by_user_id' => $creator->getKey(), 'person_type' => 'PF', 'name' => 'Pessoa sintética',
            'postal_code' => '45650000', 'street' => 'Rua de teste', 'address_number' => '1',
            'city' => 'Ilhéus', 'state' => 'BA',
        ]);
    }

    /** @param array<int, list<int>> $mappings */
    private function classMap(array $mappings): ProcessClassCompetencyMap
    {
        return new class($mappings) extends ProcessClassCompetencyMap
        {
            /** @param array<int, list<int>> $mappings */
            public function __construct(private array $mappings) {}

            public function selectableCompetencies(int $classCode): array
            {
                return $this->mappings[$classCode] ?? [];
            }
        };
    }
}
