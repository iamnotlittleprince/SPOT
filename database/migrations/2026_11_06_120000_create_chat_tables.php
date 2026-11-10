<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('first_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('second_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'first_user_id', 'second_user_id'], 'chat_pair_unique');
        });
        Schema::create('chat_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'status']);
        });
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
        Schema::create('chat_message_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->text('body');
            $table->timestamp('created_at');
            $table->unique(['message_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_revisions');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_access_requests');
        Schema::dropIfExists('chat_conversations');
    }
};
