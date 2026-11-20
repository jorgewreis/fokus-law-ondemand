<?php

namespace App\Services;

use App\Security\SyntheticDatabaseGuard;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Imports only the reviewed snapshot. No network or inferred catalog updates. */
class CompetencyCatalog
{
    private const SHA256 = 'a88cd93dba01547f520d85a0aa93319f1eb8e82e71730677bc65030ffc6022e1';

    public function importSnapshot(): void
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();

        $records = $this->snapshot();
        DB::transaction(function () use ($records): void {
            foreach ($records as $code => $description) {
                $existing = DB::table('competencies')->where('code', $code)->first();
                if ($existing && $existing->description !== $description) {
                    throw new RuntimeException('Existing competency description differs from snapshot.');
                }
                if (! $existing) {
                    DB::table('competencies')->insert(['code' => $code, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }

    public function descriptionFor(int $code): ?string
    {
        return $this->snapshot()[$code] ?? null;
    }

    /** @return array<int, string> */
    private function snapshot(): array
    {

        $path = resource_path('catalogs/cnj-mpm-competencias-2025-04.csv');
        $bytes = @file_get_contents($path);
        if ($bytes === false || ! hash_equals(self::SHA256, hash('sha256', str_replace("\r\n", "\n", $bytes)))) {
            throw new RuntimeException('Competency snapshot missing or checksum mismatch.');
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $bytes);
        rewind($stream);
        if (fgetcsv($stream, 0, ',', '"', '') !== ['seq_competencia', 'dsc_competencia']) {
            throw new RuntimeException('Invalid competency snapshot header.');
        }
        $records = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            if (count($row) !== 2 || ! ctype_digit($row[0]) || $row[1] === '' || isset($records[(int) $row[0]])) {
                throw new RuntimeException('Invalid competency snapshot row.');
            }
            $records[(int) $row[0]] = $row[1];
        }
        fclose($stream);
        if (count($records) !== 39) {
            throw new RuntimeException('Invalid competency snapshot size.');
        }

        return $records;
    }
}
