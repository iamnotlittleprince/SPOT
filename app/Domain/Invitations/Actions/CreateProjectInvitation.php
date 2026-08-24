<?php

namespace App\Domain\Invitations\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateProjectInvitation
{
    public function __construct(private AuditRecorder $audit) {}

    /** @param array<int, string> $permissions @return array{invitation:ProjectInvitation, token:string} */
    public function execute(Project $project, User $actor, string $email, array $permissions, int $expiresInHours, ?int $organizationId, string $projectRole, string $relationshipType, ?string $jobTitle = null, ?string $department = null): array
    {
        return DB::transaction(function () use ($project, $actor, $email, $permissions, $expiresInHours, $organizationId, $projectRole, $relationshipType, $jobTitle, $department): array {
            ProjectInvitation::query()->where('project_id', $project->id)->where('email', $email)
                ->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

            $token = Str::random(64);
            $invitation = ProjectInvitation::create([
                'project_id' => $project->id,
                'email' => mb_strtolower($email),
                'job_title' => $jobTitle,
                'department' => $department,
                'organization_id' => $organizationId,
                'project_role' => $projectRole,
                'relationship_type' => $relationshipType,
                'token_hash' => hash('sha256', $token),
                'permissions' => $permissions,
                'invited_by' => $actor->id,
                'expires_at' => now()->addHours($expiresInHours),
            ]);
            $this->audit->record('invitation.created', $invitation, $actor, [], $invitation->only(['project_id', 'email', 'permissions', 'expires_at']));

            return ['invitation' => $invitation, 'token' => $token];
        });
    }
}
