<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sgt_class_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('source_url', 255);
            $table->char('source_sha256', 64)->unique();
            $table->timestamp('imported_at');
        });

        Schema::create('sgt_snapshot_classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('sgt_class_snapshots')->restrictOnDelete();
            $table->unsignedInteger('code');
            $table->unsignedInteger('parent_code')->nullable();
            $table->string('name');
            $table->string('status', 1);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('hierarchy_type', 1)->nullable();
            $table->boolean('state_first_degree');
            $table->unique(['snapshot_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sgt_snapshot_classes');
        Schema::dropIfExists('sgt_class_snapshots');
    }
};
