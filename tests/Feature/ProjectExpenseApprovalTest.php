<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectExpenseApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_is_pending_until_manager_approves_it(): void
    {
        [$user, $project, $expenseTypeId] = $this->context();

        $expense = $this->actingAs($user)->postJson("/api/v1/projects/{$project->id}/expenses", [
            'expense_type_id' => $expenseTypeId,
            'expense_date' => '2026-08-23',
            'description' => 'Deslocamento para atendimento presencial.',
            'amount' => 125.50,
        ])->assertCreated()->assertJsonPath('status', 'pending')->json();

        $this->actingAs($user)->postJson("/api/v1/expenses/{$expense['id']}/review", [
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('status', 'approved')->assertJsonPath('reviewed_by', $user->id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.created', 'auditable_id' => $expense['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.approved', 'auditable_id' => $expense['id']]);
    }

    public function test_finalized_project_rejects_new_expense(): void
    {
        [$user, $project, $expenseTypeId] = $this->context();
        $project->forceFill(['finalized_at' => now(), 'finalized_by' => $user->id])->save();

        $this->actingAs($user)->postJson("/api/v1/projects/{$project->id}/expenses", [
            'expense_type_id' => $expenseTypeId,
            'expense_date' => '2026-08-23',
            'description' => 'Despesa após encerramento.',
            'amount' => 10,
        ])->assertStatus(409);
    }

    /** @return array{User, Project, int} */
    private function context(): array
    {
        $company = Company::create(['legal_name' => 'Computécnica']);
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $profile = Profile::create(['name' => 'Gestor', 'slug' => 'gestor']);
        foreach (['projects.view_all', 'expenses.create', 'expenses.approve'] as $slug) {
            $permission = Permission::create(['name' => $slug, 'slug' => $slug, 'module' => 'Teste']);
            $profile->permissions()->attach($permission);
        }
        $user->profiles()->attach($profile, ['company_id' => $company->id]);
        $project = Project::create(['company_id' => $company->id, 'user_id' => $user->id, 'name' => 'Projeto', 'status' => 'in_progress', 'progress' => 20]);
        $expenseTypeId = DB::table('expense_types')->insertGetId(['company_id' => $company->id, 'name' => 'Combustível', 'slug' => 'combustivel', 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $project, $expenseTypeId];
    }
}
