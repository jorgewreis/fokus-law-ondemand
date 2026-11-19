<?php

namespace Tests\Feature;

use Database\Seeders\SyntheticAccountRequestSeeder;
use LogicException;
use Tests\TestCase;

class SyntheticAccountRequestSeederSafetyTest extends TestCase
{
    public function test_it_rejects_a_non_synthetic_database_before_connecting(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.host' => '203.0.113.10',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'production',
            'database.connections.mysql.username' => 'production_user',
            'database.connections.mysql.url' => null,
        ]);

        $this->expectException(LogicException::class);

        (new SyntheticAccountRequestSeeder)->run();
    }

    public function test_it_rejects_production_even_with_local_compose_database_settings(): void
    {
        $this->app->instance('env', 'production');

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.host' => 'db',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'fokus_law_synthetic',
            'database.connections.mysql.username' => 'fokus_law_local',
            'database.connections.mysql.url' => null,
        ]);

        $this->expectException(LogicException::class);

        (new SyntheticAccountRequestSeeder)->run();
    }

    public function test_it_rejects_a_connection_url_even_with_local_compose_settings(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.host' => 'db',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'fokus_law_synthetic',
            'database.connections.mysql.username' => 'fokus_law_local',
            'database.connections.mysql.url' => 'mysql://example.invalid/not-synthetic',
        ]);

        $this->expectException(LogicException::class);

        (new SyntheticAccountRequestSeeder)->run();
    }
}
