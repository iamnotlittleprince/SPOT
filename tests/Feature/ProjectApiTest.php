<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_jwt_user_can_manage_only_their_projects_and_tasks(): void
    {
        $this->seed();
        $user = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $headers = ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];

        $project = $this->postJson('/api/projects', [
            'name' => 'Website Institucional',
            'client_name' => 'Google Brasil',
            'status' => 'in_progress',
            'progress' => 72,
            'due_date' => '2026-09-18',
        ], $headers)->assertCreated()->json();

        $this->postJson('/api/tasks', [
            'project_id' => $project['id'],
            'title' => 'Criar protótipo navegável',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => '2026-09-12',
        ], $headers)->assertCreated()->assertJsonPath('project.name', 'Website Institucional');

        $this->getJson('/api/projects', $headers)
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.tasks_count', 1);

        $foreignProject = Project::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Privado', 'status' => 'planning', 'progress' => 0,
        ]);
        $this->getJson("/api/projects/{$foreignProject->id}", $headers)->assertForbidden();
    }
    public function test_jwt_user_without_project_creation_permission_is_denied(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/projects', [
            'name' => 'Projeto indevido', 'status' => 'planning', 'progress' => 0,
        ], ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)])->assertForbidden();
    }

}
