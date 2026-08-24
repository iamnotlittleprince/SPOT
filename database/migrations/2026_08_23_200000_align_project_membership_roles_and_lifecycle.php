<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            $table->timestamp('access_revoked_at')->nullable()->after('active');
        });

        DB::table('project_members')->whereNotIn('role', ['analyst', 'manager'])->update(['role' => 'analyst']);
        DB::table('project_invitations')->whereNotIn('project_role', ['analyst', 'manager'])->update(['project_role' => 'analyst']);
    }

    public function down(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            $table->dropColumn('access_revoked_at');
        });
    }
};
