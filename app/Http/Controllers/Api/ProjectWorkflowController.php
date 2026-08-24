<?php

namespace App\Http\Controllers\Api;

use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\FinalizeProject;
use App\Domain\Projects\Actions\ReopenProject;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectWorkflowController extends Controller
{
    public function finalize(Request $request, Project $project, FinalizeProject $action): JsonResponse
    {
        Gate::authorize('projects.finalize');
        $this->ensureSameCompany($request, $project);
        $data = $this->reason($request);

        return response()->json($action->execute($project, $request->user(), $data['reason']));
    }

    public function reopen(Request $request, Project $project, ReopenProject $action): JsonResponse
    {
        Gate::authorize('projects.reopen');
        $this->ensureSameCompany($request, $project);
        $data = $this->reason($request);

        return response()->json($action->execute($project, $request->user(), $data['reason']));
    }

    public function destroy(Request $request, Project $project, DeleteProject $action): JsonResponse
    {
        Gate::authorize('projects.delete');
        $this->ensureSameCompany($request, $project);
        $data = $this->reason($request);
        $action->execute($project, $request->user(), $data['reason']);

        return response()->json(null, 204);
    }

    /** @return array{reason:string} */
    private function reason(Request $request): array
    {
        return $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
    }

    private function ensureSameCompany(Request $request, Project $project): void
    {
        abort_unless($project->company_id && $project->company_id === $request->user()->current_company_id, 404);
    }
}
