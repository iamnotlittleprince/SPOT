<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class MicrosoftAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('microsoft')->redirect();
    }

    public function callback(): RedirectResponse
    {
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

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $microsoftUser->getName() ?: $microsoftUser->getNickname() ?: $email,
                'microsoft_id' => $microsoftUser->getId(),
                'avatar_url' => $avatar ? (string) $avatar : null,
                'email_verified_at' => now(),
            ],
        );

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect('/?home');
    }
}
