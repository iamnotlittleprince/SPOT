<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskV1Controller extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tasks = $this->tasks($user)->with(['project:id,name,client_id,client_name,finalized_at', 'user:id,name'])
            ->orderBy('position')->orderByDesc('id')->get();

        return response()->json([
            'tasks' => $tasks->map(fn (Task $task) => $this->payload($task, $user)),
            'projects' => $this->projects($user)->whereNull('finalized_at')->orderBy('name')->get(['id', 'name', 'client_id', 'client_name']),
            'can_create' => $user->canCreateTasks(),
            'clients' => DB::table('clients')->where('company_id', $user->current_company_id)->whereIn('id', $this->projects($user)->select('client_id'))->get(['id', 'name']),
            'current_user' => $user->only(['id', 'name']),
            'can_assign_others' => $user->can('work_logs.create_for_others'),
            'analysts' => $user->can('work_logs.create_for_others') ? DB::table('project_members')
                ->join('users', 'users.id', '=', 'project_members.user_id')
                ->whereIn('project_id', $this->projects($user)->select('id'))
                ->where('project_members.active', true)->where('users.active', true)
                ->get(['users.id', 'users.name', 'project_members.project_id'])
                ->filter(fn ($row) => User::find($row->id)?->canCreateTasks())->values() : [],
            'activity_types' => DB::table('activity_types')->where('company_id', $user->current_company_id)->where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, AuditRecorder $audit): JsonResponse
    {
        abort_unless($request->user()->canCreateTasks(), 403);
        $data = $this->validated($request);
        $user = $request->user();
        $task = DB::transaction(function () use ($data, $user, $audit) {
            $this->writableProject($user, $data['project_id']);
            $this->ensureAnalyst($user, $data);
            $task = Task::create([...$data, 'created_by' => $user->id]);
            app(\App\Domain\Tasks\TaskCosting::class)->capture($task);
            $audit->record('task.created', $task, $user, [], $task->getAttributes());

            return $task;
        });

        return response()->json($this->payload($task, $user), 201);
    }

    public function update(Request $request, Task $task, AuditRecorder $audit): JsonResponse
    {
        $user = $request->user();
        $data = $this->validated($request);
        $updated = DB::transaction(function () use ($task, $user, $data, $audit) {
            $task = $this->tasks($user)->lockForUpdate()->findOrFail($task->id);
            $this->writableProject($user, $task->project_id);
            $this->writableProject($user, $data['project_id']);
            abort_unless($this->canChange($task, $user, 'tasks.manage'), 403);
            $this->ensureAnalyst($user, $data, $task);
            $old = $task->getAttributes();
            $reprice = $task->cost_rate_snapshot === null || (int) $task->project_id !== (int) $data['project_id'] || (int) $task->user_id !== (int) $data['user_id'] || $task->worked_on?->toDateString() !== $data['worked_on'] || $task->is_overtime !== (bool) $data['is_overtime'];
            $task->update($data);
            if ($reprice) app(\App\Domain\Tasks\TaskCosting::class)->capture($task);
            $audit->record('task.updated', $task, $user, $old, $task->getAttributes());

            return $task;
        });

        return response()->json($this->payload($updated, $user));
    }

    public function destroy(Request $request, Task $task, AuditRecorder $audit): JsonResponse
    {
        $user = $request->user();
        DB::transaction(function () use ($task, $user, $audit) {
            $task = $this->tasks($user)->lockForUpdate()->findOrFail($task->id);
            $this->writableProject($user, $task->project_id);
            abort_unless($this->canChange($task, $user, 'tasks.delete'), 403);
            $audit->record('task.deleted', $task, $user, $task->getAttributes());
            $task->delete();
        });

        return response()->json(null, 204);
    }

    private function projects(User $user): Builder
    {
        $query = Project::query()->where('company_id', $user->current_company_id);
        if (! $user->can('projects.view_all')) {
            Gate::authorize('projects.view_assigned');
            $query->whereHas('members', fn ($members) => $members->where('user_id', $user->id)->where(fn ($access) => $access->where('active', true)->orWhereNotNull('projects.finalized_at')));
        }

        return $query;
    }

    private function tasks(User $user): Builder
    {
        return Task::query()->whereIn('project_id', $this->projects($user)->select('id'))
            ->when(! $user->can('tasks.view_others'), fn ($query) => $query->where(fn ($own) => $own->where('created_by', $user->id)->orWhere(fn ($legacy) => $legacy->whereNull('created_by')->where('user_id', $user->id))));
    }

    private function writableProject(User $user, int $id): Project
    {
        $project = $this->projects($user)->lockForUpdate()->findOrFail($id);
        abort_if($project->isFinalized(), 409, 'Projeto finalizado não aceita alterações em tarefas.');

        return $project;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'title' => ['sometimes', 'string', 'max:180'],
            'analyst_id' => ['required', 'integer'],
            'worked_on' => ['required', 'date_format:Y-m-d'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'activity_type_id' => ['required', 'integer', Rule::exists('activity_types', 'id')->where('company_id', $request->user()->current_company_id)->where('active', true)],
            'description' => ['required', 'string', 'max:2000'],
            'is_overtime' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['todo', 'in_progress', 'review', 'done'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $data['user_id'] = $data['analyst_id'];
        unset($data['analyst_id']);
        $data['title'] = $data['title'] ?? mb_substr($data['description'], 0, 180);

        return $data;
    }

    private function canChange(Task $task, User $user, string $permission): bool
    {
        return $user->canCreateTasks() && (($user->can($permission === 'tasks.delete' ? 'tasks.delete_others' : 'tasks.update_others')) || (int) ($task->created_by ?? $task->user_id) === $user->id);
    }

    private function ensureAnalyst(User $user, array $data, ?Task $task = null): void
    {
        $id = (int) $data['user_id'];
        if ($id !== $user->id && (! $task || $id !== $task->user_id)) {
            Gate::authorize('work_logs.create_for_others');
        }
        $analyst = User::find($id);
        abort_unless($analyst && $analyst->current_company_id === $user->current_company_id && $analyst->canCreateTasks(), 422, 'Analista inválido.');
        if ($id !== $user->id) {
            abort_unless(DB::table('project_members')->where('project_id', $data['project_id'])->where('user_id', $id)->where('active', true)->exists(), 422, 'Analista não atribuído ao projeto.');
        }
    }

    private function payload(Task $task, User $user): array
    {
        $task->loadMissing(['project:id,name,client_id,client_name,finalized_at', 'user:id,name']);

        return [
            ...collect($task->toArray())->except($user->can('financial.view') ? [] : ['cost_rate_snapshot', 'sale_rate_snapshot'])->all(),
            'due_date' => $task->due_date?->toDateString(),
            'worked_on' => $task->worked_on?->toDateString(),
            'activity_type_name' => DB::table('activity_types')->where('id', $task->activity_type_id)->value('name'),
            'can_edit' => ! $task->project->isFinalized() && $this->canChange($task, $user, 'tasks.manage'),
            'can_delete' => ! $task->project->isFinalized() && $this->canChange($task, $user, 'tasks.delete'),
        ];
    }
}
