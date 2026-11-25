<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('matricula', 32)->nullable()->unique()->after('email');
        });

        Schema::table('account_requests', function (Blueprint $table): void {
            $table->string('matricula', 32)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['matricula']);
            $table->dropColumn('matricula');
        });

        Schema::table('account_requests', function (Blueprint $table): void {
            $table->dropColumn('matricula');
        });
    }
};
