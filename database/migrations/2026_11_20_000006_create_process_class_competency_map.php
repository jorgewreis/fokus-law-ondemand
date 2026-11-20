<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_class_competencies', function (Blueprint $table): void {
            $table->unsignedInteger('class_code');
            $table->unsignedInteger('competency_code');
            $table->foreign('competency_code')->references('code')->on('competencies')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->primary(['class_code', 'competency_code']);
        });

        Schema::create('process_class_competency_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('class_code');
            $table->unsignedInteger('competency_code');
            $table->foreign('competency_code')->references('code')->on('competencies')->restrictOnDelete();
            $table->string('event', 16);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_class_competency_events');
        Schema::dropIfExists('process_class_competencies');
    }
};
