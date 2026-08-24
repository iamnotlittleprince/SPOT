<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('cpf', 11)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->char('cnpj', 14)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('company_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'person_id']);
        });

        Schema::create('client_memberships', function (Blueprint $table) {
            $table->foreignId('company_person_id')->constrained('company_people')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['company_person_id', 'client_id']);
        });

        Schema::create('company_user_profiles', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['company_id', 'user_id', 'profile_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });
        Schema::dropIfExists('company_user_profiles');
        Schema::dropIfExists('client_memberships');
        Schema::dropIfExists('company_people');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('people');
    }
};
