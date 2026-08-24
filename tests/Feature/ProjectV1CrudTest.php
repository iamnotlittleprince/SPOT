<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectV1CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_versioned_project_crud_validates_business_fields_and_hides_financial_data(): void
    {
        $company = Company::create(['legal_name' => 'Computécnica']);
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $profile = Profile::create(['name' => 'Operacional', 'slug' => 'operacional']);
        foreach (['projects.create', 'projects.update', 'projects.view_all'] as $slug) {
            $permission = Permission::create(['name' => $slug, 'slug' => $slug, 'module' => 'Projetos']);
            $profile->permissions()->attach($permission);
        }
        $user->profiles()->attach($profile, ['company_id' => $company->id]);
        $status = DB::table('project_statuses')->insertGetId(['company_id' => $company->id, 'name' => 'Planejamento', 'slug' => 'planning', 'created_at' => now(), 'updated_at' => now()]);
        $situation = DB::table('project_situations')->insertGetId(['company_id' => $company->id, 'name' => 'No prazo', 'slug' => 'on-time', 'created_at' => now(), 'updated_at' => now()]);

        $project = $this->actingAs($user)->postJson('/api/v1/projects', [
            'name' => 'Implantação segura', 'proposal_number' => 'PROP-2026-001',
            'project_status_id' => $status, 'project_situation_id' => $situation,
            'start_date' => '2026-09-01', 'end_date' => '2026-10-01',
            'contract_value' => 25000, 'commission_rate' => 5, 'commission_basis' => 'gross',
            'estimated_minutes' => 2400,
        ])->assertCreated()->assertJsonMissingPath('contract_value')->json();

        $this->assertDatabaseHas('projects', ['id' => $project['id'], 'contract_value' => 25000, 'company_id' => $company->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.created', 'auditable_id' => $project['id']]);

        $this->actingAs($user)->getJson('/api/v1/projects')->assertOk()->assertJsonMissingPath('0.contract_value');
    }
}
