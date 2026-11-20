<?php

namespace Tests\Feature;

use App\Models\CourtProcess;
use App\Models\Person;
use App\Models\User;
use App\Services\CompetencyCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourtProcessAuthorizationHttpTest extends TestCase
{
    use RefreshDatabase;

    private const UNIT_ID = '00000000-0000-4000-8000-000000000101';

    public function test_process_creation_requires_unit_competency_and_explicit_competency_without_class(): void
    {
        $user = $this->authorizedCreator();

        $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->get(route('processes.create'))
            ->assertOk()
            ->assertSee('Competência da Vara');

        $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->post(route('processes.store'), ['cnj' => '0000100-50.2008.8.27.0000'])
            ->assertSessionHasErrors('competency_code');
        $this->assertDatabaseCount('court_processes', 0);

        $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->post(route('processes.store'), [
                'cnj' => '0000100-50.2008.8.27.0000',
                'competency_code' => 3,
            ])
            ->assertRedirect(route('processes.create'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('court_processes', [
            'cnj_normalized' => '00001005020088270000',
            'class_code' => null,
            'competency_code' => 3,
            'secrecy_code' => null,
            'unit_id' => self::UNIT_ID,
        ]);
    }

    public function test_process_creation_denies_user_without_explicit_unit_grant(): void
    {
        $user = $this->authorizedCreator(withUnitGrant: false);

        $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->post(route('processes.store'), [
                'cnj' => '0000100-50.2008.8.27.0000',
                'competency_code' => 3,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('court_processes', 0);
    }

    public function test_process_creation_can_link_an_authorized_person_with_witness_origin(): void
    {
        $user = $this->authorizedCreator();
        DB::table('account_grants')->insert([
            'user_id' => $user->getKey(), 'module' => 'people', 'action' => 'read',
            'granted_by_user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $person = Person::query()->create([
            'created_by_user_id' => $user->getKey(), 'person_type' => 'PF', 'name' => 'Pessoa sintética',
            'postal_code' => '45653240', 'street' => 'Rua de teste', 'address_number' => '1', 'city' => 'Ilhéus', 'state' => 'BA',
        ]);

        $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->post(route('processes.store'), [
                'cnj' => '0000100-50.2008.8.27.0000',
                'competency_code' => 3,
                'person_id' => $person->getKey(),
                'role' => 'testemunha',
                'witness_origin' => 'ambas',
            ])
            ->assertRedirect(route('processes.create'));

        $this->assertDatabaseHas('person_process', [
            'person_id' => $person->getKey(),
            'role' => 'testemunha',
            'witness_origin' => 'ambas',
        ]);
    }

    public function test_process_creation_rejects_unreadable_person_without_leaving_a_process(): void
    {
        $user = $this->authorizedCreator();
        $person = Person::query()->create([
            'created_by_user_id' => User::factory()->create()->getKey(), 'person_type' => 'PF', 'name' => 'Pessoa fora do escopo',
            'postal_code' => '45653240', 'street' => 'Rua de teste', 'address_number' => '1', 'city' => 'Ilhéus', 'state' => 'BA',
        ]);

        $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->post(route('processes.store'), [
                'cnj' => '0000100-50.2008.8.27.0000',
                'competency_code' => 3,
                'person_id' => $person->getKey(),
                'role' => 'reu',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('court_processes', 0);
    }

    public function test_process_creation_requires_an_origin_for_a_witness_link(): void
    {
        $user = $this->authorizedCreator();
        DB::table('account_grants')->insert([
            'user_id' => $user->getKey(), 'module' => 'people', 'action' => 'read',
            'granted_by_user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $person = Person::query()->create([
            'created_by_user_id' => $user->getKey(), 'person_type' => 'PF', 'name' => 'Pessoa sintética',
            'postal_code' => '45653240', 'street' => 'Rua de teste', 'address_number' => '1', 'city' => 'Ilhéus', 'state' => 'BA',
        ]);

        $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->post(route('processes.store'), [
                'cnj' => '0000100-50.2008.8.27.0000',
                'competency_code' => 3,
                'person_id' => $person->getKey(),
                'role' => 'testemunha',
            ])
            ->assertSessionHasErrors('witness_origin');
        $this->assertDatabaseCount('court_processes', 0);
    }

    public function test_only_synthetic_admin_can_classify_and_classification_does_not_grant_process_read(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
            'is_synthetic_admin' => true,
            'mfa_enabled_at' => now()->subMinute(),
        ]);
        $process = $this->unclassifiedProcess($admin);
        $session = ['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp];

        $this->actingAs($admin)->withSession($session)
            ->get(route('process-secrecy-admin.index'))
            ->assertOk()
            ->assertSee((string) $process->getKey())
            ->assertDontSee('00001005020088270000');

        $this->actingAs($admin)->withSession($session)
            ->put(route('process-secrecy-admin.update', $process->getKey()), ['secrecy_code' => 2])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('court_processes', ['id' => $process->getKey(), 'secrecy_code' => 2]);
        $this->assertDatabaseHas('process_secrecy_events', [
            'actor_user_id' => $admin->getKey(),
            'court_process_id' => $process->getKey(),
            'operation' => 'classified',
            'previous_secrecy_code' => null,
            'new_secrecy_code' => 2,
        ]);

        $this->actingAs($admin)->withSession($session)
            ->get(route('processes.show', $process->getKey()))
            ->assertNotFound();
    }

    public function test_account_without_synthetic_admin_authority_cannot_classify_process(): void
    {
        $user = $this->authorizedCreator();
        $process = $this->unclassifiedProcess($user);

        $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->put(route('process-secrecy-admin.update', $process->getKey()), ['secrecy_code' => 2])
            ->assertForbidden();

        $this->assertDatabaseHas('court_processes', ['id' => $process->getKey(), 'secrecy_code' => null]);
        $this->assertDatabaseMissing('process_secrecy_events', ['court_process_id' => $process->getKey()]);
    }

    public function test_reclassification_audit_records_old_and_new_codes_without_cnj(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'is_synthetic_admin' => true, 'mfa_enabled_at' => now()->subMinute()]);
        $process = $this->unclassifiedProcess($admin);
        $process->secrecy_code = 1;
        $process->save();

        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->put(route('process-secrecy-admin.update', $process->getKey()), ['secrecy_code' => 4])
            ->assertRedirect();

        $this->assertDatabaseHas('process_secrecy_events', [
            'court_process_id' => $process->getKey(),
            'operation' => 'reclassified',
            'previous_secrecy_code' => 1,
            'new_secrecy_code' => 4,
        ]);
        $this->assertDatabaseMissing('process_secrecy_events', ['cnj_normalized' => '00001005020088270000']);
    }

    public function test_read_requires_every_explicit_scope_grant_and_unclassified_process_is_not_found(): void
    {
        $creator = User::factory()->create(['is_active' => true, 'mfa_enabled_at' => now()->subMinute()]);
        $process = $this->unclassifiedProcess($creator);

        $this->actingAs($creator)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->get(route('processes.show', $process->getKey()))
            ->assertNotFound();

        $this->assertDatabaseHas('court_processes', ['id' => $process->getKey(), 'secrecy_code' => null]);
    }

    public function test_authorized_account_can_read_only_when_all_exact_scope_grants_match(): void
    {
        $user = $this->authorizedCreator();
        DB::table('account_grants')->insert(['user_id' => $user->getKey(), 'module' => 'processes', 'action' => 'read', 'granted_by_user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('account_secrecy_grants')->insert(['user_id' => $user->getKey(), 'secrecy_code' => 2, 'granted_by_user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now()]);
        $process = CourtProcess::query()->create([
            'unit_id' => self::UNIT_ID,
            'created_by_user_id' => $user->getKey(),
            'cnj_normalized' => '00001005020088270000',
            'competency_code' => 3,
            'secrecy_code' => 2,
        ]);

        $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->get(route('processes.show', $process->getKey()))
            ->assertOk()
            ->assertSee('00001005020088270000');

        DB::table('account_secrecy_grants')->where('user_id', $user->getKey())->where('secrecy_code', 2)->delete();
        $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->get(route('processes.show', $process->getKey()))
            ->assertNotFound();
    }

    public function test_process_status_transition_is_authorized_and_audited_with_before_and_after(): void
    {
        $user = $this->authorizedCreator();
        DB::table('account_grants')->insert([
            'user_id' => $user->getKey(), 'module' => 'processes', 'action' => 'read',
            'granted_by_user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('account_grants')->insert([
            'user_id' => $user->getKey(), 'module' => 'processes', 'action' => 'update',
            'granted_by_user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('account_grants')->insert([
            'user_id' => $user->getKey(), 'module' => 'processes', 'action' => 'archive',
            'granted_by_user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('account_secrecy_grants')->insert([
            'user_id' => $user->getKey(), 'secrecy_code' => 2, 'granted_by_user_id' => $user->getKey(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $process = CourtProcess::query()->create([
            'unit_id' => self::UNIT_ID,
            'created_by_user_id' => $user->getKey(),
            'cnj_normalized' => '00001005020088270000',
            'competency_code' => 3,
            'secrecy_code' => 2,
        ]);

        $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->put(route('processes.status.update', $process->getKey()), ['operational_status' => 'arquivado_definitivamente'])
            ->assertRedirect(route('processes.show', $process->getKey()));

        $this->assertDatabaseHas('process_status_events', [
            'actor_user_id' => $user->getKey(),
            'court_process_id' => $process->getKey(),
            'previous_status' => 'ativo',
            'new_status' => 'arquivado_definitivamente',
        ]);
    }

    private function authorizedCreator(bool $withUnitGrant = true): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'mfa_enabled_at' => now()->subMinute(),
            'mfa_reset_required' => false,
        ]);
        app(CompetencyCatalog::class)->importSnapshot();
        $description = app(CompetencyCatalog::class)->descriptionFor(3);
        DB::table('process_module_settings')->insert(['id' => 1, 'justice_branch' => 'justica_estadual', 'court_level' => 'primeiro_grau', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('process_module_competencies')->insert(['competency_code' => 3, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('units')->insert(['id' => self::UNIT_ID, 'name' => 'Unidade sintética', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('process_module_unit_settings')->where('id', 1)->update(['unit_id' => self::UNIT_ID]);
        DB::table('account_grants')->insert(['user_id' => $user->getKey(), 'module' => 'processes', 'action' => 'create', 'granted_by_user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('account_competency_grants')->insert(['user_id' => $user->getKey(), 'competency_code' => 3, 'granted_by_user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now()]);
        if ($description !== null) {
            DB::table('competencies')->where('code', 3)->update(['description' => $description]);
        }
        if ($withUnitGrant) {
            DB::table('account_unit_grants')->insert(['user_id' => $user->getKey(), 'unit_id' => self::UNIT_ID, 'granted_by_user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return $user;
    }

    private function unclassifiedProcess(User $creator): CourtProcess
    {
        if (! DB::table('units')->where('id', self::UNIT_ID)->exists()) {
            DB::table('units')->insert(['id' => self::UNIT_ID, 'name' => 'Unidade sintética', 'created_at' => now(), 'updated_at' => now()]);
        }

        return CourtProcess::query()->create([
            'unit_id' => self::UNIT_ID,
            'created_by_user_id' => $creator->getKey(),
            'cnj_normalized' => '00001005020088270000',
            'competency_code' => null,
            'secrecy_code' => null,
        ]);
    }
}
