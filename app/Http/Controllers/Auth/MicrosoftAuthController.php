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
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('microsoft')->scopes(['offline_access', 'Calendars.ReadWrite'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
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

        $avatar = $microsoftUser->getAvatar();

        $user = User::query()->where('microsoft_id', $microsoftUser->getId())->orWhere('microsoft_email', $email)->orWhere('email', $email)->first();
        if (! $user || ! $user->active || $user->account_status !== 'active') return redirect('/?auth_error=account_not_provisioned');
        if ($user->microsoft_id && $user->microsoft_id !== $microsoftUser->getId()) return redirect('/?auth_error=microsoft_already_linked');
        if (! in_array(mb_strtolower($email), array_filter([mb_strtolower($user->email), mb_strtolower((string) $user->microsoft_email)]), true)) return redirect('/?auth_error=email_mismatch');
        $user->forceFill(['microsoft_id' => $microsoftUser->getId(), 'microsoft_email' => $email, 'microsoft_access_token' => $microsoftUser->token, 'microsoft_refresh_token' => $microsoftUser->refreshToken ?: $user->microsoft_refresh_token, 'microsoft_token_expires_at' => $microsoftUser->expiresIn ? now()->addSeconds($microsoftUser->expiresIn) : null, 'avatar_url' => $user->avatar_url ?: ($avatar ? (string) $avatar : null), 'email_verified_at' => $user->email_verified_at ?: now(), 'last_login_at' => now()])->save();

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect('/?home');
    }
}
