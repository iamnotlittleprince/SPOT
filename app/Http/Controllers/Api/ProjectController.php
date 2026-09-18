<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Projects\ProjectFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProjectRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
            foreach ($projects as $item) {
                $item->setAttribute('client_name', DB::table('clients')->where('id', $item->client_id)->value('name') ?? $item->client_name);
            }
            $this->protectFinancialFields($request, $projects);

            return response()->json($projects);
        }

        return response()->json(Auth::guard('api')->user()->projects()->withCount('tasks')->latest()->get());
    }

    public function store(SaveProjectRequest $request, AuditRecorder $audit): JsonResponse
    {
        if ($request->is('api/v1/*')) {
            Gate::authorize('projects.create');
            $project = DB::transaction(function () use ($request, $audit) {
                $project = Project::create([
                    ...$this->projectData($request),
                    'user_id' => $request->user()->id,
                    'company_id' => $request->user()->current_company_id,
                ]);
                $this->saveTaxes($request, $project);
                $audit->record('project.created', $project, $request->user(), [], $project->getAttributes());

                return $project;
            });

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
                $project->load(['tasks' => fn ($q) => $q->when(! request()->user()->can('tasks.view_others'), fn ($q) => $q->where(fn ($own) => $own->where('created_by', request()->user()->id)->orWhere(fn ($legacy) => $legacy->whereNull('created_by')->where('user_id', request()->user()->id)))), 'tasks.user:id,name'])->loadCount('tasks');
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
            DB::transaction(function () use ($request, $project, $audit) {
                $project = Project::lockForUpdate()->findOrFail($project->id);
                abort_if($project->isFinalized(), 409, 'Projeto finalizado não pode ser alterado.');
                $old = $project->getOriginal();
                $project->update($this->projectData($request));
                $this->saveTaxes($request, $project);
                $audit->record('project.updated', $project, $request->user(), $old, $project->getAttributes());
            });
            $project->refresh();
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
        if ($request->user()->can('projects.view_all')) {
            return false;
        }
        Gate::authorize($assignedPermission);
        $member = $project->members()->where('user_id', $request->user()->id)->first();
        abort_unless($member && ($member->active || $project->isFinalized()), 404);

        return ! $member->active;
    }

    private function projectData(SaveProjectRequest $request): array
    {
        $data=collect($request->validated())->except('taxes')->all();
        if(isset($data['project_status_id'])) {
            $slug=DB::table('project_statuses')->where('id',$data['project_status_id'])->value('slug');
            $mapped=['planning'=>'planning','in-progress'=>'in_progress','completed'=>'completed','cancelled'=>'cancelled','paused'=>'paused'];
            if(isset($mapped[$slug]))$data['status']=$mapped[$slug];
        }
        return $data;
    }

    private function saveTaxes(Request $request, Project $project): void
    {
        if ($request->has('taxes')) {
            $project->taxes()->delete();
            foreach ($request->validated('taxes') as $tax) {
                $project->taxes()->create([...$tax, 'calculated_amount' => 0]);
            }
        }
    }

    private function protectFinancialFields(Request $request, $projects): void
    {
        $hiddenFields = array_keys(array_filter(ProjectFields::access($request->user()), fn ($rights) => ! $rights['view']));
        foreach ($projects as $project) {
            $project->makeHidden($hiddenFields);
        }
        if ($request->user()->can('financial.view')) {
            return;
        }
        $hidden = ['contract_value', 'commission_rate', 'commission_basis', 'estimated_labor_cost', 'estimated_additional_cost'];
        foreach ($projects as $project) {
            $project->makeHidden($hidden);
            if ($project->relationLoaded('tasks')) {
                $project->tasks->each->makeHidden(['cost_rate_snapshot', 'sale_rate_snapshot']);
            }
        }
    }
}
