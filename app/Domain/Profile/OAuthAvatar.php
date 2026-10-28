<?php

namespace App\Domain\Profile;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Throwable;

final class OAuthAvatar
{
    public function sync(User $user, string $provider, ProviderUser $identity): string
    {
        $previous = $user->avatar_url;
        $previousPath = parse_url((string) $previous, PHP_URL_PATH) ?: '';
        // A manually uploaded photo takes precedence over an imported photo.
        if (str_starts_with($previousPath, '/storage/avatars/') && ! str_starts_with($previousPath, '/storage/avatars/providers/')) return 'custom_photo';

        $path = null;
        $saved = false;
        try {
            if ($provider === 'microsoft') {
                if (! $identity->token) return 'reconnect';
                $response = Http::connectTimeout(3)->timeout(5)->withOptions(['allow_redirects' => false])
                    ->withToken($identity->token)->accept('image/*')->get('https://graph.microsoft.com/v1.0/me/photo/$value');
                if ($response->status() === 401 && $user->microsoft_refresh_token) {
                    try {
                        $token = app(\App\Domain\Auth\MicrosoftToken::class)->get($user, force: true);
                    } catch (Throwable) { return 'reconnect'; }
                    $response = Http::connectTimeout(3)->timeout(5)->withOptions(['allow_redirects' => false])
                        ->withToken($token)->accept('image/*')->get('https://graph.microsoft.com/v1.0/me/photo/$value');
                }
            } elseif ($provider === 'google') {
                $url = $identity->getAvatar();
                $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
                if (parse_url((string) $url, PHP_URL_SCHEME) !== 'https' || ! ($host === 'googleusercontent.com' || str_ends_with($host, '.googleusercontent.com'))) return 'unavailable';
                $response = Http::connectTimeout(3)->timeout(5)->withOptions(['allow_redirects' => false])->accept('image/*')->get($url);
            } else return 'unavailable';

            if ($response->status() === 404) return 'no_photo';
            if (in_array($response->status(), [401, 403], true)) return 'reconnect';
            if (! $response->successful()) {
                Log::notice('OAuth profile photo unavailable', ['provider' => $provider, 'status' => $response->status()]);
                return 'unavailable';
            }
            $contents = $response->body();
            if (strlen($contents) > 5 * 1024 * 1024) return 'unavailable';
            $image = @getimagesizefromstring($contents);
            $extension = match ($image[2] ?? null) { IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', default => null };
            if (! $extension) return 'unavailable';
            $path = "avatars/providers/{$user->id}/{$provider}-".Str::uuid().'.'.$extension;
            if (! Storage::disk('public')->put($path, $contents)) return 'unavailable';
            $user->forceFill(['avatar_url' => '/storage/'.$path])->save();
            $saved = true;
            if (str_starts_with($previousPath, '/storage/avatars/providers/'.$user->id.'/')) {
                Storage::disk('public')->delete(substr($previousPath, strlen('/storage/')));
            }
            return 'updated';
        } catch (Throwable) {
            // Photo delivery is optional and must never prevent authentication.
            if (! $saved) {
                $user->forceFill(['avatar_url' => $previous]);
                if ($path) Storage::disk('public')->delete($path);
            }
            Log::notice('OAuth profile photo could not be imported', ['provider' => $provider]);
            return 'unavailable';
        }
    }
}
