<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_module_setting_locks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
        });
        DB::table('process_module_setting_locks')->insert(['id' => 1]);

        Schema::create('process_module_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('justice_branch', 100);
            $table->string('court_level', 100);
            $table->timestamps();
        });

        Schema::create('process_module_competencies', function (Blueprint $table): void {
            $table->unsignedInteger('competency_code')->primary();
            $table->foreign('competency_code')->references('code')->on('competencies')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('process_module_setting_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->json('before_state')->nullable();
            $table->json('after_state');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_module_setting_events');
        Schema::dropIfExists('process_module_competencies');
        Schema::dropIfExists('process_module_settings');
        Schema::dropIfExists('process_module_setting_locks');
    }
};
