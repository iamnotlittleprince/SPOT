<?php
namespace App\Domain\Access;

use App\Models\{Project, ProjectAccessRequest, User};
use Illuminate\Database\Eloquent\Builder;

final class ProjectReportAccess
{
    private array $meetingWindows = [];
    public function canRequest(User $user): bool
    {
        if (! $user->active || $user->account_status !== 'active') return false;
        $roles = $user->profiles()->wherePivot('company_id', $user->current_company_id)->pluck('slug');
        return $roles->contains('analista') && $roles->intersect(['administrador', 'gestor-administrador', 'gestor'])->isEmpty();
    }

    public function participates(User $user, Project $project): bool
    {
        return $project->members()->where('user_id', $user->id)->where('active', true)->whereNull('access_revoked_at')->exists();
    }

    public function isAnalyst(User $user): bool
    {
        return $user->profiles()->wherePivot('company_id', $user->current_company_id)->where('slug', 'analista')->exists();
    }

    public function manages(User $user, Project $project): bool
    {
        return in_array($user->id, [$project->project_manager_id, $project->account_manager_id], true)
            || $project->members()->where('user_id', $user->id)->where('role', 'manager')->where('active', true)->whereNull('access_revoked_at')->exists();
    }

    public function mayGrant(User $reviewer, User $requester, Project $project): bool
    {
        if (! $reviewer->active || $reviewer->account_status !== 'active' || ! $requester->active || $requester->account_status !== 'active'
            || $reviewer->id === $requester->id || $project->trashed() || $project->isFinalized()
            || $project->company_id !== $reviewer->current_company_id || $project->company_id !== $requester->current_company_id) return false;
        if ($this->isAnalyst($requester) && ! $this->participates($requester, $project)) return false;
        $roles = $reviewer->profiles()->wherePivot('company_id', $project->company_id)->pluck('slug');
        if ($roles->intersect(['gestor-administrador', 'administrador'])->isNotEmpty()) {
            return $this->participates($requester, $project);
        }
        $teamManager = $project->members()->where('user_id', $reviewer->id)->where('role', 'manager')->where('active', true)->whereNull('access_revoked_at')->exists();
        return $teamManager || ($roles->contains('gestor') && $this->manages($reviewer, $project));
    }

    public function grants(User $user, string $mode = 'view'): Builder
    {
        return ProjectAccessRequest::query()->where('requester_id', $user->id)->where('company_id', $user->current_company_id)
            ->where('status', 'approved')->whereNull('revoked_at')
            ->when($mode === 'data', fn ($q) => $q->where('access_mode', 'data'))
            ->when($mode === 'visual', fn ($q) => $q->whereIn('access_mode', ['visual', 'data']))
            ->whereHas('project', fn ($q) => $q->whereNull('finalized_at'));
    }

    public function allowedIds(User $user, string $mode = 'view'): array
    {
        if (! $user->active || $user->account_status !== 'active') return [];
        return $this->grants($user, $mode)->with(['project', 'reviewer'])->get()
            ->filter(fn ($grant) => $grant->reviewer && $grant->project && $this->mayGrant($grant->reviewer, $user, $grant->project) && $this->withinWindow($grant))
            ->pluck('project_id')->unique()->values()->all();
    }

    public function state(ProjectAccessRequest $grant): array
    {
        if ($grant->status !== 'approved') return ['effective_status' => $grant->status];
        if (! $grant->project || ! $grant->reviewer || ! $grant->requester || ! $this->mayGrant($grant->reviewer, $grant->requester, $grant->project)) {
            return ['effective_status' => 'unavailable'];
        }
        $window = $this->window($grant);
        if (! $window) return ['effective_status' => 'unavailable'];
        [$start, $end] = $window;
        return ['effective_status' => ! $start ? 'unavailable' : ($start->gt(now()) ? 'scheduled' : ($end && $end->lte(now()) ? 'expired' : 'active')),
            'effective_starts_at' => $start?->toIso8601String(), 'effective_expires_at' => $end?->toIso8601String()];
    }

    private function withinWindow(ProjectAccessRequest $grant): bool
    {
        $window = $this->window($grant);
        if (! $window) return false;
        [$start, $end] = $window;
        return $start && $start->lte(now()) && (! $end || $end->gt(now()));
    }

    private function window(ProjectAccessRequest $grant): ?array
    {
        if ($grant->duration === 'meeting') {
            try {
                $key = $grant->reviewer_id.':'.$grant->teams_event_id;
                if (! array_key_exists($key, $this->meetingWindows)) {
                    $this->meetingWindows[$key] = null;
                    $this->meetingWindows[$key] = app(TeamsAccessWindow::class)->event($grant->reviewer, $grant->teams_event_id);
                }
                $event = $this->meetingWindows[$key];
                return $event ? [$event['start'], $event['end']] : null;
            } catch (\Throwable) {
                // Falha fechada: sem confirmação da Microsoft não há acesso de reunião.
                return null;
            }
        }
        return [$grant->starts_at, $grant->expires_at];
    }

    public function canView(User $user, Project $project): bool
    {
        return $project->company_id === $user->current_company_id && ($user->can('financial.view') || in_array($project->id, $this->allowedIds($user), true));
    }
}
