<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_secrecy_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('court_process_id')->constrained('court_processes')->restrictOnDelete();
            $table->string('operation', 16);
            $table->unsignedTinyInteger('previous_secrecy_code')->nullable();
            $table->foreign('previous_secrecy_code')->references('code')->on('secrecy_catalog')->restrictOnDelete();
            $table->unsignedTinyInteger('new_secrecy_code');
            $table->foreign('new_secrecy_code')->references('code')->on('secrecy_catalog')->restrictOnDelete();
            $table->timestamp('created_at')->index();
            $table->index(['court_process_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_secrecy_events');
    }
};
