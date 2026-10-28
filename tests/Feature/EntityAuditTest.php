<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditRecorder;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EntityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Projeto auditado']);
        $task = Task::create(['project_id' => $project->id, 'user_id' => $admin->id, 'title' => 'Entrega auditada', 'status' => 'todo', 'priority' => 'medium']);
        return [$admin, $project, $task];
    }

    public function test_home_changes_capture_actor_context_and_before_after_without_logging_noops(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $this->actingAs($admin)->patchJson("/api/v1/home-dashboard/tasks/{$task->id}/toggle")->assertOk();
        $this->patchJson("/api/v1/home-dashboard/tasks/{$task->id}/priority", ['priority' => 'high'])->assertOk();
        $this->patchJson("/api/v1/home-dashboard/tasks/{$task->id}/priority", ['priority' => 'high'])->assertOk();
        $this->patchJson("/api/v1/home-dashboard/tasks/{$task->id}/priority", ['priority' => 'invalid'])->assertUnprocessable();
        $status = AuditLog::where('action', 'task.status_changed')->firstOrFail();
        $this->assertSame($admin->id, $status->user_id);
        $this->assertSame('todo', $status->old_values['status']);
        $this->assertSame('done', $status->new_values['status']);
        $this->assertSame($project->id, $status->new_values['_context']['project_id']);
        $this->assertSame($task->id, $status->new_values['_context']['task_id']);
        $this->assertNotNull($status->ip_address);
        $this->assertNotNull($status->request_id);
        $this->assertSame(1, AuditLog::where('action', 'task.priority_changed')->count());
        $this->getJson("/api/v1/history?project_id={$project->id}&task_id={$task->id}")
            ->assertOk()->assertJsonCount(2, 'events')
            ->assertJsonPath('events.0.context.subject_name', 'Entrega auditada')
            ->assertJsonPath('events.0.old_values.priority', 'medium')
            ->assertJsonPath('events.0.new_values.priority', 'high');
    }

    public function test_project_history_includes_related_and_legacy_records_and_survives_deletion(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $audit = app(AuditRecorder::class);
        $this->actingAs($admin);
        $audit->record('project.created', $project, $admin, [], $project->getAttributes());
        $member = $project->members()->create(['user_id' => $admin->id, 'role' => 'manager', 'active' => true]);
        $audit->record('project.member_assigned', $member, $admin, [], $member->getAttributes());
        AuditLog::create(['company_id' => $project->company_id, 'user_id' => $admin->id, 'action' => 'task.updated', 'auditable_type' => Task::class, 'auditable_id' => $task->id, 'old_values' => ['project_id' => $project->id, 'title' => 'Entrega original'], 'created_at' => now()]);
        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();
        $this->getJson("/api/v1/history?project_id={$project->id}")->assertOk()->assertJsonCount(4, 'events');
        $this->getJson("/api/v1/history?project_id={$project->id}&task_id={$task->id}")->assertOk()->assertJsonCount(2, 'events');
        $this->deleteJson("/api/v1/projects/{$project->id}", ['reason' => 'Projeto encerrado para teste'])->assertNoContent();
        $this->getJson("/api/v1/history?project_id={$project->id}")->assertOk()->assertJsonCount(5, 'events')
            ->assertJsonPath('events.0.context.subject_name', 'Projeto auditado');
    }

    public function test_history_preserves_company_personal_and_detail_access_boundaries(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $viewer = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $audit = app(AuditRecorder::class);
        $audit->record('task.updated', $task, $admin, ['priority' => 'low'], ['priority' => 'high', 'cost_rate_snapshot' => 100]);
        $own = $audit->record('task.updated', $task, $viewer, ['priority' => 'medium'], ['priority' => 'high', 'cost_rate_snapshot' => 200]);
        $foreignCompany = Company::create(['legal_name' => 'Empresa externa']);
        $foreign = Project::create(['company_id' => $foreignCompany->id, 'user_id' => $admin->id, 'name' => 'Externo']);
        AuditLog::create(['company_id' => $foreignCompany->id, 'user_id' => $viewer->id, 'action' => 'task.updated', 'auditable_type' => Task::class, 'auditable_id' => $task->id, 'new_values' => ['project_id' => $project->id], 'created_at' => now()]);
        $this->actingAs($viewer)->getJson("/api/v1/history?project_id={$project->id}&task_id={$task->id}")
            ->assertOk()->assertJsonCount(1, 'events')->assertJsonPath('events.0.id', $own->id)
            ->assertJsonMissingPath('events.0.old_values')->assertJsonMissingPath('events.0.new_values')->assertJsonMissingPath('events.0.request_id');
        $this->getJson("/api/v1/history?project_id={$foreign->id}")->assertNotFound();
        $this->getJson("/api/v1/history?task_id={$task->id}")->assertUnprocessable();
        $this->getJson('/api/v1/history?project_id=invalid')->assertUnprocessable();
        $this->actingAs($admin)->getJson("/api/v1/history?project_id={$project->id}")->assertOk()->assertJsonCount(2, 'events');
    }

    public function test_failed_audit_rolls_back_the_task_change(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $listener = 'eloquent.creating: '.AuditLog::class;
        Event::listen($listener, function () { throw new \RuntimeException('Audit storage unavailable'); });
        try {
            $this->actingAs($admin)->patchJson("/api/v1/home-dashboard/tasks/{$task->id}/toggle")->assertStatus(500);
            $this->assertSame('todo', $task->fresh()->status);
            $this->assertSame(0, AuditLog::where('action', 'task.status_changed')->count());
        } finally {
            Event::forget($listener);
        }
    }


    public function test_task_history_includes_linked_work_and_preserves_the_actor_name(): void
    {
        [$admin, $project, $task] = $this->fixture();
        $log = new \App\Models\WorkLog(['project_id' => $project->id, 'task_id' => $task->id, 'analyst_id' => $admin->id, 'duration_minutes' => 60]);
        $log->id = 999;
        $name = $admin->name;
        app(AuditRecorder::class)->record('work_log.created', $log, $admin, [], $log->getAttributes());
        $admin->update(['name' => 'Nome atualizado']);
        $this->actingAs($admin)->getJson("/api/v1/history?project_id={$project->id}&task_id={$task->id}")
            ->assertOk()->assertJsonCount(1, 'events')->assertJsonPath('events.0.action', 'work_log.created')
            ->assertJsonPath('events.0.context.actor_name', $name);
    }

    public function test_filtered_history_pagination_does_not_repeat_events(): void
    {
        [$admin, $project, $task] = $this->fixture();
        for ($i = 0; $i < 102; $i++) app(AuditRecorder::class)->record('task.updated', $task, $admin, ['priority' => 'medium'], ['priority' => 'high']);
        $first = $this->actingAs($admin)->getJson("/api/v1/history?project_id={$project->id}&task_id={$task->id}")->assertOk()->assertJsonCount(100, 'events');
        $second = $this->getJson("/api/v1/history?project_id={$project->id}&task_id={$task->id}&before=".$first->json('next'))->assertOk()->assertJsonCount(2, 'events')->assertJsonPath('next', null);
        $this->assertEmpty(array_intersect(array_column($first->json('events'), 'id'), array_column($second->json('events'), 'id')));
    }
}
