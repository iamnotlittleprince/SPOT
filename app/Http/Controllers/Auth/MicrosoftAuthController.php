<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class MicrosoftAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->forget('microsoft_link_user_id');
        // Older open tabs still use /auth/microsoft from the integrations modal.
        // With an authenticated session, OAuth connects to that same Spot user.
        if ($request->user()) {
            $request->session()->put('microsoft_link_user_id', $request->user()->id);
        }

        return Socialite::driver('microsoft')->scopes(['offline_access', 'Calendars.ReadWrite'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $linkUserId = $request->session()->pull('microsoft_link_user_id');
        $connecting = $linkUserId !== null;
        if ($connecting && (! $request->user() || (int) $request->user()->id !== (int) $linkUserId)) {
            return redirect('/?auth_error=account_not_provisioned');
        }
        if ($request->filled('error')) {
            return redirect('/?auth_error=microsoft_denied');
        }

        try {
            $microsoftUser = Socialite::driver('microsoft')->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect('/?auth_error=microsoft');
        }

        $email = $microsoftUser->getEmail();

        if (! $email) {
            return redirect('/?auth_error=microsoft_email');
        }

        $email = mb_strtolower(trim($email));
        $matchingUsers = User::query()->where(function ($query) use ($microsoftUser, $email) {
            $query->where('microsoft_id', $microsoftUser->getId())
                ->orWhereRaw('lower(microsoft_email) = ?', [$email])
                ->orWhereRaw('lower(email) = ?', [$email]);
        });
        $user = $connecting ? $request->user() : (clone $matchingUsers)->first();
        if (! $user || ! $user->active || $user->account_status !== 'active') return redirect('/?auth_error=account_not_provisioned');
        if ($connecting && (clone $matchingUsers)->where('id', '!=', $user->id)->exists()) return redirect('/?auth_error=microsoft_already_linked');
        if ($user->microsoft_id && $user->microsoft_id !== $microsoftUser->getId()) return redirect('/?auth_error=microsoft_already_linked');
        if (! $connecting && ! in_array($email, array_filter([mb_strtolower($user->email), mb_strtolower((string) $user->microsoft_email)]), true)) return redirect('/?auth_error=email_mismatch');
        $linkingProvider = ! $user->microsoft_id;
        $providerName = trim((string) $microsoftUser->getName());
        $user->forceFill([
            'microsoft_id' => $microsoftUser->getId(),
            'microsoft_email' => $email,
            'microsoft_access_token' => $microsoftUser->token,
            'microsoft_refresh_token' => $microsoftUser->refreshToken ?: $user->microsoft_refresh_token,
            'microsoft_token_expires_at' => $microsoftUser->expiresIn ? now()->addSeconds($microsoftUser->expiresIn) : null,
            ...($connecting ? [] : [
                'name' => $providerName !== '' ? mb_substr($providerName, 0, 255) : $user->name,
                'email_verified_at' => $user->email_verified_at ?: now(),
                'last_login_at' => now(),
            ]),
        ])->save();

        app(\App\Domain\Profile\OAuthAvatar::class)->sync($user, 'microsoft', $microsoftUser);

        if (! $connecting) {
            Auth::login($user, remember: true);
            app(\App\Domain\Audit\AuditRecorder::class)->record('user.login',$user,$user,[],['provider'=>'microsoft']);
        }
        if ($linkingProvider) app(\App\Domain\Audit\AuditRecorder::class)->record('security.provider_linked.microsoft', $user, $user);
        request()->session()->regenerate();

        return redirect('/?home');
    }
}
