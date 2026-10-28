<?php

namespace App\Domain\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MicrosoftToken
{
    public function get(User $user, bool $force = false): string
    {
        if (! $force && $user->microsoft_access_token && ! $user->microsoft_token_expires_at?->isPast()) return $user->microsoft_access_token;

        $values = DB::transaction(function () use ($user, $force) {
            $current = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($current->microsoft_access_token && ((! $force && ! $current->microsoft_token_expires_at?->isPast()) || ($force && $current->microsoft_access_token !== $user->microsoft_access_token))) {
                return $current->only(['microsoft_access_token', 'microsoft_refresh_token', 'microsoft_token_expires_at']);
            }
            if (! $current->microsoft_refresh_token) throw new RuntimeException('Reconecte sua conta Microsoft.');
            $tenant = config('services.microsoft.tenant', 'common');
            $response = Http::connectTimeout(3)->timeout(10)->asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                'client_id' => config('services.microsoft.client_id'), 'client_secret' => config('services.microsoft.client_secret'),
                'refresh_token' => $current->microsoft_refresh_token, 'grant_type' => 'refresh_token',
                'scope' => 'offline_access User.Read Calendars.ReadWrite',
            ]);
            if (! $response->successful() || ! is_string($response->json('access_token')) || $response->json('access_token') === '') throw new RuntimeException('Reconecte sua conta Microsoft.');
            $values = [
                'microsoft_access_token' => $response->json('access_token'),
                'microsoft_refresh_token' => $response->json('refresh_token') ?: $current->microsoft_refresh_token,
                'microsoft_token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
            ];
            $current->forceFill($values)->save();
            return $values;
        });
        $user->forceFill($values);
        return $user->microsoft_access_token;
    }
}
