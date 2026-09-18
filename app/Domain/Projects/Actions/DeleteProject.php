<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteProject
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(Project $project, User $actor, string $reason): void
    {
        DB::transaction(function () use ($project, $actor, $reason): void {
            $project = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            abort_if($project->isFinalized(), 409, 'Reabra o projeto antes de excluir.');
            $project->forceFill(['deleted_by' => $actor->getKey(), 'deletion_reason' => $reason])->save();
            $this->audit->record('project.deleted', $project, $actor, [], ['reason' => $reason]);
            $project->delete();
        });
    }
}
