<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function calendarQuery(): string
    {
        return '/api/v1/calendar?start=2026-10-01T00:00:00Z&end=2026-11-01T00:00:00Z';
    }

    public function test_google_calendar_reads_all_pages_and_skips_cancelled_events(): void
    {
        $user = User::factory()->create(['google_access_token' => 'private-google-token', 'google_token_expires_at' => now()->addHour()]);
        $item = fn ($id) => ['id' => $id, 'summary' => 'Evento '.$id, 'start' => ['dateTime' => '2026-10-27T10:00:00-03:00'], 'end' => ['dateTime' => '2026-10-27T11:00:00-03:00']];
        Http::fake(['https://www.googleapis.com/*' => Http::sequence()->push(['items' => [$item('a')], 'nextPageToken' => 'second'])->push(['items' => [$item('b'), ['id' => 'cancelled', 'status' => 'cancelled']]])]);
        $this->actingAs($user)->getJson($this->calendarQuery())->assertOk()->assertJsonCount(2, 'events')->assertJsonCount(0, 'errors');
        Http::assertSent(fn ($request) => ($request['pageToken'] ?? null) === 'second' && $request->hasHeader('Authorization', 'Bearer private-google-token'));
    }

    public function test_microsoft_calendar_follows_next_link_and_requests_utc_dates(): void
    {
        $user = User::factory()->create(['microsoft_access_token' => 'private-microsoft-token', 'microsoft_token_expires_at' => now()->addHour()]);
        $item = fn ($id) => ['id' => $id, 'subject' => 'Evento '.$id, 'start' => ['dateTime' => '2026-10-27T13:00:00'], 'end' => ['dateTime' => '2026-10-27T14:00:00']];
        Http::fake(['https://graph.microsoft.com/*' => Http::sequence()->push(['value' => [$item('a')], '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/calendarView?$skiptoken=abc'])->push(['value' => [$item('b')]])]);
        $this->actingAs($user)->getJson($this->calendarQuery())->assertOk()->assertJsonCount(2, 'events')->assertJsonPath('events.0.start', '2026-10-27T13:00:00+00:00');
        Http::assertSent(fn ($request) => $request->hasHeader('Prefer', 'outlook.timezone="UTC"'));
    }

    public function test_microsoft_next_link_cannot_forward_credentials_to_another_host(): void
    {
        $user = User::factory()->create(['microsoft_access_token' => 'private-token', 'microsoft_token_expires_at' => now()->addHour()]);
        Http::fake(['https://graph.microsoft.com/*' => Http::response(['value' => [], '@odata.nextLink' => 'https://attacker.example/collect'])]);
        $this->actingAs($user)->getJson($this->calendarQuery())->assertOk()->assertJsonCount(1, 'errors');
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'attacker.example'));
    }

    public function test_expired_google_token_is_refreshed_and_remains_hidden(): void
    {
        $user = User::factory()->create(['google_access_token' => 'old', 'google_refresh_token' => 'refresh-secret', 'google_token_expires_at' => now()->subMinute()]);
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-secret', 'expires_in' => 3600]), 'https://www.googleapis.com/*' => Http::response(['items' => []])]);
        $response = $this->actingAs($user)->getJson($this->calendarQuery())->assertOk()->assertJsonCount(0, 'errors');
        $this->assertSame('new-secret', $user->fresh()->google_access_token);
        $this->assertStringNotContainsString('new-secret', $response->getContent());
        Http::assertSent(fn ($request) => str_contains($request->url(), 'www.googleapis.com') && $request->hasHeader('Authorization', 'Bearer new-secret'));
    }

    public function test_google_and_teams_creation_preserve_user_timezone_without_double_conversion(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo', 'google_access_token' => 'g', 'google_token_expires_at' => now()->addHour(), 'microsoft_access_token' => 'm', 'microsoft_token_expires_at' => now()->addHour()]);
        Http::fake(['https://www.googleapis.com/*' => Http::response(['id' => 'g1']), 'https://graph.microsoft.com/*' => Http::response(['id' => 'm1', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/test']])]);
        $payload = ['title' => 'Reunião de teste', 'starts_at' => '2026-10-27T10:00', 'ends_at' => '2026-10-27T11:00'];
        $this->actingAs($user)->postJson('/api/v1/calendar/events', $payload + ['provider' => 'google'])->assertCreated()->assertJsonPath('event.start', '2026-10-27T13:00:00+00:00');
        $this->postJson('/api/v1/calendar/events', $payload + ['provider' => 'teams'])->assertCreated()->assertJsonPath('event.url', 'https://teams.microsoft.com/test');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com') && $request['start']['dateTime'] === '2026-10-27T13:00:00' && $request['start']['timeZone'] === 'UTC' && $request['isOnlineMeeting'] === true);
    }

    public function test_integration_status_never_returns_secrets_and_distinguishes_calendar_consent(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'google-private-secret']);
        $user = User::factory()->create(['google_id' => 'identity-only', 'google_email' => 'test@example.test']);
        $response = $this->actingAs($user)->getJson('/api/v1/security')->assertOk()->assertJsonPath('providers.google.configured', true)->assertJsonPath('providers.google.connected', true)->assertJsonPath('providers.google.calendar_connected', false);
        $this->assertStringNotContainsString('google-private-secret', $response->getContent());
        Http::assertNothingSent();
    }
}
