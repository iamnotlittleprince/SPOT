<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Identity\Actions\CreateActivationLink;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('security.manage');

        $companyId = $request->user()->current_company_id;
        $managedProfiles = ['administrador', 'gestor-administrador', 'gestor', 'analista'];

        $users = User::query()
            ->where('current_company_id', $companyId)
            ->with(['profiles' => fn ($query) => $query
                ->wherePivot('company_id', $companyId)
                ->select('profiles.id', 'profiles.name', 'profiles.slug')])
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id', 'name', 'email', 'google_email', 'microsoft_email',
                'job_title', 'department', 'account_status', 'active', 'current_company_id',
            ]);

        return response()->json([
            'users' => $users->map(fn ($user) => [...$user->toArray(), 'can_create_projects' => $user->hasPermission('projects.create')]),
            'organizations' => DB::table('organizations')
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'profiles' => Profile::query()
                ->whereIn('slug', $managedProfiles)
                ->orderByRaw('CASE slug WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END', array_slice($managedProfiles,0,4))
                ->get(['slug', 'name']),
        ]);
    }

    public function store(Request $request, CreateActivationLink $activation): JsonResponse
    {
        Gate::authorize('security.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'regex:/^\S+\s+\S+/u'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'google_email' => ['nullable', 'email', 'max:255', 'unique:users,google_email'],
            'microsoft_email' => ['nullable', 'email', 'max:255', 'unique:users,microsoft_email'],
            'profile' => ['required', Rule::in(['administrador', 'gestor-administrador', 'gestor', 'analista'])],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'job_title' => ['required', 'string', 'max:120'],
            'department' => ['required', 'string', 'max:120'],
        ]);
        $profile = Profile::where('slug', $data['profile'])->firstOrFail();
        $user = User::create([
            'name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => null,
            'google_email' => isset($data['google_email']) ? mb_strtolower($data['google_email']) : null,
            'microsoft_email' => isset($data['microsoft_email']) ? mb_strtolower($data['microsoft_email']) : null,
            'organization_id' => $data['organization_id'], 'current_company_id' => $request->user()->current_company_id,
            'job_title' => $data['job_title'], 'department' => $data['department'],
            'active' => false, 'account_status' => 'pending_activation',
        ]);
        $user->profiles()->attach($profile, ['company_id' => $request->user()->current_company_id]);
        $result = $activation->execute($user, $request->user());

        return response()->json(['user' => $user, 'activation_url' => url('/first-access/'.$result['token']), 'expires_at' => $result['record']->expires_at], 201);
    }

    public function update(Request $request, User $user, AuditRecorder $audit): JsonResponse
    {
        Gate::authorize('security.manage');
        abort_unless($user->current_company_id === $request->user()->current_company_id, 404);
        $data = $request->validate([
            'can_create_projects' => ['sometimes', 'boolean'],
            'profile' => ['required', Rule::in(['administrador', 'gestor-administrador', 'gestor', 'analista'])],
            'account_status' => ['required', Rule::in(['pending_activation', 'active', 'suspended', 'disabled'])],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);
        $currentIsAdmin = $user->profiles()->whereIn('slug', ['administrador', 'gestor-administrador'])->exists();
        $willBeAdmin = in_array($data['profile'], ['administrador', 'gestor-administrador'], true) && $data['account_status'] === 'active';
        if ($currentIsAdmin && ! $willBeAdmin) {
            $otherAdmins = User::query()->where('current_company_id',$request->user()->current_company_id)->where('id', '!=', $user->id)->where('account_status', 'active')->whereHas('profiles', fn ($query) => $query->whereIn('slug', ['administrador', 'gestor-administrador']))->exists();
            if (! $otherAdmins) {
                throw ValidationException::withMessages(['profile' => 'O último administrador ativo não pode perder o acesso administrativo.']);
            }
        }
        $old = ['can_create_projects' => $user->hasPermission('projects.create'), 'profiles' => $user->profiles->pluck('slug'), 'account_status' => $user->account_status];
        $profile = Profile::where('slug', $data['profile'])->firstOrFail();
        DB::transaction(function () use ($user, $profile, $request) {
            DB::table('company_user_profiles')->where('user_id',$user->id)->where('company_id',$request->user()->current_company_id)->delete();
            $user->profiles()->attach($profile->id,['company_id'=>$request->user()->current_company_id]);
        });
        $user->update(['account_status' => $data['account_status'], 'active' => $data['account_status'] === 'active', ...collect($data)->only(['job_title', 'department'])->all()]);
        $permission = Permission::where('slug', 'projects.create')->firstOrFail();
        if ($data['profile'] !== 'analista') {
            DB::table('user_permission_overrides')->where('user_id', $user->id)->where('permission_id', $permission->id)->delete();
        } elseif (array_key_exists('can_create_projects', $data)) {
            DB::table('user_permission_overrides')->updateOrInsert(
                ['user_id' => $user->id, 'permission_id' => $permission->id],
                ['allowed' => $data['can_create_projects'], 'granted_by' => $request->user()->id, 'reason' => 'Definido pelo administrador em Usuários e acessos.', 'created_at' => now(), 'updated_at' => now()],
            );
        }
        $audit->record('user.access_updated', $user, $request->user(), $old, $data);

        return response()->json(['user' => $user->load('profiles')]);
    }

    /**
     * Remove somente cadastros que nunca foram ativados.
     *
     * Usuários já ativados podem possuir projetos, horas e despesas. Excluí-los
     * apagaria ou deixaria órfão esse histórico; por isso devem ser desativados.
     */
    public function destroy(Request $request, User $user, AuditRecorder $audit): JsonResponse
    {
        Gate::authorize('security.manage');
        abort_unless($user->current_company_id === $request->user()->current_company_id, 404);

        if ($user->account_status !== 'pending_activation') {
            throw ValidationException::withMessages([
                'user' => 'Contas já ativadas não podem ser excluídas. Desative o acesso para preservar o histórico.',
            ]);
        }

        DB::transaction(function () use ($request, $user, $audit): void {
            $audit->record(
                'user.deleted',
                $user,
                $request->user(),
                $user->only(['id', 'name', 'email', 'account_status']),
                [],
            );
            $user->delete();
        });

        return response()->json(status: 204);
    }
}
