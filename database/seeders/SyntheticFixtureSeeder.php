<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class SyntheticFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");
        $url = config("database.connections.{$connection}.url");

        if (! self::isAllowedTarget(
            app()->environment(),
            $driver,
            config("database.connections.{$connection}.host"),
            $database,
            $url,
        )) {
            throw new RuntimeException(
                'Synthetic fixtures may only be seeded into the in-memory test database or the dedicated local fokus_law_synthetic database.'
            );
        }

        User::query()->firstOrCreate(
            ['email' => 'conta.teste.001@example.test'],
            [
                'name' => 'Conta Sintética de Teste 001',
                'password' => Str::random(64),
            ],
        );
    }

    public static function isAllowedTarget(
        string $environment,
        string $driver,
        ?string $host,
        ?string $database,
        ?string $url = null,
    ): bool {
        if ($url !== null && trim($url) !== '') {
            return false;
        }

        $inMemoryTestDatabase = $environment === 'testing'
            && $driver === 'sqlite'
            && $database === ':memory:';

        $dedicatedLocalSyntheticDatabase = in_array($environment, ['local', 'development'], true)
            && $driver === 'mysql'
            && in_array($host, ['127.0.0.1', 'localhost'], true)
            && $database === 'fokus_law_synthetic';

        return $inMemoryTestDatabase || $dedicatedLocalSyntheticDatabase;
    }
}
