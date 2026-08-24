<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AccountSecurityController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessions = DB::table(config('session.table'))->where('user_id', $user->id)
            ->orderByDesc('last_activity')->get()->map(fn ($session) => [
                'id' => $session->id,
                'current' => hash_equals($request->session()->getId(), $session->id),
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => now()->setTimestamp($session->last_activity)->toIso8601String(),
            ]);
        $events = DB::table('audit_logs')->where('user_id', $user->id)
            ->whereIn('action', ['security.password_changed', 'security.2fa_enabled', 'security.2fa_disabled', 'security.sessions_revoked', 'security.provider_disconnected.google', 'security.provider_disconnected.microsoft'])
            ->latest('created_at')->limit(10)->get(['id', 'action', 'ip_address', 'created_at']);

        return response()->json([
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'providers' => [
                'google' => ['connected' => filled($user->google_id), 'email' => $user->google_email],
                'microsoft' => ['connected' => filled($user->microsoft_id), 'email' => $user->microsoft_email],
            ],
            'preferences' => $user->only(['notify_new_login', 'notify_password_change', 'notify_provider_link']),
            'sessions' => $sessions,
            'events' => $events,
        ]);
    }

    public function updatePassword(Request $request, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);
        $request->user()->update(['password' => $data['password']]);
        DB::table(config('session.table'))->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        $audit->record('security.password_changed', $request->user(), $request->user());

        return response()->json(['message' => 'Senha alterada. As outras sessões foram encerradas.']);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notify_new_login' => ['required', 'boolean'],
            'notify_password_change' => ['required', 'boolean'],
            'notify_provider_link' => ['required', 'boolean'],
        ]);
        $request->user()->update($data);
        return response()->json(['preferences' => $data]);
    }

    public function disconnectProvider(Request $request, string $provider, AuditRecorder $audit): JsonResponse
    {
        validator(['provider' => $provider], [
            'provider' => ['required', Rule::in(['google', 'microsoft'])],
        ])->validate();

        $user = $request->user();
        $user->forceFill([
            "{$provider}_id" => null,
            "{$provider}_email" => null,
            "{$provider}_access_token" => null,
            "{$provider}_refresh_token" => null,
            "{$provider}_token_expires_at" => null,
        ])->save();

        $audit->record("security.provider_disconnected.{$provider}", $user, $user);

        return response()->json(['message' => 'Integração desconectada com sucesso.']);
    }

    public function revokeSession(Request $request, string $session): JsonResponse
    {
        abort_if(hash_equals($request->session()->getId(), $session), 422, 'Use a opção de sair para encerrar a sessão atual.');
        DB::table(config('session.table'))->where('user_id', $request->user()->id)->where('id', $session)->delete();
        return response()->json(['message' => 'Sessão encerrada.']);
    }

    public function revokeOtherSessions(Request $request, AuditRecorder $audit): JsonResponse
    {
        DB::table(config('session.table'))->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        $audit->record('security.sessions_revoked', $request->user(), $request->user());
        return response()->json(['message' => 'Outras sessões encerradas.']);
    }

    public function beginTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey(32);
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $uri = $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd())))->writeString($uri);

        return response()->json(['secret' => $secret, 'qr_code' => 'data:image/svg+xml;base64,'.base64_encode($svg)]);
    }

    public function confirmTwoFactor(Request $request, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        if (! $user->two_factor_secret || ! (new Google2FA())->verifyKey($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Código de autenticação inválido.']);
        }
        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();
        $user->forceFill(['two_factor_recovery_codes' => array_map(fn ($code) => hash('sha256', $code), $codes), 'two_factor_confirmed_at' => now()])->save();
        $audit->record('security.2fa_enabled', $user, $user);
        return response()->json(['recovery_codes' => $codes]);
    }

    public function disableTwoFactor(Request $request, AuditRecorder $audit): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password:web']]);
        $user = $request->user();
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $audit->record('security.2fa_disabled', $user, $user);
        return response()->json(['message' => 'Autenticação em dois fatores desativada.']);
    }
}
