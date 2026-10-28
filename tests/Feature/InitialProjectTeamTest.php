<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InitialProjectTeamTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $admin): array
    {
        return ['name' => 'Projeto com equipe inicial', 'proposal_number' => 'EQUIPE-001', 'contract_value' => 100,
            'project_status_id' => DB::table('project_statuses')->where('company_id', $admin->current_company_id)->value('id'),
            'project_situation_id' => DB::table('project_situations')->where('company_id', $admin->current_company_id)->value('id')];
    }

    private function user(User $admin, string $role = 'analista', array $attributes = []): User
    {
        $user = User::factory()->create(['current_company_id' => $admin->current_company_id, ...$attributes]);
        $user->profiles()->attach(Profile::where('slug', $role)->value('id'), ['company_id' => $user->current_company_id]);
        return $user;
    }

    public function test_selected_users_are_saved_as_team_members_and_guests_keep_limited_access(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $analyst = $this->user($admin);
        $guest = $this->user($admin, 'convidado');
        $project = $this->actingAs($admin)->postJson('/api/v1/projects', [...$this->payload($admin), 'member_ids' => [$analyst->id, $guest->id]])->assertCreated()->json();
        $this->assertDatabaseHas('project_members', ['project_id' => $project['id'], 'user_id' => $analyst->id, 'role' => 'analyst', 'active' => true, 'assigned_by' => $admin->id]);
        $this->assertDatabaseHas('project_members', ['project_id' => $project['id'], 'user_id' => $guest->id, 'role' => 'guest']);
        $stored = Project::findOrFail($project['id']);
        $this->assertSame(['tasks.view', 'files.view'], $stored->members()->where('user_id', $guest->id)->firstOrFail()->permissions);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.member_assigned', 'auditable_id' => $stored->members()->where('user_id', $analyst->id)->value('id')]);
        $this->getJson('/api/v1/teams/'.$project['id'])->assertOk()->assertJsonCount(2, 'members');
        $this->actingAs($analyst)->getJson('/api/v1/projects')->assertOk()->assertJsonFragment(['id' => $project['id']]);
    }

    public function test_invalid_members_are_rejected_without_creating_a_partial_project(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $analyst = $this->user($admin);
        $inactive = $this->user($admin, 'analista', ['active' => false]);
        $pending = $this->user($admin, 'analista', ['account_status' => 'pending_activation']);
        $other = Company::create(['legal_name' => 'Empresa externa']);
        $foreign = $this->user($admin, 'analista', ['current_company_id' => $other->id]);
        $before = Project::count();
        foreach ([[$inactive->id], [$pending->id], [$foreign->id], [$analyst->id, $analyst->id], [999999999], ['invalid']] as $ids) {
            $this->actingAs($admin)->postJson('/api/v1/projects', [...$this->payload($admin), 'member_ids' => $ids])->assertUnprocessable();
            $this->assertSame($before, Project::count());
        }
        $this->actingAs($analyst)->postJson('/api/v1/projects', [...$this->payload($admin), 'member_ids' => [$analyst->id]])->assertForbidden();
    }

    public function test_a_failure_during_membership_creation_rolls_back_project_team_and_audit(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $first = $this->user($admin);
        $second = $this->user($admin);
        $before = [Project::count(), \App\Models\ProjectMember::count(), \App\Models\AuditLog::count()];
        $event = 'eloquent.creating: '.\App\Models\ProjectMember::class;
        \Illuminate\Support\Facades\Event::listen($event, function ($member) use ($second) {
            if ($member->user_id === $second->id) throw new \RuntimeException('Simulated membership failure');
        });
        try {
            $this->actingAs($admin)->postJson('/api/v1/projects', [...$this->payload($admin), 'member_ids' => [$first->id, $second->id]])->assertStatus(500);
            $this->assertSame($before, [Project::count(), \App\Models\ProjectMember::count(), \App\Models\AuditLog::count()]);
        } finally {
            \Illuminate\Support\Facades\Event::forget($event);
        }
    }

    public function test_candidates_include_only_active_accounts_and_team_changes_use_the_existing_endpoint(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $active = $this->user($admin);
        $pending = $this->user($admin, 'analista', ['account_status' => 'pending_activation']);
        $ids = array_column($this->actingAs($admin)->getJson('/api/v1/parameters')->assertOk()->json('project_workers'), 'id');
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($pending->id, $ids);
        $project = $this->postJson('/api/v1/projects', $this->payload($admin))->assertCreated()->json();
        $this->assertSame(0, Project::findOrFail($project['id'])->members()->count());
        $this->putJson('/api/v1/projects/'.$project['id'], ['member_ids' => [$active->id]])->assertUnprocessable()->assertJsonValidationErrors('member_ids');
    }
}
