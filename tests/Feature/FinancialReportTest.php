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

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_result_uses_composed_taxes_net_commission_and_approved_costs(): void
    {
        $company = Company::create(['legal_name' => 'Computécnica']);
        $manager = User::factory()->create(['current_company_id' => $company->id]);
        $profile = Profile::create(['name' => 'Gestor', 'slug' => 'gestor']);
        $permission = Permission::create(['name' => 'Financeiro', 'slug' => 'financial.view', 'module' => 'Financeiro']);
        $profile->permissions()->attach($permission);
        $manager->profiles()->attach($profile, ['company_id' => $company->id]);
        $project = Project::create([
            'company_id' => $company->id, 'user_id' => $manager->id, 'name' => 'Projeto financeiro',
            'status' => 'in_progress', 'progress' => 50, 'contract_value' => 10000,
            'commission_rate' => 5, 'commission_basis' => 'net_of_taxes',
            'estimated_labor_cost' => 1000, 'estimated_additional_cost' => 200, 'estimated_minutes' => 600,
        ]);
        $iss = DB::table('tax_types')->insertGetId(['company_id' => $company->id, 'name' => 'ISS', 'slug' => 'iss', 'created_at' => now(), 'updated_at' => now()]);
        $pis = DB::table('tax_types')->insertGetId(['company_id' => $company->id, 'name' => 'PIS', 'slug' => 'pis', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('project_taxes')->insert([
            ['project_id' => $project->id, 'tax_type_id' => $iss, 'calculation_type' => 'percentage', 'rate' => 10, 'fixed_amount' => null, 'calculated_amount' => 0, 'calculation_basis' => 'total_revenue', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $project->id, 'tax_type_id' => $pis, 'calculation_type' => 'fixed', 'rate' => null, 'fixed_amount' => 100, 'calculated_amount' => 0, 'calculation_basis' => 'contract_value', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $activity = DB::table('activity_types')->insertGetId(['company_id' => $company->id, 'name' => 'Extra', 'slug' => 'extra', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('work_logs')->insert(['project_id' => $project->id, 'analyst_id' => $manager->id, 'activity_type_id' => $activity, 'worked_on' => '2026-08-20', 'duration_minutes' => 120, 'description' => 'Hora extra', 'is_overtime' => true, 'cost_rate_snapshot' => 100, 'sale_rate_snapshot' => 200, 'status' => 'submitted', 'created_by' => $manager->id, 'created_at' => now(), 'updated_at' => now()]);
        $expenseType = DB::table('expense_types')->insertGetId(['company_id' => $company->id, 'name' => 'Viagem', 'slug' => 'viagem', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['approved', 500], ['pending', 999]] as [$status, $amount]) {
            DB::table('project_expenses')->insert(['project_id' => $project->id, 'expense_type_id' => $expenseType, 'submitted_by' => $manager->id, 'expense_date' => '2026-08-20', 'description' => $status, 'amount' => $amount, 'currency' => 'BRL', 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($manager)->getJson("/api/v1/projects/{$project->id}/financial-result")
            ->assertOk()
            ->assertJsonPath('actual.total_revenue', '10400.00')
            ->assertJsonPath('actual.taxes', '1140.00')
            ->assertJsonPath('actual.commission', '463.00')
            ->assertJsonPath('actual.labor_cost', '200.00')
            ->assertJsonPath('actual.approved_expenses', '500.00')
            ->assertJsonPath('actual.profit', '8097.00')
            ->assertJsonPath('actual.margin_percent', '77.86')
            ->assertJsonPath('actual.estimated_minutes_consumed_percent', 20)
            ->assertJsonPath('estimated.profit', '7255.00');
    }
}
