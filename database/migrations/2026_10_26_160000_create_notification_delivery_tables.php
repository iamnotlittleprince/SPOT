<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_mail_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 100);
            $table->string('frequency', 10);
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'period_key']);
        });
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('mail_batch_id')->nullable()->constrained('notification_mail_batches')->nullOnDelete();
            $table->string('deduplication_key', 64)->unique();
            $table->string('event', 40);
            $table->string('type', 40)->default('update');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('actor_name')->nullable();
            $table->boolean('in_app');
            $table->boolean('email');
            $table->timestampTz('available_at');
            $table->timestampTz('in_app_delivered_at')->nullable();
            $table->timestampTz('email_processed_at')->nullable();
            $table->timestampsTz();
            $table->index(['available_at', 'in_app_delivered_at']);
            $table->index(['user_id', 'email_processed_at', 'mail_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_mail_batches');
    }
};
