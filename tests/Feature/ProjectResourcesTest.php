<?php

namespace Tests\Feature;

use App\Models\{Company, Profile, Project, ProjectDocument, Task, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_resources_are_scoped_to_project_company_task_visibility_and_document_owner(): void
    {
        $this->seed();
        Storage::fake('local');
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $analyst = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $analyst->profiles()->attach(Profile::where('slug', 'analista')->value('id'), ['company_id' => $admin->current_company_id]);
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Implementação']);
        $project->members()->create(['user_id' => $analyst->id, 'active' => true, 'role' => 'analyst']);
        $own = Task::create(['project_id' => $project->id, 'user_id' => $analyst->id, 'title' => 'Minha tarefa', 'status' => 'done']);
        $participant = Task::create(['project_id' => $project->id, 'user_id' => $admin->id, 'title' => 'Participação', 'status' => 'todo']);
        $participant->participants()->attach($analyst);
        Task::create(['project_id' => $project->id, 'user_id' => $admin->id, 'title' => 'Restrita', 'status' => 'todo']);
        $other = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Outro projeto']);
        Task::create(['project_id' => $other->id, 'user_id' => $analyst->id, 'title' => 'Outro contexto']);
        Storage::disk('local')->put('documents/test.txt', 'conteúdo');
        $document = ProjectDocument::create(['company_id' => $admin->current_company_id, 'project_id' => $project->id,
            'user_id' => $analyst->id, 'name' => 'relatório.txt', 'path' => 'documents/test.txt', 'size' => 8, 'mime_type' => 'text/plain']);
        ProjectDocument::create(['company_id' => $admin->current_company_id, 'project_id' => $project->id,
            'user_id' => $admin->id, 'name' => 'Privado.txt', 'path' => 'private', 'size' => 4, 'mime_type' => 'text/plain']);
        ProjectDocument::create(['company_id' => $admin->current_company_id, 'project_id' => $other->id,
            'user_id' => $analyst->id, 'name' => 'Outro.txt', 'path' => 'other', 'size' => 4, 'mime_type' => 'text/plain']);
        $response = $this->actingAs($analyst)->getJson("/api/v1/projects/{$project->id}/resources")->assertOk()
            ->assertJsonCount(2, 'tasks')->assertJsonCount(1, 'documents')->assertJsonPath('documents.0.id', $document->id);
        $this->assertEqualsCanonicalizing([$own->id, $participant->id], array_column($response->json('tasks'), 'id'));
        $this->assertArrayNotHasKey('path', $response->json('documents.0'));
        $this->assertArrayNotHasKey('cost_rate_snapshot', $response->json('tasks.0'));
        $this->get("/api/v1/documents/{$document->id}/download")->assertOk();
        $this->getJson("/api/v1/projects/{$other->id}/resources")->assertNotFound();
        $this->actingAs($admin)->getJson("/api/v1/projects/{$project->id}/resources")->assertOk()->assertJsonCount(3, 'tasks')->assertJsonCount(1, 'documents');
        $foreign = Project::create(['company_id' => Company::create(['legal_name' => 'Outra empresa'])->id, 'user_id' => $admin->id, 'name' => 'Privado']);
        $this->getJson("/api/v1/projects/{$foreign->id}/resources")->assertNotFound();
        $this->actingAs(User::factory()->create(['current_company_id' => $admin->current_company_id]))
            ->getJson("/api/v1/projects/{$project->id}/resources")->assertNotFound();
    }
}
