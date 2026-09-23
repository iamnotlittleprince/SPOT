<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_rename_a_team_and_manage_its_members(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $first = User::factory()->create(['current_company_id' => $admin->current_company_id, 'organization_id' => $admin->organization_id, 'name' => 'Primeira Pessoa']);
        $second = User::factory()->create(['current_company_id' => $admin->current_company_id, 'organization_id' => $admin->organization_id, 'name' => 'Segunda Pessoa']);
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Projeto Equipe', 'status' => 'planning', 'progress' => 0]);
        $project->members()->create(['user_id' => $first->id, 'role' => 'analyst', 'active' => true]);

        $this->actingAs($admin)->getJson("/api/v1/teams/{$project->id}")
            ->assertOk()->assertJsonPath('name', 'Time - Projeto Equipe')->assertJsonPath('can_edit', true);

        $this->putJson("/api/v1/teams/{$project->id}", [
            'name' => 'Time de Entrega',
            'members' => [['user_id' => $second->id, 'role' => 'manager']],
        ])->assertOk()->assertJsonPath('name', 'Time de Entrega')->assertJsonPath('members.0.role', 'manager');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'team_name' => 'Time de Entrega']);
        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'user_id' => $first->id, 'active' => false]);
        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'user_id' => $second->id, 'active' => true, 'role' => 'manager']);
    }

    public function test_assigned_analyst_can_view_but_cannot_edit_a_team(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $analyst = User::factory()->create(['current_company_id' => $admin->current_company_id, 'organization_id' => $admin->organization_id]);
        $analyst->profiles()->attach(Profile::where('slug', 'analista')->value('id'), ['company_id' => $admin->current_company_id]);
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Projeto Visível', 'status' => 'planning', 'progress' => 0]);
        $project->members()->create(['user_id' => $analyst->id, 'role' => 'analyst', 'active' => true]);

        $this->actingAs($analyst)->getJson("/api/v1/teams/{$project->id}")
            ->assertOk()->assertJsonPath('can_edit', false);
        $this->putJson("/api/v1/teams/{$project->id}", ['name' => 'Sem permissão', 'members' => []])->assertForbidden();
    }
}
