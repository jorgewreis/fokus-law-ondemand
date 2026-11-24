<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('person_type', 2);
            $table->string('name', 255)->index();
            $table->string('postal_code', 8);
            $table->string('street', 255);
            $table->string('address_number', 64);
            $table->string('city', 160);
            $table->char('state', 2);
            $table->string('district', 160)->nullable();
            $table->string('complement', 160)->nullable();
            $table->timestamps();
        });

        Schema::create('person_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->text('number');
            $table->string('issuing_authority', 120)->nullable();
            $table->char('issuing_state', 2)->nullable();
            $table->timestamps();
            $table->unique(['person_id', 'type']);
        });

        Schema::create('person_phones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->text('number');
            $table->string('category', 16);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['person_id', 'is_primary']);
        });

        Schema::create('person_whatsapp_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->text('value');
            $table->text('user_key')->nullable();
            $table->foreignId('phone_id')->nullable()->constrained('person_phones')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['person_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_whatsapp_identifiers');
        Schema::dropIfExists('person_phones');
        Schema::dropIfExists('person_documents');
        Schema::dropIfExists('people');
    }
};
