<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', fn (Blueprint $table) => $table->uuid('group_key')->nullable()->index());
        DB::table('calendar_events')->orderBy('id')->eachById(function ($event) {
            $key = DB::table('calendar_deliveries')->where('user_id', $event->user_id)->where('calendar_event_id', $event->id)->value('request_key');
            DB::table('calendar_events')->where('id', $event->id)->update(['group_key' => $key ?: (string) Str::uuid()]);
        });
        Schema::create('calendar_event_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_key');
            $table->unsignedBigInteger('source_event_id');
            $table->uuid('group_key');
            $table->string('payload_hash', 64);
            $table->json('steps');
            $table->timestamps();
            $table->unique(['user_id', 'request_key']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('calendar_event_changes');
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropIndex(['group_key']);
            $table->dropColumn('group_key');
        });
    }
};
