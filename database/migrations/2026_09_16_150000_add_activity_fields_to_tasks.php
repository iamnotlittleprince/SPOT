<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->date('worked_on')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->foreignId('activity_type_id')->nullable()->constrained('activity_types')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_overtime')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_type_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['worked_on', 'duration_minutes', 'description', 'is_overtime']);
        });
    }
};
