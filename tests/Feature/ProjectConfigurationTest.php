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

class ProjectConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_configures_member_rate_and_tax_composition(): void
    {
        $company = Company::create(['legal_name' => 'Computécnica']);
        $manager = $this->authorizedUser($company, ['projects.update', 'financial.manage']);
        $analyst = User::factory()->create(['current_company_id' => $company->id]);
        $project = Project::create(['company_id' => $company->id, 'user_id' => $manager->id, 'name' => 'Projeto', 'status' => 'planning', 'progress' => 0]);

        $this->actingAs($manager)->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $analyst->id, 'role' => 'analyst',
        ])->assertCreated()->assertJsonPath('user_id', $analyst->id);

        $this->actingAs($manager)->postJson("/api/v1/projects/{$project->id}/rates", [
            'user_id' => $analyst->id, 'normal_cost_rate' => 80, 'overtime_cost_rate' => 120,
            'overtime_sale_rate' => 200, 'effective_from' => '2026-01-01', 'effective_until' => '2026-12-31',
        ])->assertCreated()->assertJsonPath('normal_cost_rate', '80.00');

        $this->actingAs($manager)->postJson("/api/v1/projects/{$project->id}/rates", [
            'user_id' => $analyst->id, 'normal_cost_rate' => 90, 'overtime_cost_rate' => 130,
            'overtime_sale_rate' => 210, 'effective_from' => '2026-06-01', 'effective_until' => '2027-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('effective_from');

        $taxType = DB::table('tax_types')->insertGetId(['company_id' => $company->id, 'name' => 'ISS', 'slug' => 'iss', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($manager)->putJson("/api/v1/projects/{$project->id}/taxes", ['taxes' => [[
            'tax_type_id' => $taxType, 'calculation_type' => 'percentage', 'rate' => 5,
            'calculation_basis' => 'total_revenue',
        ]]])->assertOk()->assertJsonPath('taxes.0.tax_type_id', $taxType);

        $this->assertDatabaseHas('audit_logs', ['action' => 'project.member_assigned']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.rate_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.taxes_replaced']);
    }

    private function authorizedUser(Company $company, array $slugs): User
    {
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $profile = Profile::create(['name' => 'Gestor', 'slug' => 'gestor']);
        foreach ($slugs as $slug) {
            $permission = Permission::create(['name' => $slug, 'slug' => $slug, 'module' => 'Teste']);
            $profile->permissions()->attach($permission);
        }
        $user->profiles()->attach($profile, ['company_id' => $company->id]);
        return $user;
    }
}
