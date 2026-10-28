<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectManagerEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(Company $company, string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['current_company_id' => $company->id, ...$attributes]);
        $user->profiles()->attach(Profile::where('slug', $role)->firstOrFail(), ['company_id' => $company->id]);
        return $user;
    }

    public function test_parameters_list_only_eligible_managers_without_removing_analysts_from_users(): void
    {
        $this->seed();
        $company = Company::firstOrFail();
        $admin = $this->userWithRole($company, 'administrador');
        $allowed = [$admin, $this->userWithRole($company, 'gestor-administrador'), $this->userWithRole($company, 'gestor')];
        $analyst = $this->userWithRole($company, 'analista');
        $guest = $this->userWithRole($company, 'convidado');
        $inactive = $this->userWithRole($company, 'gestor', ['active' => false]);
        $pending = $this->userWithRole($company, 'gestor', ['account_status' => 'pending_activation']);
        $other = Company::create(['legal_name' => 'Outra empresa']);
        $foreign = $this->userWithRole($other, 'administrador');
        // An analyst does not qualify through a role assigned in a different company.
        $analyst->profiles()->attach(Profile::where('slug', 'gestor')->firstOrFail(), ['company_id' => $other->id]);
        $data = $this->actingAs($admin)->getJson('/api/v1/parameters')->assertOk()->json();
        $ids = array_column($data['managers'], 'id');
        foreach ($allowed as $user) $this->assertContains($user->id, $ids);
        foreach ([$analyst, $guest, $inactive, $pending, $foreign] as $user) $this->assertNotContains($user->id, $ids);
        $this->assertContains($analyst->id, array_column($data['users'], 'id'));
    }

    public function test_creating_and_updating_projects_enforce_roles_for_both_manager_fields(): void
    {
        $this->seed();
        $company = Company::firstOrFail();
        $admin = $this->userWithRole($company, 'administrador');
        $this->actingAs($admin);
        $base = ['name' => 'Projeto com gestão', 'proposal_number' => 'MANAGER-001', 'contract_value' => 100,
            'project_status_id' => DB::table('project_statuses')->where('company_id', $company->id)->value('id'),
            'project_situation_id' => DB::table('project_situations')->where('company_id', $company->id)->value('id')];
        foreach (['administrador', 'gestor-administrador', 'gestor'] as $index => $role) {
            $manager = $this->userWithRole($company, $role);
            $payload = [...$base, 'proposal_number' => 'MANAGER-'.$index, 'account_manager_id' => $manager->id, 'project_manager_id' => $manager->id];
            $project = $this->postJson('/api/v1/projects', $payload)->assertCreated()->json();
            $this->putJson('/api/v1/projects/'.$project['id'], ['account_manager_id' => $admin->id, 'project_manager_id' => $admin->id])->assertOk();
        }
        foreach (['analista', 'convidado'] as $role) {
            $invalid = $this->userWithRole($company, $role);
            $payload = [...$base, 'account_manager_id' => $invalid->id, 'project_manager_id' => $invalid->id];
            $this->postJson('/api/v1/projects', $payload)->assertUnprocessable()->assertJsonValidationErrors(['account_manager_id', 'project_manager_id']);
            $this->putJson('/api/v1/projects/'.$project['id'], ['account_manager_id' => $invalid->id, 'project_manager_id' => $invalid->id])->assertUnprocessable()->assertJsonValidationErrors(['account_manager_id', 'project_manager_id']);
        }
        $other = Company::create(['legal_name' => 'Empresa externa']);
        $crossCompanyRole = $this->userWithRole($company, 'analista');
        $crossCompanyRole->profiles()->attach(Profile::where('slug', 'gestor')->firstOrFail(), ['company_id' => $other->id]);
        foreach ([
            $this->userWithRole($company, 'gestor', ['active' => false]),
            $this->userWithRole($company, 'gestor', ['account_status' => 'pending_activation']),
            $this->userWithRole($other, 'administrador'),
            $crossCompanyRole,
        ] as $invalid) {
            $payload = [...$base, 'account_manager_id' => $invalid->id, 'project_manager_id' => $invalid->id];
            $this->postJson('/api/v1/projects', $payload)->assertUnprocessable()->assertJsonValidationErrors(['account_manager_id', 'project_manager_id']);
            $this->putJson('/api/v1/projects/'.$project['id'], ['account_manager_id' => $invalid->id, 'project_manager_id' => $invalid->id])->assertUnprocessable()->assertJsonValidationErrors(['account_manager_id', 'project_manager_id']);
        }
        $this->putJson('/api/v1/projects/'.$project['id'], ['account_manager_id' => null, 'project_manager_id' => null])->assertOk();
    }
}
