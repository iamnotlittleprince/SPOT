<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_real_projects_tasks_and_active_teams_and_can_toggle_task(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Projeto real', 'status' => 'in_progress', 'progress' => 40]);
        $project->members()->create(['user_id' => $admin->id, 'role' => 'manager', 'relationship_type' => 'responsible_manager', 'active' => true]);
        $task = Task::create(['project_id' => $project->id, 'user_id' => $admin->id, 'title' => 'Tarefa real', 'status' => 'todo', 'priority' => 'high']);

        $this->actingAs($admin)->getJson('/api/v1/home-dashboard')->assertOk()
            ->assertJsonPath('projects.0.name', 'Projeto real')
            ->assertJsonPath('tasks.0.title', 'Tarefa real')
            ->assertJsonPath('teams.0.count', 1)
            ->assertJsonPath('teams.0.members.0.id', $admin->id)
            ->assertJsonPath('teams.0.members.0.name', $admin->name);

        $this->patchJson("/api/v1/home-dashboard/tasks/{$task->id}/toggle")
            ->assertOk()->assertJsonPath('status', 'done');
        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_dashboard_does_not_expose_another_company_project(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $other = User::factory()->create();
        Project::create(['user_id' => $other->id, 'name' => 'Projeto externo', 'status' => 'planning', 'progress' => 0]);

        $this->actingAs($admin)->getJson('/api/v1/home-dashboard')->assertOk()->assertJsonMissing(['name' => 'Projeto externo']);
    }
}
