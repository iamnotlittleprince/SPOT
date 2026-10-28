<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Task::with('project:id,name')->whereHas('project', fn ($q) => $q->where('user_id', Auth::guard('api')->id()));
        if ($request->filled('project_id')) $query->where('project_id', $request->integer('project_id'));
        return response()->json($query->orderBy('position')->latest()->get());
    }

    public function store(Request $request, AuditRecorder $audit): JsonResponse
    {
        $data = $this->validated($request);
        $this->ownedProject($data['project_id']);
        $task = DB::transaction(function () use ($data, $audit) {
            $task = Task::create([...$data, 'user_id' => Auth::guard('api')->id()]);
            $audit->record('task.created', $task, Auth::guard('api')->user(), [], $task->getAttributes());
            return $task;
        });
        return response()->json($task->load('project:id,name'), 201);
    }

    public function update(Request $request, Task $task, AuditRecorder $audit): JsonResponse
    {
        $this->ownedProject($task->project_id);
        $data = $this->validated($request);
        $this->ownedProject($data['project_id']);
        DB::transaction(function () use ($task, $data, $audit) {
            $old = $task->getAttributes();
            $task->update($data);
            $audit->record('task.updated', $task, Auth::guard('api')->user(), $old, $task->getAttributes());
        });
        return response()->json($task->load('project:id,name'));
    }

    public function destroy(Task $task, AuditRecorder $audit): JsonResponse
    {
        $this->ownedProject($task->project_id);
        DB::transaction(function () use ($task, $audit) {
            $audit->record('task.deleted', $task, Auth::guard('api')->user(), $task->getAttributes());
            $task->delete();
        });
        return response()->json(null, 204);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:180'],
            'status' => ['required', Rule::in(['todo', 'in_progress', 'review', 'done'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'due_date' => ['nullable', 'date'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function ownedProject(int $id): Project
    {
        return Project::whereKey($id)->where('user_id', Auth::guard('api')->id())->firstOrFail();
    }
}
