<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\TestCase;

class MicrosoftPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function account(array $values = []): User
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        return User::factory()->create(['microsoft_id' => 'ms-account', 'microsoft_access_token' => 'old-token', 'microsoft_refresh_token' => 'refresh-token', 'microsoft_token_expires_at' => now()->addHour(), ...$values]);
    }

    private function image(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    }

    public function test_expired_token_is_renewed_with_profile_scope_and_the_photo_is_imported(): void
    {
        $user = $this->account(['microsoft_token_expires_at' => now()->subMinute()]);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'new-token', 'refresh_token' => 'rotated-refresh', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response($this->image()),
        ]);
        $url = $this->actingAs($user)->postJson('/profile/avatar/microsoft')->assertOk()->assertJsonPath('status', 'updated')->json('avatar_url');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2/v2.0/token') && $r['scope'] === 'offline_access User.Read Calendars.ReadWrite' && $r['grant_type'] === 'refresh_token');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/me/photo/') && $r->hasHeader('Authorization', 'Bearer new-token'));
        $this->assertSame('rotated-refresh', $user->fresh()->microsoft_refresh_token);
        $this->assertTrue($user->fresh()->microsoft_token_expires_at->isFuture());
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));
        $this->assertArrayNotHasKey('microsoft_access_token', $user->fresh()->toArray());
    }

    public function test_401_retries_once_after_refresh_and_404_is_explained_without_losing_the_existing_photo(): void
    {
        $user = $this->account(['avatar_url' => 'https://lh3.googleusercontent.com/old']);
        Http::fake([
            'graph.microsoft.com/*' => Http::sequence()->push(['error' => ['code' => 'InvalidAuthenticationToken']], 401)->push(['error' => ['code' => 'ImageNotFound']], 404),
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'new-token']),
        ]);
        $this->actingAs($user)->postJson('/profile/avatar/microsoft')->assertOk()->assertJsonPath('status', 'no_photo')->assertJsonPath('avatar_url', 'https://lh3.googleusercontent.com/old');
        Http::assertSentCount(3);
        $this->assertSame('new-token', $user->fresh()->microsoft_access_token);
    }

    public function test_refused_refresh_and_forbidden_photo_request_reconnection_without_leaking_credentials(): void
    {
        $user = $this->account(['microsoft_token_expires_at' => now()->subMinute()]);
        Http::fake(['login.microsoftonline.com/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'private-refresh-secret'], 400)]);
        $response = $this->actingAs($user)->postJson('/profile/avatar/microsoft')->assertUnprocessable()->assertJsonPath('status', 'reconnect');
        $this->assertStringNotContainsString('private-refresh-secret', $response->getContent());
        $this->assertSame('old-token', $user->fresh()->microsoft_access_token);
        $user->update(['microsoft_token_expires_at' => now()->addHour()]);
        Http::fake(['graph.microsoft.com/*' => Http::response(['error' => ['code' => 'AccessDenied']], 403)]);
        $this->actingAs($user)->postJson('/profile/avatar/microsoft')->assertUnprocessable()->assertJsonPath('status', 'reconnect');
        Http::fake([
            'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'InvalidAuthenticationToken']], 401),
            'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_grant'], 400),
        ]);
        $this->actingAs($user)->postJson('/profile/avatar/microsoft')->assertUnprocessable()->assertJsonPath('status', 'reconnect');
    }

    public function test_manual_photo_is_preserved_and_only_the_authenticated_account_is_changed(): void
    {
        $user = $this->account(['avatar_url' => '/storage/avatars/1/manual.jpg']);
        $other = User::factory()->create(['avatar_url' => null]);
        Http::fake();
        $this->actingAs($user)->postJson('/profile/avatar/microsoft', ['user_id' => $other->id])->assertOk()->assertJsonPath('status', 'custom_photo');
        $this->assertNull($other->fresh()->avatar_url);
        Http::assertNothingSent();
    }

    public function test_calendar_refresh_keeps_profile_permissions_for_later_photo_requests(): void
    {
        $user = $this->account(['microsoft_token_expires_at' => now()->subMinute()]);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'new-token', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response(['value' => []]),
        ]);
        $this->actingAs($user)->getJson('/api/v1/calendar?start=2026-10-01T00:00:00Z&end=2026-11-01T00:00:00Z')->assertOk()->assertJsonCount(0, 'errors');
        Http::assertSent(fn ($r) => ($r['scope'] ?? '') === 'offline_access User.Read Calendars.ReadWrite');
    }

    public function test_disconnected_and_unauthenticated_accounts_cannot_import(): void
    {
        $this->postJson('/profile/avatar/microsoft')->assertUnauthorized();
        $user = User::factory()->create();
        Http::fake();
        $this->actingAs($user)->postJson('/profile/avatar/microsoft')->assertUnprocessable()->assertJsonPath('status', 'reconnect');
        Http::assertNothingSent();
    }
}
