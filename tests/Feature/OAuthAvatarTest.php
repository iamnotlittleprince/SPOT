<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Storage};
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Mockery;
use Tests\TestCase;

class OAuthAvatarTest extends TestCase
{
    use RefreshDatabase;

    private function image(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    }

    private function completeOAuth(string $provider, User $user, ?string $avatar = 'https://lh3.googleusercontent.com/photo'): void
    {
        $identity = ($provider === 'microsoft' ? new \SocialiteProviders\Microsoft\MicrosoftUser : new ProviderUser)->map(['id' => $provider.'-'.$user->id, 'email' => $user->email, 'name' => $user->name, 'avatar' => $avatar]);
        $identity->token = 'test-provider-token';
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->once()->andReturn($identity);
        Socialite::shouldReceive('driver')->once()->with($provider)->andReturn($driver);
        $this->get('/auth/'.$provider.'/callback')->assertRedirect('/?home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_and_microsoft_photos_are_downloaded_and_persisted_as_real_images(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(['lh3.googleusercontent.com/*' => Http::response($this->image(), 200, ['Content-Type' => 'image/png']), 'graph.microsoft.com/*' => Http::response($this->image(), 200, ['Content-Type' => 'image/png'])]);
        foreach (['google', 'microsoft'] as $provider) {
            $user = User::factory()->create();
            $this->completeOAuth($provider, $user, $provider === 'microsoft' ? null : 'https://lh3.googleusercontent.com/photo');
            $url = $user->fresh()->avatar_url;
            $this->assertStringStartsWith('/storage/avatars/providers/'.$user->id.'/'.$provider.'-', $url);
            $this->assertSame($this->image(), Storage::disk('public')->get(substr($url, strlen('/storage/'))));
        }
        Http::assertSent(fn ($request) => $request->url() === 'https://graph.microsoft.com/v1.0/me/photo/$value' && $request->hasHeader('Authorization', 'Bearer test-provider-token'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'googleusercontent.com') && ! $request->hasHeader('Authorization'));
    }

    public function test_stale_external_and_previously_imported_photos_are_refreshed(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->image())]);
        $user = User::factory()->create(['avatar_url' => 'https://lh3.googleusercontent.com/expired']);
        $this->completeOAuth('google', $user);
        $previous = $user->fresh()->avatar_url;
        $this->completeOAuth('google', $user);
        $this->assertNotSame($previous, $user->fresh()->avatar_url);
        Storage::disk('public')->assertMissing(substr($previous, strlen('/storage/')));
        Storage::disk('public')->assertExists(substr($user->fresh()->avatar_url, strlen('/storage/')));
    }

    public function test_manual_photos_are_preserved_and_do_not_trigger_a_download(): void
    {
        Storage::fake('public');
        Http::fake();
        foreach (['google', 'microsoft'] as $provider) {
            $user = User::factory()->create(['avatar_url' => '/storage/avatars/1/manual.jpg']);
            $this->completeOAuth($provider, $user);
            $this->assertSame('/storage/avatars/1/manual.jpg', $user->fresh()->avatar_url);
        }
        Http::assertNothingSent();
    }

    public function test_missing_forbidden_invalid_and_unavailable_photos_do_not_block_login(): void
    {
        Storage::fake('public');
        foreach ([404, 403, 500, 200] as $status) {
            Http::fake(['*' => Http::response('not an image', $status)]);
            foreach (['google', 'microsoft'] as $provider) {
                $user = User::factory()->create();
                $this->completeOAuth($provider, $user);
                $this->assertNull($user->fresh()->avatar_url);
            }
        }
        Http::fake(['*' => Http::failedConnection()]);
        $user = User::factory()->create(['avatar_url' => 'https://lh3.googleusercontent.com/old']);
        $this->completeOAuth('google', $user);
        $this->assertSame('https://lh3.googleusercontent.com/old', $user->fresh()->avatar_url);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_failed_avatar_save_keeps_login_working_and_removes_the_new_file(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->image())]);
        $user = User::factory()->create();
        $event = 'eloquent.saving: '.User::class;
        \Illuminate\Support\Facades\Event::listen($event, function ($saving) {
            if (str_starts_with((string) $saving->avatar_url, '/storage/avatars/providers/')) throw new \RuntimeException('Avatar persistence unavailable');
        });
        try {
            $this->completeOAuth('google', $user);
            $this->assertNull($user->fresh()->avatar_url);
            $this->assertSame([], Storage::disk('public')->allFiles());
        } finally { \Illuminate\Support\Facades\Event::forget($event); }
    }

    public function test_google_photo_download_rejects_untrusted_hosts_and_redirects(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private'])]);
        foreach (['http://lh3.googleusercontent.com/photo', 'https://googleusercontent.com.evil.test/photo', 'http://127.0.0.1/photo', 'data:image/png;base64,abc'] as $url) {
            $user = User::factory()->create();
            $this->completeOAuth('google', $user, $url);
            $this->assertNull($user->fresh()->avatar_url);
        }
        Http::assertNothingSent();
        $user = User::factory()->create();
        $this->completeOAuth('google', $user);
        $this->assertNull($user->fresh()->avatar_url);
        Http::assertSentCount(1);
    }
}
