<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 120)->nullable()->after('name');
            $table->string('department', 120)->nullable()->after('job_title');
        });
        Schema::table('project_invitations', function (Blueprint $table) {
            $table->string('job_title', 120)->nullable()->after('email');
            $table->string('department', 120)->nullable()->after('job_title');
        });
    }

    public function down(): void
    {
        Schema::table('project_invitations', fn (Blueprint $table) => $table->dropColumn(['job_title', 'department']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['job_title', 'department']));
    }
};
