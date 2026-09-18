<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_expenses', fn (Blueprint $t) => $t->foreignId('analyst_id')->nullable()->constrained('users')->nullOnDelete());
        DB::table('project_expenses')->update(['analyst_id' => DB::raw('submitted_by')]);
    }

    public function down(): void
    {
        Schema::table('project_expenses', fn (Blueprint $t) => $t->dropConstrainedForeignId('analyst_id'));
    }
};
