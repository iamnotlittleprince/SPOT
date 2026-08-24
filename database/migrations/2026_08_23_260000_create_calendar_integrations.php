<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('google_access_token')->nullable(); $table->text('google_refresh_token')->nullable(); $table->timestamp('google_token_expires_at')->nullable();
            $table->text('microsoft_access_token')->nullable(); $table->text('microsoft_refresh_token')->nullable(); $table->timestamp('microsoft_token_expires_at')->nullable();
        });
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title'); $table->text('description')->nullable(); $table->timestampTz('starts_at'); $table->timestampTz('ends_at');
            $table->string('provider', 20)->default('spot'); $table->string('external_id')->nullable(); $table->text('meeting_url')->nullable(); $table->string('location')->nullable(); $table->timestamps();
            $table->index(['user_id', 'starts_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['google_access_token','google_refresh_token','google_token_expires_at','microsoft_access_token','microsoft_refresh_token','microsoft_token_expires_at']));
    }
};
