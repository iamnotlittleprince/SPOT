<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequiredProjectManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_creation_requires_a_manager_and_assigns_them_to_the_team(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $payload = ['name' => 'Projeto com gerente obrigatório', 'proposal_number' => 'GER-001', 'contract_value' => 100,
            'project_status_id' => DB::table('project_statuses')->where('company_id', $admin->current_company_id)->value('id'),
            'project_situation_id' => DB::table('project_situations')->where('company_id', $admin->current_company_id)->value('id')];
        $this->actingAs($admin)->postJson('/api/v1/projects', $payload)->assertUnprocessable()->assertJsonValidationErrors('project_manager_id');
        $id = $this->postJson('/api/v1/projects', [...$payload, 'project_manager_id' => $admin->id, 'member_ids' => [$admin->id]])->assertCreated()->json('id');
        $this->assertDatabaseHas('project_members', ['project_id' => $id, 'user_id' => $admin->id, 'role' => 'manager', 'active' => true]);
        $this->assertSame(1, Project::findOrFail($id)->members()->count());
        $this->putJson('/api/v1/projects/'.$id, ['project_manager_id' => null])->assertUnprocessable()->assertJsonValidationErrors('project_manager_id');
    }

    public function test_last_manager_cannot_be_removed_or_demoted_from_either_team_endpoint(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $project = Project::create(['name' => 'Equipe com gerente', 'user_id' => $admin->id, 'company_id' => $admin->current_company_id, 'project_manager_id' => $admin->id]);
        $manager = $project->members()->create(['user_id' => $admin->id, 'role' => 'manager', 'active' => true]);
        $this->actingAs($admin)->putJson('/api/v1/teams/'.$project->id, ['name' => 'Equipe', 'members' => []])->assertUnprocessable()->assertJsonValidationErrors('members');
        $this->postJson('/api/v1/projects/'.$project->id.'/members', ['user_id' => $admin->id, 'role' => 'analyst'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->deleteJson('/api/v1/projects/'.$project->id.'/members/'.$admin->id)->assertUnprocessable()->assertJsonValidationErrors('members');
        $this->assertTrue($manager->fresh()->active);
        $this->assertSame('manager', $manager->fresh()->role);
        $this->assertNull($manager->fresh()->access_revoked_at);

        $other = User::factory()->create(['current_company_id' => $admin->current_company_id, 'active' => true, 'account_status' => 'active']);
        $other->profiles()->attach(DB::table('profiles')->where('slug', 'gestor')->value('id'), ['company_id' => $admin->current_company_id]);
        $this->postJson('/api/v1/projects/'.$project->id.'/members', ['user_id' => $other->id, 'role' => 'manager'])->assertCreated();
        $this->deleteJson('/api/v1/projects/'.$project->id.'/members/'.$admin->id)->assertNoContent();
        $this->assertSame($other->id, $project->fresh()->project_manager_id);
    }
}
