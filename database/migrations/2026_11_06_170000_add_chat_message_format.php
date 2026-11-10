<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', fn (Blueprint $table) => $table->json('body_format')->nullable());
        Schema::table('chat_message_revisions', fn (Blueprint $table) => $table->json('body_format')->nullable());
    }
    public function down(): void
    {
        Schema::table('chat_message_revisions', fn (Blueprint $table) => $table->dropColumn('body_format'));
        Schema::table('chat_messages', fn (Blueprint $table) => $table->dropColumn('body_format'));
    }
};
