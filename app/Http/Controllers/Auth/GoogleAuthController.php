<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/calendar.events'])
            ->with(['prompt' => 'consent select_account', 'access_type' => 'offline'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect('/?auth_error=google');
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return redirect('/?auth_error=google_email');
        }

        $user = User::query()->where('google_id', $googleUser->getId())->orWhere('google_email', $email)->orWhere('email', $email)->first();
        if (! $user || ! $user->active || $user->account_status !== 'active') return redirect('/?auth_error=account_not_provisioned');
        if ($user->google_id && $user->google_id !== $googleUser->getId()) return redirect('/?auth_error=google_already_linked');
        if (! in_array(mb_strtolower($email), array_filter([mb_strtolower($user->email), mb_strtolower((string) $user->google_email)]), true)) return redirect('/?auth_error=email_mismatch');
        $user->forceFill(['google_id' => $googleUser->getId(), 'google_email' => $email, 'google_access_token' => $googleUser->token, 'google_refresh_token' => $googleUser->refreshToken ?: $user->google_refresh_token, 'google_token_expires_at' => $googleUser->expiresIn ? now()->addSeconds($googleUser->expiresIn) : null, 'avatar_url' => $user->avatar_url ?: $googleUser->getAvatar(), 'email_verified_at' => $user->email_verified_at ?: now()])->save();

        Auth::login($user, remember: true);
        $user->forceFill(['last_login_at' => now()])->save();
        request()->session()->regenerate();

        return redirect('/?home');
    }
}
