<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskV1CrudTest extends TestCase
{
    use RefreshDatabase;

    private function setupProject(): array
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Projeto de tarefas', 'status' => 'planning', 'progress' => 0]);

        return [$admin, $project];
    }

    private function payload(Project $project): array
    {
        return ['analyst_id' => auth()->id() ?? $project->user_id, 'worked_on' => '2026-09-16', 'duration_minutes' => 90, 'activity_type_id' => DB::table('activity_types')->where('company_id', $project->company_id)->value('id') ?? DB::table('activity_types')->value('id'), 'description' => 'Atividade de teste', 'is_overtime' => false, 'project_id' => $project->id, 'title' => 'Preparar entrega', 'status' => 'todo', 'priority' => 'medium', 'due_date' => '2026-10-15'];
    }

    public function test_session_user_creates_reads_updates_and_deletes_persisted_tasks(): void
    {
        [$admin, $project] = $this->setupProject();
        $payload = $this->payload($project);
        $id = $this->actingAs($admin)->postJson('/api/v1/tasks', $payload)->assertCreated()
            ->assertJsonPath('user_id', $admin->id)->assertJsonPath('due_date', '2026-10-15')->json('id');
        $this->assertDatabaseHas('tasks', ['id' => $id, 'title' => 'Preparar entrega']);
        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonPath('tasks.0.id', $id)
            ->assertJsonPath('tasks.0.can_edit', true)->assertJsonPath('tasks.0.can_delete', true);
        $this->putJson('/api/v1/tasks/'.$id, [...$payload, 'title' => 'Entrega revisada', 'status' => 'done', 'priority' => 'high', 'due_date' => null])
            ->assertOk()->assertJsonPath('due_date', null);
        $this->assertDatabaseHas('tasks', ['id' => $id, 'title' => 'Entrega revisada', 'status' => 'done', 'due_date' => null]);
        $this->getJson('/api/v1/home-dashboard')->assertOk()->assertJsonPath('tasks.0.title', 'Entrega revisada');
        $this->deleteJson('/api/v1/tasks/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $id]);
        foreach (['task.created', 'task.updated', 'task.deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'auditable_id' => $id]);
        }
    }

    public function test_validation_rejects_invalid_fields_without_writing(): void
    {
        [$admin, $project] = $this->setupProject();
        $this->actingAs($admin)->postJson('/api/v1/tasks', [...$this->payload($project), 'title' => '   ', 'status' => 'invalid', 'priority' => 'invalid', 'due_date' => '2026-02-31'])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'status', 'priority', 'due_date']);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_cross_company_and_finalized_projects_are_protected(): void
    {
        [$admin, $project] = $this->setupProject();
        $foreign = Project::create(['company_id' => Company::create(['legal_name' => 'Outra empresa'])->id, 'user_id' => $admin->id, 'name' => 'Projeto externo']);
        $foreignTask = Task::create([...$this->payload($foreign), 'user_id' => $admin->id]);
        $this->actingAs($admin)->getJson('/api/v1/tasks')->assertOk()->assertJsonCount(0, 'tasks');
        $this->postJson('/api/v1/tasks', $this->payload($foreign))->assertNotFound();
        $this->putJson('/api/v1/tasks/'.$foreignTask->id, $this->payload($project))->assertNotFound();
        $this->deleteJson('/api/v1/tasks/'.$foreignTask->id)->assertNotFound();
        $task = Task::create([...$this->payload($project), 'user_id' => $admin->id]);
        $project->forceFill(['finalized_at' => now()])->save();
        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonPath('tasks.0.can_edit', false)->assertJsonCount(0, 'projects');
        $this->postJson('/api/v1/tasks', $this->payload($project))->assertConflict();
        $this->putJson('/api/v1/tasks/'.$task->id, $this->payload($project))->assertConflict();
        $this->deleteJson('/api/v1/tasks/'.$task->id)->assertConflict();
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_analyst_can_manage_own_tasks_only_in_active_assigned_projects(): void
    {
        [$admin, $project] = $this->setupProject();
        $analyst = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $analyst->profiles()->attach(DB::table('profiles')->where('slug', 'analista')->value('id'), ['company_id' => $admin->current_company_id]);
        $this->actingAs($analyst)->postJson('/api/v1/tasks', $this->payload($project))->assertNotFound();
        $membership = $project->members()->create(['user_id' => $analyst->id, 'role' => 'analyst', 'relationship_type' => 'cpt_internal_analyst', 'active' => true]);
        $id = $this->postJson('/api/v1/tasks', $this->payload($project))->assertCreated()->assertJsonPath('can_delete', true)->json('id');
        $otherTask = Task::create([...$this->payload($project), 'user_id' => $admin->id]);
        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonCount(1, 'tasks');
        $this->putJson('/api/v1/tasks/'.$otherTask->id, $this->payload($project))->assertNotFound();
        $this->putJson('/api/v1/tasks/'.$id, [...$this->payload($project), 'status' => 'review'])->assertOk();
        // Own records can be deleted, but membership remains required.
        $membership->update(['active' => false]);
        $this->putJson('/api/v1/tasks/'.$id, $this->payload($project))->assertNotFound();
    }

    public function test_unauthenticated_requests_and_users_without_management_permission_cannot_create(): void
    {
        [$admin, $project] = $this->setupProject();
        $this->getJson('/api/v1/tasks')->assertUnauthorized();
        $this->postJson('/api/v1/tasks', $this->payload($project))->assertUnauthorized();
        $guest = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $guest->profiles()->attach(DB::table('profiles')->where('slug', 'convidado')->value('id'), ['company_id' => $admin->current_company_id]);
        $this->actingAs($guest)->postJson('/api/v1/tasks', $this->payload($project))->assertForbidden();
    }

    public function test_every_non_guest_profile_can_create_even_without_tasks_manage_permission(): void
    {
        [$admin, $project] = $this->setupProject();
        foreach (['administrador', 'gestor-administrador', 'gestor', 'analista'] as $slug) {
            $user = User::factory()->create(['current_company_id' => $admin->current_company_id]);
            $user->profiles()->attach(DB::table('profiles')->where('slug', $slug)->value('id'), ['company_id' => $admin->current_company_id]);
            DB::table('user_permission_overrides')->insert([
                'user_id' => $user->id,
                'permission_id' => DB::table('permissions')->where('slug', 'tasks.manage')->value('id'),
                'allowed' => false,
                'reason' => 'Teste da regra de criação por perfil',
            ]);
            $project->members()->create(['user_id' => $user->id, 'role' => 'analyst', 'relationship_type' => 'cpt_internal_analyst', 'active' => true]);
            $this->actingAs($user)->getJson('/api/v1/tasks')->assertOk()->assertJsonPath('can_create', true);
            $this->postJson('/api/v1/tasks', $this->payload($project))->assertCreated();
        }
        $this->assertDatabaseCount('tasks', 4);
    }

    public function test_guest_cannot_create_even_with_management_permission_override(): void
    {
        [$admin, $project] = $this->setupProject();
        $guest = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $guest->profiles()->attach(DB::table('profiles')->where('slug', 'convidado')->value('id'), ['company_id' => $admin->current_company_id]);
        DB::table('user_permission_overrides')->insert([
            'user_id' => $guest->id,
            'permission_id' => DB::table('permissions')->where('slug', 'tasks.manage')->value('id'),
            'allowed' => true,
            'reason' => 'Teste de bloqueio de convidado',
        ]);
        $project->members()->create(['user_id' => $guest->id, 'role' => 'analyst', 'relationship_type' => 'cpt_internal_analyst', 'active' => true]);
        $this->actingAs($guest)->getJson('/api/v1/tasks')->assertOk()->assertJsonPath('can_create', false);
        $this->postJson('/api/v1/tasks', $this->payload($project))->assertForbidden();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_analyst_can_delete_own_record_but_cannot_register_for_others(): void
    {
        [$admin, $project] = $this->setupProject();
        $analyst = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $analyst->profiles()->attach(DB::table('profiles')->where('slug', 'analista')->value('id'), ['company_id' => $admin->current_company_id]);
        $project->members()->create(['user_id' => $analyst->id, 'role' => 'analyst', 'relationship_type' => 'cpt_internal_analyst', 'active' => true]);
        $this->actingAs($analyst);
        $this->postJson('/api/v1/tasks', [...$this->payload($project), 'analyst_id' => $admin->id])->assertForbidden();
        $this->postJson('/api/v1/tasks', [...$this->payload($project), 'duration_minutes' => 0])->assertUnprocessable();
        $this->postJson('/api/v1/tasks', [...$this->payload($project), 'duration_minutes' => 1441])->assertUnprocessable();
        $id = $this->postJson('/api/v1/tasks', [...$this->payload($project), 'is_overtime' => true])->assertCreated()->assertJsonPath('duration_minutes', 90)->assertJsonPath('is_overtime', true)->json('id');
        $this->deleteJson('/api/v1/tasks/'.$id)->assertNoContent();
        $this->actingAs($admin)->postJson('/api/v1/tasks', [...$this->payload($project), 'analyst_id' => $analyst->id])->assertCreated()->assertJsonPath('user_id', $analyst->id)->assertJsonPath('created_by', $admin->id);
    }
}
