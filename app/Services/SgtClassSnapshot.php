<?php

namespace App\Services;

use App\Security\SyntheticDatabaseGuard;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Historical source snapshot only; does not decide whether a class is selectable. */
class SgtClassSnapshot
{
    private const SNAPSHOT_SHA256 = 'ca7a00e843d0e150ea503bf1876b98734d5237daabdac13d706ff4ed4fc6db0d';

    private const SOURCE_SHA256 = '45a1e0750a3cf4ce330e1569d14ae869c356f818a2d2b625e86b9a18975111a5';

    private const SOURCE = 'https://www.cnj.jus.br/sgt/enviarArquivo.php?url=85_dump_dados.sql&nome=dump_dados.sql';

    public function import(): void
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();

        $bytes = @file_get_contents(resource_path('catalogs/cnj-sgt-classes-dump-85.json'));
        if ($bytes === false || ! hash_equals(self::SNAPSHOT_SHA256, hash('sha256', str_replace("\r\n", "\n", $bytes)))) {
            throw new RuntimeException('SGT class snapshot missing or checksum mismatch.');
        }
        $snapshot = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        if (($snapshot['source'] ?? null) !== self::SOURCE || ($snapshot['source_sha256'] ?? null) !== self::SOURCE_SHA256 || count($snapshot['records'] ?? []) !== 842) {
            throw new RuntimeException('Invalid SGT class snapshot metadata.');
        }

        DB::transaction(function () use ($snapshot): void {
            $existing = DB::table('sgt_class_snapshots')->where('source_sha256', self::SOURCE_SHA256)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->source_url !== self::SOURCE) {
                    throw new RuntimeException('Stored SGT snapshot metadata differs from source.');
                }
                $stored = DB::table('sgt_snapshot_classes')->where('snapshot_id', $existing->id)->orderBy('code')->get();
                if ($stored->count() !== count($snapshot['records'])) {
                    throw new RuntimeException('Stored SGT snapshot record count differs from source.');
                }
                foreach ($snapshot['records'] as $index => $record) {
                    $row = $stored[$index];
                    $actual = [
                        'code' => (int) $row->code,
                        'parent_code' => $row->parent_code === null ? null : (int) $row->parent_code,
                        'name' => $row->name,
                        'status' => $row->status,
                        'valid_from' => $row->valid_from,
                        'valid_until' => $row->valid_until,
                        'hierarchy_type' => $row->hierarchy_type,
                        'state_first_degree' => (bool) $row->state_first_degree,
                    ];
                    if ($actual !== $record) {
                        throw new RuntimeException('Stored SGT snapshot differs from source.');
                    }
                }

                return;
            }
            $id = DB::table('sgt_class_snapshots')->insertGetId([
                'source_url' => self::SOURCE,
                'source_sha256' => self::SOURCE_SHA256,
                'imported_at' => now(),
            ]);
            foreach (array_chunk($snapshot['records'], 100) as $chunk) {
                DB::table('sgt_snapshot_classes')->insert(array_map(fn (array $record): array => [
                    'snapshot_id' => $id,
                    ...$record,
                ], $chunk));
            }
        });
    }
}
