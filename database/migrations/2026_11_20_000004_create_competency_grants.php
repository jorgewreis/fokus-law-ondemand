<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencies', function (Blueprint $table): void {
            $table->unsignedInteger('code')->primary();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('account_competency_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('competency_code');
            $table->foreign('competency_code')->references('code')->on('competencies')->restrictOnDelete();
            $table->foreignId('granted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'competency_code']);
        });

        Schema::create('competency_grant_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('competency_code');
            $table->foreign('competency_code')->references('code')->on('competencies')->restrictOnDelete();
            $table->string('event', 16);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_grant_events');
        Schema::dropIfExists('account_competency_grants');
        Schema::dropIfExists('competencies');
    }
};
