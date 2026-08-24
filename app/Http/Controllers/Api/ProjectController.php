<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Audit\AuditRecorder;
use App\Http\Requests\SaveProjectRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->is('api/v1/*')) {
            $user = $request->user();
            $query = Project::query()->where('company_id', $user->current_company_id);
            if (! $user->can('projects.view_all')) {
                Gate::authorize('projects.view_assigned');
                $query->whereHas('members', fn ($member) => $member->where('user_id', $user->id)
                    ->where(fn ($access) => $access->where('active', true)->orWhereNotNull('projects.finalized_at')));
            }

            $projects = $query->withCount('tasks')->latest()->get();
            $this->protectFinancialFields($request, $projects);
            return response()->json($projects);
        }

        return response()->json(Auth::guard('api')->user()->projects()->withCount('tasks')->latest()->get());
    }

    public function store(SaveProjectRequest $request, AuditRecorder $audit): JsonResponse
    {
        if ($request->is('api/v1/*')) {
            Gate::authorize('projects.create');
            $project = Project::create([
                ...$request->validated(),
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->current_company_id,
            ]);
            $audit->record('project.created', $project, $request->user(), [], $project->getAttributes());

            $project->loadCount('tasks');
            $this->protectFinancialFields($request, collect([$project]));
            return response()->json($project, 201);
        }

        $project = Auth::guard('api')->user()->projects()->create($request->validated());
        return response()->json($project->loadCount('tasks'), 201);
    }

    public function show(Project $project): JsonResponse
    {
        if (request()->is('api/v1/*')) {
            $historicalOnly = $this->authorizeVersionedProject(request(), $project, 'projects.view_assigned');
            if ($historicalOnly) {
                $project->loadCount('tasks');
            } else {
                $project->load(['tasks.user:id,name'])->loadCount('tasks');
            }
            $this->protectFinancialFields(request(), collect([$project]));
            return response()->json($project);
        }

        $this->authorizeProject($project);
        return response()->json($project->load(['tasks.user:id,name'])->loadCount('tasks'));
    }

    public function update(SaveProjectRequest $request, Project $project, AuditRecorder $audit): JsonResponse
    {
        if ($request->is('api/v1/*')) {
            Gate::authorize('projects.update');
            $this->ensureCompany($request, $project);
            abort_if($project->isFinalized(), 409, 'Projeto finalizado não pode ser alterado.');
            $old = $project->getOriginal();
            $project->update($request->validated());
            $audit->record('project.updated', $project, $request->user(), $old, $project->getAttributes());
            $project->loadCount('tasks');
            $this->protectFinancialFields($request, collect([$project]));
            return response()->json($project);
        }

        $this->authorizeProject($project);
        $project->update($request->validated());
        return response()->json($project->loadCount('tasks'));
    }

    public function destroy(Project $project): JsonResponse
    {
        if (request()->is('api/v1/*')) {
            abort(405, 'Use o endpoint de exclusão governada com uma justificativa.');
        }

        $this->authorizeProject($project);
        $project->delete();
        return response()->json(null, 204);
    }

    private function authorizeProject(Project $project): void
    {
        abort_unless($project->user_id === Auth::guard('api')->id(), 403);
    }

    private function ensureCompany(Request $request, Project $project): void
    {
        abort_unless($project->company_id && $project->company_id === $request->user()->current_company_id, 404);
    }

    private function authorizeVersionedProject(Request $request, Project $project, string $assignedPermission): bool
    {
        $this->ensureCompany($request, $project);
        if ($request->user()->can('projects.view_all')) return false;
        Gate::authorize($assignedPermission);
        $member = $project->members()->where('user_id', $request->user()->id)->first();
        abort_unless($member && ($member->active || $project->isFinalized()), 404);

        return ! $member->active;
    }

    private function protectFinancialFields(Request $request, $projects): void
    {
        if ($request->user()->can('financial.view')) return;
        $hidden = ['contract_value', 'commission_rate', 'commission_basis', 'estimated_labor_cost', 'estimated_additional_cost'];
        foreach ($projects as $project) $project->makeHidden($hidden);
    }
}
