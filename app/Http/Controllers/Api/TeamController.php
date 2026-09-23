<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Project::query()->where('company_id', $user->current_company_id)
            ->whereNull('deleted_at')
            ->whereHas('members', fn ($members) => $members->where('active', true))
            ->with(['members' => fn ($members) => $members->where('active', true)->with('user:id,name,email,avatar_url')]);

        if (! $user->can('projects.view_all')) {
            Gate::authorize('projects.view_assigned');
            $query->whereHas('members', fn ($members) => $members->where('user_id', $user->id)->where('active', true));
        }

        return response()->json($query->orderBy('name')->get()->map(fn (Project $project) => $this->team($project, $request)));
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->viewable($request, $project);
        $project->load(['members' => fn ($members) => $members->where('active', true)->with('user:id,name,email,avatar_url')]);

        return response()->json([
            ...$this->team($project, $request),
            'available_users' => User::query()->where('current_company_id', $request->user()->current_company_id)
                ->where('active', true)->orderBy('name')->get(['id', 'name', 'email', 'avatar_url']),
        ]);
    }

    public function update(Request $request, Project $project, AuditRecorder $audit): JsonResponse
    {
        Gate::authorize('projects.update');
        $this->sameCompany($request, $project);
        abort_if($project->isFinalized(), 409, 'Projeto finalizado não permite editar o time.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'members' => ['present', 'array'],
            'members.*.user_id' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('current_company_id', $request->user()->current_company_id)->where('active', true)],
            'members.*.role' => ['required', Rule::in(['manager', 'analyst', 'guest'])],
        ]);

        $old = ['name' => $project->team_name, 'members' => $project->members()->where('active', true)->get(['user_id', 'role'])->toArray()];
        DB::transaction(function () use ($project, $request, $data): void {
            $project->update(['team_name' => trim($data['name'])]);
            $selected = collect($data['members'])->pluck('user_id');
            $project->members()->where('active', true)->whereNotIn('user_id', $selected)->update(['active' => false, 'access_revoked_at' => now()]);
            foreach ($data['members'] as $member) {
                $project->members()->updateOrCreate(['user_id' => $member['user_id']], [
                    'role' => $member['role'],
                    'relationship_type' => match ($member['role']) {
                        'manager' => 'responsible_manager',
                        'guest' => 'guest_client',
                        default => 'cpt_internal_analyst',
                    },
                    'permissions' => $member['role'] === 'guest' ? ['tasks.view', 'files.view'] : ['tasks.view', 'tasks.comment', 'files.view', 'files.upload'],
                    'organization_id' => $request->user()->organization_id,
                    'active' => true, 'access_revoked_at' => null,
                    'assigned_by' => $request->user()->id, 'assigned_at' => now(),
                ]);
            }
        });
        $new = ['name' => $project->fresh()->team_name, 'members' => $project->members()->where('active', true)->get(['user_id', 'role'])->toArray()];
        $audit->record('project.team_updated', $project, $request->user(), $old, $new);

        return $this->show($request, $project->fresh());
    }

    private function team(Project $project, Request $request): array
    {
        return [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'name' => $project->team_name ?: 'Time - '.$project->name,
            'finalized' => $project->isFinalized(),
            'can_edit' => $request->user()->can('projects.update') && ! $project->isFinalized(),
            'members' => $project->members->map(fn ($member) => [
                'user_id' => $member->user_id, 'role' => $member->role,
                'name' => $member->user?->name, 'email' => $member->user?->email, 'avatar_url' => $member->user?->avatar_url,
            ])->filter(fn ($member) => $member['name'])->values(),
        ];
    }

    private function viewable(Request $request, Project $project): void
    {
        $this->sameCompany($request, $project);
        if (! $request->user()->can('projects.view_all')) {
            abort_unless($project->members()->where('user_id', $request->user()->id)->where('active', true)->exists(), 404);
        }
    }

    private function sameCompany(Request $request, Project $project): void
    {
        abort_unless($project->company_id === $request->user()->current_company_id, 404);
    }
}
