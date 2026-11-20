<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(false)->index();
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_enabled_at')->nullable();
            $table->boolean('mfa_reset_required')->default(false);
            $table->unsignedBigInteger('mfa_last_accepted_counter')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'is_active',
                'mfa_secret',
                'mfa_enabled_at',
                'mfa_reset_required',
                'mfa_last_accepted_counter',
            ]);
        });
    }
};
