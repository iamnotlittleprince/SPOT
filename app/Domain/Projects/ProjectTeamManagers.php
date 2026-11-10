<?php

namespace App\Domain\Projects;

use App\Models\Project;
use Illuminate\Validation\ValidationException;

final class ProjectTeamManagers
{
    public static function synchronize(Project $project, string $errorField = 'members'): void
    {
        $managers = $project->members()->where('role', 'manager')->where('active', true)->whereNull('access_revoked_at')
            ->whereIn('user_id', ProjectManagers::query($project->company_id)->select('users.id'));
        if (! $managers->exists()) {
            throw ValidationException::withMessages([$errorField => 'Todo projeto deve ter pelo menos um gerente. Selecione o gerente do projeto.']);
        }
        if (! (clone $managers)->where('user_id', $project->project_manager_id)->exists()) {
            $project->update(['project_manager_id' => $managers->orderBy('id')->value('user_id')]);
        }
    }
}
