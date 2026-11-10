<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_access_requests', function (Blueprint $table) {
            $table->foreignId('assigned_reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_access_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('assigned_reviewer_id'));
    }
};
