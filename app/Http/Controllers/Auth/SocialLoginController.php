<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialLoginController extends Controller
{
    /*
    |------------------------------------------------------------------
    | Google
    |------------------------------------------------------------------
    */

    public function redirectToGoogle(): RedirectResponse
    {
        $query = http_build_query([
            'client_id'     => config('services.google.client_id'),
            'redirect_uri'  => config('services.google.redirect'),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        if (!$request->has('code')) {
            return redirect()->route('login')->withErrors(['social' => 'Autenticação cancelada.']);
        }

        $tokenResponse = Http::post('https://oauth2.googleapis.com/token', [
            'code'          => $request->code,
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri'  => config('services.google.redirect'),
            'grant_type'    => 'authorization_code',
        ]);

        $accessToken = $tokenResponse->json('access_token');

        if (!$accessToken) {
            return redirect()->route('login')->withErrors(['social' => 'Falha ao obter token do Google.']);
        }

        $userInfo = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v3/userinfo')
            ->json();

        return $this->loginOrCreate($userInfo['email'], $userInfo['name'] ?? 'Usuário');
    }

    /*
    |------------------------------------------------------------------
    | Microsoft
    |------------------------------------------------------------------
    */

    public function redirectToMicrosoft(): RedirectResponse
    {
        $query = http_build_query([
            'client_id'     => config('services.microsoft.client_id'),
            'redirect_uri'  => config('services.microsoft.redirect'),
            'response_type' => 'code',
            'scope'         => 'openid email profile User.Read',
        ]);

        return redirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . $query);
    }

    public function handleMicrosoftCallback(Request $request): RedirectResponse
    {
        if (!$request->has('code')) {
            return redirect()->route('login')->withErrors(['social' => 'Autenticação cancelada.']);
        }

        $tokenResponse = Http::post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'code'          => $request->code,
            'client_id'     => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'redirect_uri'  => config('services.microsoft.redirect'),
            'grant_type'    => 'authorization_code',
            'scope'         => 'openid email profile User.Read',
        ]);

        $accessToken = $tokenResponse->json('access_token');

        if (!$accessToken) {
            return redirect()->route('login')->withErrors(['social' => 'Falha ao obter token da Microsoft.']);
        }

        $userInfo = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me')
            ->json();

        $email = $userInfo['mail'] ?? $userInfo['userPrincipalName'];

        return $this->loginOrCreate($email, $userInfo['displayName'] ?? 'Usuário');
    }

    /*
    |------------------------------------------------------------------
    | Helpers
    |------------------------------------------------------------------
    */

    private function loginOrCreate(string $email, string $name): RedirectResponse
    {
        $user = User::firstOrCreate(
            ['Email' => $email],
            [
                'Senha'             => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
                'id_perfil'         => 4,
            ]
        );

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
