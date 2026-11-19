<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SyntheticFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyntheticFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_an_explicitly_synthetic_unverified_account_idempotently(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('users', [
            'name' => 'Conta Sintética de Teste 001',
            'email' => 'conta.teste.001@example.test',
            'email_verified_at' => null,
        ]);
    }

    public function test_fixture_seeder_rejects_a_remote_database_even_outside_production(): void
    {
        $this->assertFalse(SyntheticFixtureSeeder::isAllowedTarget(
            'local',
            'mysql',
            'db.example.invalid',
            'fokus_law_synthetic',
        ));
    }

    public function test_fixture_seeder_rejects_a_database_url_even_when_other_target_fields_are_local(): void
    {
        $this->assertFalse(SyntheticFixtureSeeder::isAllowedTarget(
            'local',
            'mysql',
            '127.0.0.1',
            'fokus_law_synthetic',
            'mysql://synthetic@example.invalid/fokus_law_synthetic',
        ));
    }

    public function test_fixture_seeder_accepts_the_dedicated_local_synthetic_mysql_target(): void
    {
        $this->assertTrue(SyntheticFixtureSeeder::isAllowedTarget(
            'local',
            'mysql',
            '127.0.0.1',
            'fokus_law_synthetic',
        ));
    }

    public function test_fixture_seeder_rejects_a_production_environment_even_with_the_synthetic_database_name(): void
    {
        $this->assertFalse(SyntheticFixtureSeeder::isAllowedTarget(
            'production',
            'mysql',
            '127.0.0.1',
            'fokus_law_synthetic',
        ));
    }
}
