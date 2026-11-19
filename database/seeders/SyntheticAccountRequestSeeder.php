<?php

namespace Database\Seeders;

use App\AccountRequestStatus;
use App\Models\AccountRequest;
use Illuminate\Database\Seeder;
use LogicException;

class SyntheticAccountRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $environment = app()->environment();
        $connection = config('database.default');
        $databaseConfig = config("database.connections.{$connection}");
        $database = $databaseConfig['database'] ?? null;
        $host = $databaseConfig['host'] ?? null;
        $url = $databaseConfig['url'] ?? null;

        $isIsolatedTestDatabase = $environment === 'testing'
            && $connection === 'sqlite'
            && $database === ':memory:'
            && blank($url);

        $isSyntheticComposeDatabase = $environment === 'local'
            && $connection === 'mysql'
            && $host === 'db'
            && ($databaseConfig['port'] ?? null) === '3306'
            && $database === 'fokus_law_synthetic'
            && ($databaseConfig['username'] ?? null) === 'fokus_law_local'
            && blank($url);

        if (! $isIsolatedTestDatabase && ! $isSyntheticComposeDatabase) {
            throw new LogicException(
                'Synthetic fixtures require the in-memory test database or the isolated local Compose database.'
            );
        }

        AccountRequest::query()->firstOrCreate(
            ['email' => 'solicitacao.sintetica@fokuslaw.test'],
            [
                'display_name' => '[SINTÉTICO] Solicitação de conta pendente',
                'status' => AccountRequestStatus::PendingEmailConfirmation,
                'email_confirmed_at' => null,
                'submitted_at' => now(),
                'expires_at' => now()->addDays(7),
            ],
        );
    }
}
