<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinalizeProject
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(Project $project, User $actor, string $reason): Project
    {
        return DB::transaction(function () use ($project, $actor, $reason): Project {
            $project = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            if ($project->isFinalized()) {
                throw ValidationException::withMessages(['project' => 'O projeto já está finalizado.']);
            }

            $old = [...$project->getAttributes(), 'members' => $project->members()->get()->toArray()];
            $project->forceFill(['finalized_at' => now(), 'finalized_by' => $actor->getKey(), 'finalization_reason' => $reason])->save();
            $project->members()->where('active', true)->update([
                'active' => false,
                'permissions' => json_encode([]),
                'access_revoked_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit->record('project.finalized', $project, $actor, $old, [...$project->getAttributes(), 'reason' => $reason, 'members' => $project->members()->get()->toArray()]);

            return $project->refresh();
        });
    }
}
