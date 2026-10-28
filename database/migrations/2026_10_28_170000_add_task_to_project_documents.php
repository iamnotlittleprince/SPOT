<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('task_id'));
    }
};
