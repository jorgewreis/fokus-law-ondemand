<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_activation_invites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 64);
            $table->string('action', 64);
            $table->foreignId('granted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'module', 'action']);
            $table->index(['module', 'action']);
        });

        Schema::create('mfa_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 255)->unique();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('mfa_recovery_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('requested_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 96);
            $table->string('subject_type', 48)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('module', 64)->nullable();
            $table->string('action', 64)->nullable();
            $table->string('outcome', 24);
            $table->timestamp('created_at')->index();
            $table->index(['event', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_audit_events');
        Schema::dropIfExists('mfa_recovery_requests');
        Schema::dropIfExists('mfa_recovery_codes');
        Schema::dropIfExists('account_grants');
        Schema::dropIfExists('account_activation_invites');
    }
};
