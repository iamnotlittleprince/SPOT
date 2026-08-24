<?php

namespace App\Http\Controllers\Api;

use App\Domain\WorkLogs\Actions\CreateWorkLog;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WorkLogController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->ensureProjectAccess($request, $project);
        $query = $project->workLogs()->with(['analyst:id,name', 'task:id,title'])->latest('worked_on');
        if (! $request->user()->can('work_logs.view_others')) {
            $query->where('analyst_id', $request->user()->id);
        }

        return response()->json($query->get()->map(fn ($log) => [
            ...$log->toArray(),
            'cost_amount' => $request->user()->can('financial.view') ? $log->costAmount() : null,
            'billable_amount' => $request->user()->can('financial.view') ? $log->billableAmount() : null,
        ]));
    }

    public function store(Request $request, Project $project, CreateWorkLog $action): JsonResponse
    {
        $this->ensureProjectAccess($request, $project);
        $analystId = $request->integer('analyst_id') ?: $request->user()->id;
        Gate::authorize($analystId === $request->user()->id ? 'work_logs.create_own' : 'work_logs.create_for_others');
        $data = $request->validate([
            'analyst_id' => ['nullable', 'integer', 'exists:users,id'],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $project->id)],
            'activity_type_id' => ['required', 'integer', Rule::exists('activity_types', 'id')->where('company_id', $request->user()->current_company_id)],
            'worked_on' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'description' => ['required', 'string', 'max:2000'],
            'is_overtime' => ['sometimes', 'boolean'],
        ]);

        return response()->json($action->execute($project, $request->user(), $data), 201);
    }

    private function ensureProjectAccess(Request $request, Project $project): void
    {
        abort_unless($project->company_id && $project->company_id === $request->user()->current_company_id, 404);
        if ($request->user()->can('projects.view_all')) return;
        Gate::authorize('projects.view_assigned');
        abort_unless($project->members()->where('user_id', $request->user()->id)->where('active', true)->exists(), 404);
    }
}
