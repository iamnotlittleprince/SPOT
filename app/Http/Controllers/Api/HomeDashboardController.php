<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HomeDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $projectsQuery = Project::query()->where('company_id', $user->current_company_id);
        if (! $user->can('projects.view_all')) {
            Gate::authorize('projects.view_assigned');
            $projectsQuery->whereHas('members', fn ($members) => $members->where('user_id', $user->id)
                ->where(fn ($access) => $access->where('active', true)->orWhereNotNull('projects.finalized_at')));
        }

        $projects = $projectsQuery->withCount([
            'members as active_members_count' => fn ($members) => $members->where('active', true),
            'tasks',
        ])->with(['members' => fn ($members) => $members->where('active', true)->with('user:id,name,avatar_url')])
            ->orderByRaw('finalized_at is not null')->orderByDesc('updated_at')->limit(20)->get();
        $projectIds = $projects->pluck('id');

        $tasks = Task::query()->whereIn('project_id', $projectIds)->with(['project:id,name,finalized_at', 'user:id,name,avatar_url', 'creator:id,name,avatar_url', 'participants:id,name'])
            ->when(! $user->can('projects.view_all'), fn ($query) => $query->where(fn ($own) => $own->where('user_id', $user->id)->orWhereHas('participants', fn ($people) => $people->where('users.id', $user->id))))
            ->orderByRaw("case when status = 'done' then 1 else 0 end")
            ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('position')->limit(30)->get();

        return response()->json([
            'updated_at' => now()->toIso8601String(),
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id, 'name' => $project->name, 'status' => $project->status,
                'progress' => $project->progress, 'finalized' => $project->isFinalized(),
                'tasks_count' => $project->tasks_count, 'members_count' => $project->active_members_count,
            ]),
            'tasks' => $tasks->map(fn (Task $task) => [
                'id' => $task->id, 'title' => $task->title, 'status' => $task->status,
                'description' => $task->description, 'user' => $task->user?->only(['id', 'name']),
                'participants' => $task->participants->map->only(['id', 'name']),
                'creator' => ($task->created_by ? $task->creator : $task->user)?->only(['id', 'name', 'avatar_url']),
                'worked_on' => $task->worked_on?->toDateString(), 'duration_minutes' => $task->duration_minutes,
                'is_overtime' => $task->is_overtime,
                'activity_type_name' => \Illuminate\Support\Facades\DB::table('activity_types')->where('id', $task->activity_type_id)->value('name'),
                'priority' => $task->priority, 'due_date' => $task->due_date?->toDateString(),
                'project_id' => $task->project_id, 'project_name' => $task->project->name,
                'can_edit' => ! $task->project->isFinalized() && $user->canCreateTasks()
                    && ($user->can('tasks.update_others') || (int) $task->user_id === $user->id),
                'mutable' => ! $task->project->isFinalized() && ($user->can('tasks.update_others') || (int) $task->user_id === $user->id),
            ]),
            'teams' => $projects->filter(fn (Project $project) => $project->active_members_count > 0)->map(fn (Project $project) => [
                'project_id' => $project->id, 'name' => $project->team_name ?: 'Time - '.$project->name,
                'count' => $project->active_members_count,
                'members' => $project->members->map(fn ($member) => $member->user?->only(['id', 'name', 'avatar_url']))->filter()->values(),
            ])->values(),
        ]);
    }

    public function toggleTask(Request $request, Task $task): JsonResponse
    {
        $task = DB::transaction(function () use ($request, $task) {
            $task = Task::lockForUpdate()->findOrFail($task->id);
            $this->authorizeTaskUpdate($request, $task);
            $old = $task->only(['project_id', 'title', 'status']);
            $task->update(['status' => $task->status === 'done' ? 'todo' : 'done']);
            app(\App\Domain\Audit\AuditRecorder::class)->record('task.status_changed', $task, $request->user(), $old, $task->only(['project_id', 'title', 'status']));
            return $task;
        });

        return response()->json(['id' => $task->id, 'status' => $task->status]);
    }
    public function updatePriority(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTaskUpdate($request, $task);
        $data = $request->validate(['priority' => ['required', 'in:low,medium,high']]);
        $task = DB::transaction(function () use ($request, $task, $data) {
            $task = Task::lockForUpdate()->findOrFail($task->id);
            $this->authorizeTaskUpdate($request, $task);
            $old = $task->only(['project_id', 'title', 'priority']);
            $task->update($data);
            if ($task->wasChanged('priority')) {
                app(\App\Domain\Audit\AuditRecorder::class)->record('task.priority_changed', $task, $request->user(), $old, $task->only(['project_id', 'title', 'priority']));
            }
            return $task;
        });

        return response()->json(['id' => $task->id, 'priority' => $task->priority]);
    }

    private function authorizeTaskUpdate(Request $request, Task $task): void
    {
        $user = $request->user();
        abort_unless($task->project && $task->project->company_id === $user->current_company_id, 404);
        abort_if($task->project->isFinalized(), 409, 'Projeto finalizado não aceita alterações em tarefas.');
        abort_unless($user->can('tasks.update_others') || (int) $task->user_id === $user->id, 403);
        if (! $user->can('projects.view_all')) {
            abort_unless($task->project->members()->where('user_id', $user->id)->where('active', true)->exists(), 404);
        }
    }

}
