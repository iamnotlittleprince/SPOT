<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_key');
            $table->string('provider', 20);
            $table->string('payload_hash', 64);
            $table->string('status', 20);
            $table->foreignId('calendar_event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'request_key', 'provider']);
        });
    }
    public function down(): void { Schema::dropIfExists('calendar_deliveries'); }
};
