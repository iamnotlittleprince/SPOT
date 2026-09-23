<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Invitations\Actions\AcceptProjectInvitation;
use App\Domain\Invitations\Actions\CreateProjectInvitation;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Jobs\SendAccessEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProjectInvitationController extends Controller
{
    private const GUEST_PERMISSIONS = ['tasks.view', 'files.view'];
    private const MEMBER_PERMISSIONS = ['tasks.view', 'tasks.comment', 'files.view', 'files.upload'];

    public function store(Request $request, Project $project, CreateProjectInvitation $action): JsonResponse
    {
        Gate::authorize('invitations.create');
        $this->ensureCompany($request, $project);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(self::MEMBER_PERMISSIONS)],
            'expires_in_hours' => ['sometimes', 'integer', 'between:1,168'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'project_role' => ['required', Rule::in(['analyst', 'manager', 'guest'])],
            'relationship_type' => ['required', Rule::in(['cpt_internal_analyst', 'external_analyst', 'responsible_manager', 'guest_client', 'guest_analyst', 'guest_manager'])],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'requires_password_creation' => ['sometimes', 'boolean'],
        ]);
        if ($request->hasAny(['job_title', 'department'])) {
            Gate::authorize('security.manage');
        }
        if ($data['project_role'] === 'guest' && (filled($data['job_title'] ?? null) || filled($data['department'] ?? null))) {
            throw ValidationException::withMessages(['job_title' => 'Cargo e departamento não se aplicam ao papel Convidado.']);
        }
        $relationshipsByRole = [
            'analyst' => ['cpt_internal_analyst', 'external_analyst'],
            'manager' => ['responsible_manager'],
            'guest' => ['guest_client', 'guest_analyst', 'guest_manager'],
        ];
        if (! in_array($data['relationship_type'], $relationshipsByRole[$data['project_role']], true)) {
            throw ValidationException::withMessages(['relationship_type' => 'O vínculo selecionado não corresponde ao papel no projeto.']);
        }
        if ($data['project_role'] === 'guest' && array_diff($data['permissions'] ?? [], self::GUEST_PERMISSIONS)) {
            throw ValidationException::withMessages(['permissions' => 'Convidados podem receber somente permissões de visualização.']);
        }
        if ($data['relationship_type'] === 'external_analyst' && blank($data['organization_name'] ?? null)) {
            throw ValidationException::withMessages(['organization_name' => 'Informe a empresa do analista externo.']);
        }

        $organizationId = match ($data['relationship_type']) {
            'cpt_internal_analyst', 'responsible_manager' => $request->user()->organization_id,
            'external_analyst' => DB::table('organizations')->whereRaw('lower(name) = ?', [mb_strtolower(trim($data['organization_name']))])->value('id')
                ?? DB::table('organizations')->insertGetId(['name' => trim($data['organization_name']), 'type' => 'partner', 'active' => true, 'created_at' => now(), 'updated_at' => now()]),
            default => null,
        };
        $defaultPermissions = $data['project_role'] === 'guest' ? ['tasks.view', 'files.view'] : self::MEMBER_PERMISSIONS;
        $result = $action->execute($project, $request->user(), $data['email'], $data['permissions'] ?? $defaultPermissions, $data['expires_in_hours'] ?? 72, $organizationId, $data['project_role'], $data['relationship_type'], $data['job_title'] ?? null, $data['department'] ?? null, $data['requires_password_creation'] ?? true);
        $acceptUrl = url('/accept-invitation/'.$result['token']);
        SendAccessEmail::dispatch('invitation', $result['invitation']->id, $acceptUrl)->afterCommit();

        return response()->json([
            'invitation' => $result['invitation'],
            'accept_url' => $acceptUrl,
            'message' => 'Convite criado. O e-mail será enviado em breve.',
        ], 201);
    }

    public function showAcceptance(string $token): JsonResponse
    {
        $invitation = ProjectInvitation::query()->where('token_hash', hash('sha256', $token))->first();
        abort_unless($invitation?->isUsable(), 404, 'Convite inválido, expirado, revogado ou já utilizado.');

        return response()->json([
            'requires_password_creation' => $invitation->requires_password_creation,
        ]);
    }

    public function accept(Request $request, string $token, AcceptProjectInvitation $action): JsonResponse
    {
        $needsRegistration = $request->user() === null;
        $invitation = ProjectInvitation::query()->where('token_hash', hash('sha256', $token))->first();
        $requiresPassword = $needsRegistration || (bool) $invitation?->requires_password_creation;
        $data = $request->validate([
            'name' => [Rule::requiredIf($needsRegistration), 'nullable', 'string', 'max:120'],
            'password' => [Rule::requiredIf($requiresPassword), 'nullable', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);
        $user = $action->execute($token, $request->user(), $data['name'] ?? null, $data['password'] ?? null);
        if (! $request->user()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return response()->json(['user' => $user, 'project_access_granted' => true]);
    }

    public function revoke(Request $request, ProjectInvitation $invitation, AuditRecorder $audit): JsonResponse
    {
        Gate::authorize('invitations.create');
        $this->ensureCompany($request, $invitation->project);
        abort_if($invitation->accepted_at, 409, 'Convite já utilizado não pode ser revogado.');
        $invitation->forceFill(['revoked_at' => now()])->save();
        $audit->record('invitation.revoked', $invitation, $request->user());

        return response()->json(['message' => 'Convite revogado.']);
    }

    private function ensureCompany(Request $request, Project $project): void
    {
        abort_unless($project->company_id && $project->company_id === $request->user()->current_company_id, 404);
    }
}
