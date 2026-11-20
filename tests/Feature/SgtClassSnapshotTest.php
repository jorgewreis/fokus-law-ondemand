<?php

namespace Tests\Feature;

use App\Services\SgtClassSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class SgtClassSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_idempotent_and_preserves_source_flags(): void
    {
        app(SgtClassSnapshot::class)->import();
        app(SgtClassSnapshot::class)->import();

        $this->assertSame(1, DB::table('sgt_class_snapshots')->count());
        $this->assertSame(842, DB::table('sgt_snapshot_classes')->count());
        $this->assertSame(404, DB::table('sgt_snapshot_classes')->where('state_first_degree', true)->count());
        $class = DB::table('sgt_snapshot_classes')->where('code', 7)->first();
        $this->assertSame('Procedimento Comum Cível', $class->name);
        $this->assertSame('A', $class->status);
        $this->assertNull($class->hierarchy_type);
    }

    public function test_remote_database_configuration_is_rejected_before_import(): void
    {
        config(['database.connections.sqlite.url' => 'sqlite:///remote']);
        $this->expectException(LogicException::class);
        app(SgtClassSnapshot::class)->import();
    }

    public function test_reimport_rejects_altered_record_without_overwriting_it(): void
    {
        app(SgtClassSnapshot::class)->import();
        DB::table('sgt_snapshot_classes')->where('code', 7)->update(['name' => 'ALTERADO']);

        try {
            app(SgtClassSnapshot::class)->import();
            $this->fail('A fotografia adulterada deve ser recusada.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Stored SGT snapshot differs from source.', $exception->getMessage());
            $this->assertSame('ALTERADO', DB::table('sgt_snapshot_classes')->where('code', 7)->value('name'));
        }
    }

    public function test_reimport_rejects_missing_record_without_restoring_it(): void
    {
        app(SgtClassSnapshot::class)->import();
        DB::table('sgt_snapshot_classes')->where('code', 7)->delete();

        try {
            app(SgtClassSnapshot::class)->import();
            $this->fail('A fotografia incompleta deve ser recusada.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Stored SGT snapshot record count differs from source.', $exception->getMessage());
            $this->assertSame(841, DB::table('sgt_snapshot_classes')->count());
        }
    }
}
