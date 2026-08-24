<?php

namespace App\Http\Controllers\Api;

use App\Domain\Identity\Actions\CreateActivationLink;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use App\Domain\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
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
        $data = $request->validate([
            'profile' => ['required', Rule::in(['administrador', 'gestor-administrador', 'gestor', 'analista'])],
            'account_status' => ['required', Rule::in(['pending_activation', 'active', 'suspended', 'disabled'])],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);
        $currentIsAdmin = $user->profiles()->whereIn('slug', ['administrador', 'gestor-administrador'])->exists();
        $willBeAdmin = in_array($data['profile'], ['administrador', 'gestor-administrador'], true) && $data['account_status'] === 'active';
        if ($currentIsAdmin && ! $willBeAdmin) {
            $otherAdmins = User::query()->where('id', '!=', $user->id)->where('account_status', 'active')->whereHas('profiles', fn ($query) => $query->whereIn('slug', ['administrador', 'gestor-administrador']))->exists();
            if (! $otherAdmins) throw ValidationException::withMessages(['profile' => 'O último administrador ativo não pode perder o acesso administrativo.']);
        }
        $old = ['profiles' => $user->profiles->pluck('slug'), 'account_status' => $user->account_status];
        $profile = Profile::where('slug', $data['profile'])->firstOrFail();
        $user->profiles()->sync([$profile->id => ['company_id' => $request->user()->current_company_id]]);
        $user->update(['account_status' => $data['account_status'], 'active' => $data['account_status'] === 'active', ...collect($data)->only(['job_title', 'department'])->all()]);
        $audit->record('user.access_updated', $user, $request->user(), $old, $data);
        return response()->json(['user' => $user->load('profiles')]);
    }
}
