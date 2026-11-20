<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_processes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->char('cnj_normalized', 20);
            $table->unsignedBigInteger('class_code')->nullable();
            $table->unsignedInteger('competency_code')->nullable();
            $table->foreign('competency_code')->references('code')->on('competencies')->restrictOnDelete();
            $table->unsignedTinyInteger('secrecy_code')->nullable();
            $table->foreign('secrecy_code')->references('code')->on('secrecy_catalog')->restrictOnDelete();
            $table->string('operational_status', 32)->default('ativo');
            $table->timestamps();

            $table->unique(['unit_id', 'cnj_normalized'], 'court_process_unit_cnj_unique');
            $table->index(['unit_id', 'operational_status'], 'court_process_unit_status_index');
        });

        Schema::create('court_process_subjects', function (Blueprint $table): void {
            $table->foreignId('court_process_id')->constrained('court_processes')->restrictOnDelete();
            $table->unsignedBigInteger('subject_code');
            $table->timestamps();

            $table->primary(['court_process_id', 'subject_code'], 'court_process_subjects_primary');
        });

        Schema::create('person_process', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('court_process_id')->constrained('court_processes')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 24);
            $table->string('witness_origin', 16)->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'court_process_id'], 'person_process_pair_unique');
            $table->index(['court_process_id', 'role'], 'person_process_role_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_process');
        Schema::dropIfExists('court_process_subjects');
        Schema::dropIfExists('court_processes');
    }
};
