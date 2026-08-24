<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReopenProject
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(Project $project, User $actor, string $reason): Project
    {
        return DB::transaction(function () use ($project, $actor, $reason): Project {
            $project = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            if (! $project->isFinalized()) {
                throw ValidationException::withMessages(['project' => 'O projeto não está finalizado.']);
            }

            $old = ['finalized_at' => $project->finalized_at, 'finalized_by' => $project->finalized_by];
            $project->forceFill(['finalized_at' => null, 'finalized_by' => null, 'finalization_reason' => null])->save();
            $this->audit->record('project.reopened', $project, $actor, $old, ['reason' => $reason]);

            return $project->refresh();
        });
    }
}
