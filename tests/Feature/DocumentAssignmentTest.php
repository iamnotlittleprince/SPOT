<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Company, Profile, Project, ProjectDocument, Task, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Event, Storage};
use Tests\TestCase;

class DocumentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed();
        Storage::fake('local');
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Projeto documentos']);
        $task = Task::create(['project_id' => $project->id, 'user_id' => $admin->id, 'title' => 'Tarefa documentos', 'status' => 'todo', 'priority' => 'medium']);
        $this->actingAs($admin);
        return [$admin, $project, $task];
    }

    private function upload(array $assignment = [])
    {
        return $this->postJson('/api/v1/documents', [...$assignment, 'file' => UploadedFile::fake()->createWithContent('relatório.txt', 'Documento de teste')]);
    }

    public function test_upload_supports_no_link_project_and_task_and_infers_the_task_project(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $this->upload()->assertCreated()->assertJsonPath('project_id', null)->assertJsonPath('task_id', null);
        $this->upload(['project_id' => $project->id])->assertCreated()->assertJsonPath('project.id', $project->id)->assertJsonPath('task_id', null);
        $id = $this->upload(['task_id' => $task->id])->assertCreated()->assertJsonPath('project_id', $project->id)->assertJsonPath('task.id', $task->id)->json('id');
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(3, 'documents')->assertJsonCount(1, 'projects')->assertJsonCount(1, 'tasks');
        $this->get('/api/v1/documents/'.$id.'/download')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.created', 'auditable_id' => $id]);
    }

    public function test_reassignment_and_removal_preserve_file_and_audit_changes(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $id = $this->upload()->assertCreated()->json('id');
        $before = ProjectDocument::findOrFail($id)->only(['path', 'name', 'size', 'mime_type', 'user_id']);
        $url = '/api/v1/documents/'.$id;
        $this->patchJson($url, ['project_id' => $project->id, 'task_id' => $task->id])->assertOk()->assertJsonPath('task.title', $task->title);
        $this->patchJson($url, ['project_id' => $project->id, 'task_id' => $task->id])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'document.updated')->count());
        $this->patchJson($url, ['project_id' => $project->id, 'task_id' => null])->assertOk()->assertJsonPath('task_id', null);
        $this->patchJson($url, ['project_id' => null, 'task_id' => null])->assertOk()->assertJsonPath('project_id', null);
        $this->assertSame($before, ProjectDocument::findOrFail($id)->only(array_keys($before)));
        Storage::disk('local')->assertExists($before['path']);
        $this->assertSame(3, AuditLog::where('action', 'document.updated')->count());
        $this->patchJson($url, [])->assertUnprocessable();
        $this->deleteJson($url)->assertNoContent();
        Storage::disk('local')->assertMissing($before['path']);
    }

    public function test_mismatched_foreign_and_nonexistent_targets_are_rejected_without_uploading(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $foreign = Project::create(['company_id' => Company::create(['legal_name' => 'Outra empresa'])->id, 'user_id' => $admin->id, 'name' => 'Externo']);
        $foreignTask = Task::create(['project_id' => $foreign->id, 'user_id' => $admin->id, 'title' => 'Externa']);
        foreach ([['project_id' => $foreign->id], ['task_id' => $foreignTask->id], ['project_id' => $foreign->id, 'task_id' => $task->id], ['task_id' => 999999], ['task_id' => 'inválido']] as $data) {
            $this->upload($data)->assertUnprocessable();
        }
        $this->assertDatabaseCount('project_documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_task_choices_follow_membership_and_responsible_or_participant_visibility(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $analyst = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $analyst->profiles()->attach(Profile::where('slug', 'analista')->value('id'), ['company_id' => $admin->current_company_id]);
        $this->actingAs($analyst)->getJson('/api/v1/documents')->assertOk()->assertJsonCount(0, 'projects')->assertJsonCount(0, 'tasks');
        $this->upload(['task_id' => $task->id])->assertUnprocessable();
        $member = $project->members()->create(['user_id' => $analyst->id, 'role' => 'analyst', 'active' => true]);
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(1, 'projects')->assertJsonCount(0, 'tasks');
        $this->upload(['task_id' => $task->id])->assertUnprocessable();
        $task->participants()->attach($analyst);
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(1, 'tasks');
        $id = $this->upload(['task_id' => $task->id])->assertCreated()->json('id');
        $member->update(['active' => false]);
        $this->patchJson('/api/v1/documents/'.$id, ['project_id' => $project->id, 'task_id' => $task->id])->assertUnprocessable();
        $this->patchJson('/api/v1/documents/'.$id, ['project_id' => null, 'task_id' => null])->assertOk();
    }

    public function test_documents_remain_private_and_company_scoped(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $id = $this->upload(['task_id' => $task->id])->assertCreated()->json('id');
        $other = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $this->actingAs($other)->getJson('/api/v1/documents')->assertOk()->assertJsonCount(0, 'documents');
        $url = '/api/v1/documents/'.$id;
        $this->patchJson($url, ['project_id' => null, 'task_id' => null])->assertNotFound();
        $this->getJson($url.'/download')->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $admin->current_company_id = Company::create(['legal_name' => 'Empresa nova'])->id;
        $admin->save();
        $this->actingAs($admin)->getJson('/api/v1/documents')->assertOk()->assertJsonCount(0, 'documents');
        $this->patchJson($url, ['project_id' => null, 'task_id' => null])->assertNotFound();
    }

    public function test_deleted_task_only_clears_task_link_and_failed_audit_rolls_back_assignment(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $id = $this->upload(['task_id' => $task->id])->assertCreated()->json('id');
        $task->delete();
        $this->assertDatabaseHas('project_documents', ['id' => $id, 'project_id' => $project->id, 'task_id' => null]);
        $event = 'eloquent.creating: '.AuditLog::class;
        Event::listen($event, function () { throw new \RuntimeException('Audit unavailable'); });
        try {
            $this->patchJson('/api/v1/documents/'.$id, ['project_id' => null, 'task_id' => null])->assertStatus(500);
            $this->assertDatabaseHas('project_documents', ['id' => $id, 'project_id' => $project->id]);
        } finally { Event::forget($event); }
    }
}
