<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('process_module_unit_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->uuid('unit_id')->nullable();
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamps();
        });
        DB::table('process_module_unit_settings')->insert(['id' => 1, 'unit_id' => null, 'revision' => 0]);

        Schema::create('process_module_unit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('before_unit_id')->nullable();
            $table->foreign('before_unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->uuid('after_unit_id')->nullable();
            $table->foreign('after_unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->timestamp('created_at')->index();
        });

        Schema::create('account_unit_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreignId('granted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'unit_id']);
        });

        Schema::create('unit_grant_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->string('event', 16);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_grant_events');
        Schema::dropIfExists('account_unit_grants');
        Schema::dropIfExists('process_module_unit_events');
        Schema::dropIfExists('process_module_unit_settings');
        Schema::dropIfExists('units');
    }
};
