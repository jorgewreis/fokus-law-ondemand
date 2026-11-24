<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_status_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('court_process_id')->constrained('court_processes')->restrictOnDelete();
            $table->string('previous_status', 32);
            $table->string('new_status', 32);
            $table->timestamp('created_at')->index();
            $table->index(['court_process_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_status_events');
    }
};
