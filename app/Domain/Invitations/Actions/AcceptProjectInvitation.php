<?php

namespace App\Domain\Invitations\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Models\Profile;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptProjectInvitation
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(string $token, ?User $authenticatedUser, ?string $name, ?string $password): User
    {
        return DB::transaction(function () use ($token, $authenticatedUser, $name, $password): User {
            $invitation = ProjectInvitation::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $invitation || ! $invitation->isUsable()) {
                throw ValidationException::withMessages(['token' => 'Convite inválido, expirado, revogado ou já utilizado.']);
            }

            $existing = User::query()->where('email', $invitation->email)->first();
            if ($existing && (! $authenticatedUser || $authenticatedUser->id !== $existing->id)) {
                throw ValidationException::withMessages(['email' => 'Esta conta já existe. Entre com o mesmo e-mail antes de aceitar o convite.']);
            }
            if ($authenticatedUser && mb_strtolower($authenticatedUser->email) !== mb_strtolower($invitation->email)) {
                throw ValidationException::withMessages(['email' => 'O convite pertence a outro endereço de e-mail.']);
            }

            $user = $existing ?? User::create(['name' => $name, 'email' => $invitation->email, 'password' => $password, 'email_verified_at' => now(), 'organization_id' => $invitation->organization_id, 'job_title' => $invitation->job_title, 'department' => $invitation->department, 'account_status' => 'active']);
            $companyId = $invitation->project->company_id;
            $user->update(['current_company_id' => $companyId, 'active' => true]);
            // O papel do convite é restrito ao projeto. Ele nunca promove o
            // usuário a Analista ou Gestor no âmbito global da empresa.
            $guestProfile = Profile::query()->where('slug', 'convidado')->firstOrFail();
            $user->profiles()->syncWithoutDetaching([$guestProfile->id => ['company_id' => $companyId]]);
            $invitation->project->members()->updateOrCreate(['user_id' => $user->id], ['role' => $invitation->project_role, 'relationship_type' => $invitation->relationship_type, 'organization_id' => $invitation->organization_id, 'permissions' => $invitation->permissions, 'active' => true, 'access_revoked_at' => null, 'assigned_by' => $invitation->invited_by, 'assigned_at' => now()]);
            $invitation->forceFill(['accepted_at' => now(), 'accepted_by' => $user->id])->save();
            $this->audit->record('invitation.accepted', $invitation, $user, [], ['project_id' => $invitation->project_id]);

            return $user;
        });
    }
}
