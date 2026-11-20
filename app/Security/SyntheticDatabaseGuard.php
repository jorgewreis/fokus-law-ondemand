<?php

namespace App\Security;

use LogicException;

class SyntheticDatabaseGuard
{
    public function assertAllowed(): void
    {
        $environment = app()->environment();
        $connection = config('database.default');
        $databaseConfig = config("database.connections.{$connection}");
        $database = $databaseConfig['database'] ?? null;
        $host = $databaseConfig['host'] ?? null;
        $url = $databaseConfig['url'] ?? null;

        $isIsolatedTestDatabase = $environment === 'testing'
            && $connection === 'sqlite'
            && ($databaseConfig['driver'] ?? null) === 'sqlite'
            && $database === ':memory:'
            && blank($url);

        $isSyntheticComposeDatabase = $environment === 'local'
            && $connection === 'mysql'
            && ($databaseConfig['driver'] ?? null) === 'mysql'
            && $host === 'db'
            && (string) ($databaseConfig['port'] ?? '') === '3306'
            && $database === 'fokus_law_synthetic'
            && ($databaseConfig['username'] ?? null) === 'fokus_law_local'
            && blank($url);

        if (! $isIsolatedTestDatabase && ! $isSyntheticComposeDatabase) {
            throw new LogicException(
                'Synthetic operations require the in-memory test database or the isolated local Compose database.'
            );
        }
    }
}
