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
    }    public function test_analyst_only_receives_members_of_assigned_teams_even_with_permission_overrides(): void
    {
        $this->seed();
        $admin=User::where('email','admin@computecnica.com.br')->firstOrFail();
        $analyst=User::factory()->create(['current_company_id'=>$admin->current_company_id]);
        $analyst->profiles()->attach(Profile::where('slug','analista')->value('id'),['company_id'=>$admin->current_company_id]);
        foreach (['projects.view_all','projects.update'] as $permission) {
            \Illuminate\Support\Facades\DB::table('user_permission_overrides')->insert(['user_id'=>$analyst->id,'permission_id'=>\App\Models\Permission::where('slug',$permission)->value('id'),'allowed'=>true,'reason'=>'Verificação de restrição do analista']);
        }
        $peer=User::factory()->create(['current_company_id'=>$admin->current_company_id]);
        $outsider=User::factory()->create(['current_company_id'=>$admin->current_company_id]);
        $project=Project::create(['company_id'=>$admin->current_company_id,'user_id'=>$admin->id,'name'=>'Minha equipe']);
        $other=Project::create(['company_id'=>$admin->current_company_id,'user_id'=>$admin->id,'name'=>'Outra equipe']);
        foreach ([$analyst,$peer] as $person) $project->members()->create(['user_id'=>$person->id,'role'=>'analyst','active'=>true]);
        $project->members()->create(['user_id'=>$outsider->id,'role'=>'analyst','active'=>true,'access_revoked_at'=>now()]);
        $other->members()->create(['user_id'=>$outsider->id,'role'=>'analyst','active'=>true]);
        $this->actingAs($analyst)->getJson('/api/v1/teams')->assertOk()->assertJsonCount(1)->assertJsonCount(2,'0.members');
        $this->getJson("/api/v1/teams/{$project->id}")->assertOk()->assertJsonPath('can_edit',false)->assertJsonCount(0,'available_users')->assertJsonCount(2,'members');
        $this->getJson("/api/v1/teams/{$other->id}")->assertNotFound();
        $this->putJson("/api/v1/teams/{$project->id}",['name'=>'Tentativa','members'=>[]])->assertForbidden();
        $project->members()->where('user_id',$analyst->id)->update(['access_revoked_at'=>now()]);
        $this->getJson('/api/v1/teams')->assertOk()->assertJsonCount(0);
        $this->getJson("/api/v1/teams/{$project->id}")->assertNotFound();
    }

}
