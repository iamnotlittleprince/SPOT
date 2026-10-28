<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Company, Profile, Project, Task, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TaskBoardStatusTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Quadro']);
        $task = Task::create(['project_id' => $project->id, 'user_id' => $admin->id, 'title' => 'Tarefa legada', 'status' => 'todo', 'priority' => 'medium', 'duration_minutes' => 60, 'cost_rate_snapshot' => 100]);
        $this->actingAs($admin);
        return [$admin, $project, $task];
    }

    public function test_all_statuses_persist_without_requiring_or_changing_other_task_fields(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $before = $task->fresh()->getAttributes();
        foreach (['in_progress', 'review', 'done', 'todo'] as $status) {
            $this->patchJson('/api/v1/tasks/'.$task->id.'/status', ['status' => $status, 'priority' => 'high', 'title' => 'Ignorado'])->assertOk()->assertJsonPath('status', $status)->assertJsonPath('can_edit', true);
        }
        $after = $task->fresh()->getAttributes();
        unset($before['updated_at'], $after['updated_at']);
        $this->assertSame($before, $after);
        $this->assertSame(4, AuditLog::where('action', 'task.status_changed')->count());
        $audit = AuditLog::where('action', 'task.status_changed')->firstOrFail();
        $this->assertSame('todo', $audit->old_values['status']);
        $this->assertSame('in_progress', $audit->new_values['status']);
        $this->assertSame($task->id, $audit->new_values['_context']['task_id']);
        $this->patchJson('/api/v1/tasks/'.$task->id.'/status', ['status' => 'todo'])->assertOk();
        $this->assertSame(4, AuditLog::where('action', 'task.status_changed')->count());
        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonPath('tasks.0.status', 'todo');
    }

    public function test_invalid_status_and_finalized_or_foreign_projects_are_rejected(): void
    {
        [$admin, $project, $task] = $this->fixture();
        foreach ([[], ['status' => 'invalid'], ['status' => null]] as $data) {
            $this->patchJson('/api/v1/tasks/'.$task->id.'/status', $data)->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $project->forceFill(['finalized_at' => now()])->save();
        $this->patchJson('/api/v1/tasks/'.$task->id.'/status', ['status' => 'done'])->assertConflict();
        $project->company_id = Company::create(['legal_name' => 'Externa'])->id;
        $project->save();
        $this->patchJson('/api/v1/tasks/'.$task->id.'/status', ['status' => 'done'])->assertNotFound();
        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_analyst_must_be_active_project_member_and_responsible_and_guest_cannot_move(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $analyst = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $analyst->profiles()->attach(Profile::where('slug', 'analista')->value('id'), ['company_id' => $admin->current_company_id]);
        $url = '/api/v1/tasks/'.$task->id.'/status';
        $this->actingAs($analyst)->patchJson($url, ['status' => 'done'])->assertNotFound();
        $member = $project->members()->create(['user_id' => $analyst->id, 'role' => 'analyst', 'active' => true]);
        $task->participants()->attach($analyst);
        $this->patchJson($url, ['status' => 'done'])->assertForbidden();
        $task->update(['user_id' => $analyst->id]);
        $this->patchJson($url, ['status' => 'review'])->assertOk();
        $member->update(['active' => false]);
        $this->patchJson($url, ['status' => 'done'])->assertNotFound();
        $guest = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $guest->profiles()->attach(Profile::where('slug', 'convidado')->value('id'), ['company_id' => $admin->current_company_id]);
        $project->members()->create(['user_id' => $guest->id, 'role' => 'guest', 'active' => true]);
        $task->update(['user_id' => $guest->id]);
        $this->actingAs($guest)->patchJson($url, ['status' => 'done'])->assertForbidden();
    }

    public function test_audit_failure_rolls_back_status_and_anonymous_requests_are_rejected(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $event = 'eloquent.creating: '.AuditLog::class;
        Event::listen($event, function () { throw new \RuntimeException('Audit unavailable'); });
        try {
            $this->patchJson('/api/v1/tasks/'.$task->id.'/status', ['status' => 'done'])->assertStatus(500);
            $this->assertSame('todo', $task->fresh()->status);
        } finally { Event::forget($event); }
        auth()->forgetGuards();
        $this->patchJson('/api/v1/tasks/'.$task->id.'/status', ['status' => 'done'])->assertUnauthorized();
    }
}
