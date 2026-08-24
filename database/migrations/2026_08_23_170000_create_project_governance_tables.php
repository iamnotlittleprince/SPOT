<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('is_system')->default(true);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('module');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_profile', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'profile_id']);
        });

        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->boolean('allowed');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('active')->default(true);
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30)->default('client');
            $table->string('document', 20)->nullable()->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->string('account_status', 30)->default('pending_activation')->index();
            $table->string('google_email')->nullable()->unique();
            $table->string('microsoft_email')->nullable()->unique();
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::create('user_activation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('legal_name')->nullable();
            $table->string('document', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
        });

        Schema::create('project_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('project_situations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'slug']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_status_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_situation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('proposal_number')->nullable();
            $table->string('participation_type', 30)->default('full_project');
            $table->string('external_project_name')->nullable();
            $table->string('external_project_code')->nullable();
            $table->text('cpt_scope')->nullable();
            $table->date('proposal_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('billing_date')->nullable();
            $table->decimal('contract_value', 15, 2)->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->decimal('commission_rate', 7, 4)->default(0);
            $table->string('commission_basis', 20)->default('gross');
            $table->decimal('estimated_labor_cost', 15, 2)->default(0);
            $table->decimal('estimated_additional_cost', 15, 2)->default(0);
            $table->unsignedInteger('estimated_minutes')->default(0);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('finalization_reason')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deletion_reason')->nullable();
            $table->softDeletes();
        });

        Schema::create('tax_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('project_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_type_id')->constrained()->restrictOnDelete();
            $table->string('calculation_type', 20)->default('percentage');
            $table->decimal('rate', 7, 4)->nullable();
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->decimal('calculated_amount', 15, 2)->default(0);
            $table->string('calculation_basis', 30)->default('contract_value');
            $table->timestamps();
            $table->unique(['project_id', 'tax_type_id']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40)->default('analyst');
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('relationship_type', 40)->default('cpt_employee');
            $table->json('permissions')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('analyst_project_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('normal_cost_rate', 15, 2);
            $table->decimal('overtime_cost_rate', 15, 2);
            $table->decimal('overtime_sale_rate', 15, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'user_id', 'effective_from']);
        });

        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('analyst_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            $table->date('worked_on');
            $table->unsignedInteger('duration_minutes');
            $table->text('description');
            $table->boolean('is_overtime')->default(false);
            $table->decimal('cost_rate_snapshot', 15, 2)->default(0);
            $table->decimal('sale_rate_snapshot', 15, 2)->default(0);
            $table->string('status', 20)->default('submitted');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['project_id', 'worked_on']);
        });

        Schema::create('expense_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('project_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users');
            $table->date('expense_date');
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('BRL');
            $table->string('receipt_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

        Schema::create('project_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('project_role', 40)->default('observer');
            $table->string('relationship_type', 40)->default('client_observer');
            $table->string('token_hash', 64)->unique();
            $table->json('permissions')->nullable();
            $table->foreignId('invited_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['email', 'expires_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->ulid('request_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->boolean('encrypted')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('project_invitations');
        Schema::dropIfExists('project_expenses');
        Schema::dropIfExists('expense_types');
        Schema::dropIfExists('work_logs');
        Schema::dropIfExists('activity_types');
        Schema::dropIfExists('analyst_project_rates');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('project_taxes');
        Schema::dropIfExists('tax_types');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['proposal_number', 'participation_type', 'external_project_name', 'external_project_code', 'cpt_scope', 'proposal_date', 'start_date', 'end_date', 'billing_date', 'contract_value', 'currency', 'commission_rate', 'commission_basis', 'estimated_labor_cost', 'estimated_additional_cost', 'estimated_minutes', 'finalized_at', 'finalization_reason', 'deletion_reason']);
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('account_manager_id');
            $table->dropConstrainedForeignId('project_manager_id');
            $table->dropConstrainedForeignId('project_status_id');
            $table->dropConstrainedForeignId('project_situation_id');
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropConstrainedForeignId('deleted_by');
        });

        Schema::dropIfExists('project_situations');
        Schema::dropIfExists('project_statuses');
        Schema::dropIfExists('user_activation_tokens');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['legal_name', 'document', 'email', 'phone', 'active']);
            $table->dropConstrainedForeignId('company_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['active', 'account_status', 'google_email', 'microsoft_email', 'last_login_at']);
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('current_company_id');
        });
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('active'));
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('user_permission_overrides');
        Schema::dropIfExists('permission_profile');
        Schema::dropIfExists('permissions');
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('is_system'));
    }
};
