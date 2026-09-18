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
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function loginSession(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'two_factor_code' => ['nullable', 'string', 'max:20'],
        ]);

        $twoFactorCode = $credentials['two_factor_code'] ?? null;
        unset($credentials['two_factor_code']);

        if (! Auth::guard('web')->attempt([...$credentials, 'active' => true, 'account_status' => 'active'], true)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        $user = Auth::guard('web')->user();
        if ($user->two_factor_confirmed_at) {
            $validTotp = $twoFactorCode && preg_match('/^\d{6}$/', $twoFactorCode)
                && (new Google2FA())->verifyKey($user->two_factor_secret, $twoFactorCode);
            $recoveryHash = $twoFactorCode ? hash('sha256', strtoupper($twoFactorCode)) : null;
            $recoveryCodes = $user->two_factor_recovery_codes ?? [];
            $recoveryIndex = $recoveryHash ? array_search($recoveryHash, $recoveryCodes, true) : false;
            if (! $validTotp && $recoveryIndex === false) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return response()->json(['message' => 'Informe o código do autenticador ou um código de recuperação.', 'two_factor_required' => true], 422);
            }
            if ($recoveryIndex !== false) {
                unset($recoveryCodes[$recoveryIndex]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();
            }
        }

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
        ]);

        $guard = Auth::guard('api');
        if (! $token = $guard->attempt([...$credentials, 'active' => true, 'account_status' => 'active'])) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        return $this->respondWithToken($token, $guard->user());
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

    private function sessionUser(User $user): array
    {
        return [
            ...$user->only(['id', 'name', 'email', 'avatar_url', 'timezone', 'job_title', 'department']),
            'can_view_parameters' => $user->can('parameters.view'),
            'spot_role' => $user->spotRoleLabel(),
            'can_manage_identity' => $user->can('security.manage'),
            'can_create_projects' => $user->can('projects.create'),
            'can_view_financial' => $user->can('financial.view'),
        ];
    }
}
