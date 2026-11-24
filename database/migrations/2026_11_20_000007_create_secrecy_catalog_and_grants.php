<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secrecy_catalog', function (Blueprint $table): void {
            $table->unsignedTinyInteger('code')->primary();
            $table->string('label', 40)->unique();
        });
        DB::table('secrecy_catalog')->insert(collect(range(0, 5))->map(fn (int $code): array => [
            'code' => $code,
            'label' => "CNJ DataJud {$code}",
        ])->all());

        Schema::create('account_secrecy_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('secrecy_code');
            $table->foreign('secrecy_code')->references('code')->on('secrecy_catalog')->restrictOnDelete();
            $table->foreignId('granted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'secrecy_code']);
        });

        Schema::create('secrecy_grant_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('secrecy_code');
            $table->foreign('secrecy_code')->references('code')->on('secrecy_catalog')->restrictOnDelete();
            $table->string('event', 16);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secrecy_grant_events');
        Schema::dropIfExists('account_secrecy_grants');
        Schema::dropIfExists('secrecy_catalog');
    }
};
