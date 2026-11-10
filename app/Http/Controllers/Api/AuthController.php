<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use DateTimeImmutable;
use DateTimeZone;
use App\Support\TwoFactor;

class AuthController extends Controller
{
    public function loginSession(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'locale' => ['nullable', \Illuminate\Validation\Rule::in(['pt', 'en', 'es'])],
            'password' => ['required', 'string'],
            'two_factor_code' => ['nullable', 'string', 'max:20'],
        ]);

        $locale = $credentials['locale'] ?? null;
        unset($credentials['locale']);
        $twoFactorCode = $credentials['two_factor_code'] ?? null;
        unset($credentials['two_factor_code']);

        if (! Auth::guard('web')->validate([...$credentials, 'active' => true, 'account_status' => 'active'])) {
            return $this->loginFailure($credentials['email']);
        }

        $user = Auth::guard('web')->getLastAttempted();
        if (! TwoFactor::verify($user, $twoFactorCode)) {
            return response()->json(['message' => 'Informe o código do autenticador ou um código de recuperação.', 'two_factor_required' => true], 422);
        }

        if ($locale) $user->forceFill(['preferred_locale' => $locale])->saveQuietly();
        $request->session()->forget('two_factor_challenge');
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        app(\App\Domain\Audit\AuditRecorder::class)->record('user.login',$user,$user,[],['provider'=>'password']);

        return response()->json(['user' => $this->sessionUser($request->user())]);
    }

    public function logoutSession(Request $request): JsonResponse
    {
        if($request->user()) app(\App\Domain\Audit\AuditRecorder::class)->record('user.logout',$request->user(),$request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function meSession(Request $request): JsonResponse
    {
        return response()->json($this->sessionUser($request->user()));
    }

    public function myProjects(Request $request): JsonResponse
    {
        $user = $request->user();
        $projects = Project::query()
            ->where('company_id', $user->current_company_id)
            ->where(function ($query) use ($user): void {
                $query->where('account_manager_id', $user->id)
                    ->orWhere('project_manager_id', $user->id)
                    ->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id));
            })
            ->withCount('tasks')
            ->orderByRaw('finalized_at is not null')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'progress', 'status', 'due_date', 'finalized_at']);

        return response()->json($projects);
    }

    public function timezones(): JsonResponse
    {
        $now = new DateTimeImmutable();
        $timezones = collect(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC))
            ->map(function (string $identifier) use ($now): array {
                $zone = new DateTimeZone($identifier);
                $offset = $zone->getOffset($now);
                $sign = $offset < 0 ? '-' : '+';
                $absolute = abs($offset);
                $formattedOffset = sprintf('%s%02d:%02d', $sign, intdiv($absolute, 3600), intdiv($absolute % 3600, 60));

                return ['id' => $identifier, 'label' => "(UTC{$formattedOffset}) {$identifier}", 'offset' => $offset];
            })
            ->sortBy([['offset', 'asc'], ['id', 'asc']])
            ->values()
            ->map(fn (array $timezone) => ['id' => $timezone['id'], 'label' => $timezone['label']]);

        return response()->json($timezones);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'two_factor_code' => ['nullable', 'string', 'max:20'],
        ]);
        $code = $credentials['two_factor_code'] ?? null;
        unset($credentials['two_factor_code']);
        $guard = Auth::guard('api');
        if (! $guard->validate([...$credentials, 'active' => true, 'account_status' => 'active'])) {
            return $this->loginFailure($credentials['email']);
        }
        $user = $guard->getLastAttempted();
        if (! TwoFactor::verify($user, $code)) {
            return response()->json(['message' => 'Informe o código do autenticador ou um código de recuperação.', 'two_factor_required' => true], 422);
        }
        return $this->respondWithToken($guard->login($user), $user);
    }

    public function challengeTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $pending = $request->session()->get('two_factor_challenge');
        if (! $pending || $pending['expires'] < time()) {
            $request->session()->forget('two_factor_challenge');
            return response()->json(['message' => 'Sua sessão expirou'], 419);
        }
        $user = User::find($pending['user_id']);
        if (! $user || ! $user->active || $user->account_status !== 'active' || ! TwoFactor::verify($user, $data['code'])) {
            return response()->json(['message' => 'Código de autenticação inválido.'], 422);
        }
        $request->session()->forget('two_factor_challenge');
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        app(\App\Domain\Audit\AuditRecorder::class)->record('user.login', $user, $user, [], ['provider' => $pending['provider']]);
        return response()->json(['user' => $this->sessionUser($user)]);
    }

    private function loginFailure(string $email): JsonResponse
    {
        $awaitingFirstAccess = User::query()
            ->where('email', $email)
            ->where('account_status', 'pending_activation')
            ->whereNull('password')
            ->exists();

        if ($awaitingFirstAccess) {
            return response()->json([
                'message' => 'Sua conta aguarda ativação e ainda não possui senha. Abra o link de primeiro acesso fornecido pelo administrador para criar sua senha e ativar a conta.',
                'activation_required' => true,
            ], 401);
        }

        return response()->json(['message' => 'Credenciais inválidas.'], 401);
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function me(): JsonResponse
    {
        return response()->json(Auth::guard('api')->user());
    }

    private function respondWithToken(string $token, User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
            'user' => $user,
        ], $status);
    }

    public function updateLocale(Request $request): JsonResponse
    {
        $data = $request->validate(['locale' => ['required', \Illuminate\Validation\Rule::in(['pt', 'en', 'es'])]]);
        $request->user()->forceFill(['preferred_locale' => $data['locale']])->saveQuietly();
        return response()->json(['locale' => $data['locale']]);
    }

    private function sessionUser(User $user): array
    {
        return [
            ...$user->only(['id', 'name', 'email', 'avatar_url', 'timezone', 'job_title', 'department', 'preferred_locale']),
            'can_view_parameters' => $user->can('parameters.view'),
            'spot_role' => $user->spotRoleLabel(),
            'can_manage_identity' => $user->can('security.manage'),
            'can_chat' => app(\App\Domain\Chat\ChatAccess::class)->eligible($user),
            'can_create_projects' => $user->can('projects.create'),
            'can_view_financial' => $user->can('financial.view'),
        ];
    }
}
