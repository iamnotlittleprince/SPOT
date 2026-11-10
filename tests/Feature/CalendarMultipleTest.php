<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarMultipleTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $providers): array
    {
        return ['request_key' => (string) Str::uuid(), 'providers' => $providers, 'title' => 'Evento em várias agendas', 'starts_at' => '2026-11-15T10:00:00Z', 'ends_at' => '2026-11-15T11:00:00Z', 'color' => '#0879b7'];
    }

    public function test_multiple_calendars_and_retry_do_not_duplicate_saved_events(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['google_access_token' => 'google', 'google_token_expires_at' => now()->addHour()]);
        Http::fake(['www.googleapis.com/*' => Http::sequence()->push(['error' => 'quota'], 429)->push(['id' => 'google-event'])]);
        $payload = $this->payload(['spot', 'google']);
        $this->actingAs($user)->postJson('/api/v1/calendar/events', $payload)->assertStatus(207)->assertJsonPath('complete', false)
            ->assertJsonPath('results.0.status', 'failed')->assertJsonPath('results.1.status', 'saved');
        $this->assertSame(1, CalendarEvent::count());
        $this->postJson('/api/v1/calendar/events', $payload)->assertCreated()->assertJsonPath('complete', true);
        $this->postJson('/api/v1/calendar/events', $payload)->assertCreated();
        $this->assertSame(2, CalendarEvent::count());
        Http::assertSentCount(2);
        $this->postJson('/api/v1/calendar/events', [...$payload, 'title' => 'Modificado'])->assertConflict();
        $this->assertSame(2, CalendarEvent::count());
    }

    public function test_teams_and_outlook_create_one_microsoft_event_and_share_its_link(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['microsoft_access_token' => 'microsoft', 'microsoft_token_expires_at' => now()->addHour()]);
        Http::fake(['graph.microsoft.com/*' => Http::response(['id' => 'teams-event', 'onlineMeeting' => ['joinUrl' => 'https://teams.example.com/meeting']])]);
        $payload = $this->payload(['spot', 'microsoft', 'teams']);
        $this->actingAs($user)->postJson('/api/v1/calendar/events', $payload)->assertCreated()->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.provider', 'teams')->assertJsonPath('results.1.event.url', 'https://teams.example.com/meeting');
        $this->postJson('/api/v1/calendar/events', $payload)->assertCreated();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['isOnlineMeeting'] === true);
        $this->assertSame(2, CalendarEvent::count());
    }

    public function test_uncertain_remote_result_is_not_automatically_created_again(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['google_access_token' => 'google', 'google_token_expires_at' => now()->addHour()]);
        Http::fake(['www.googleapis.com/*' => Http::response([], 503)]);
        $payload = $this->payload(['google', 'spot']);
        $this->actingAs($user)->postJson('/api/v1/calendar/events', $payload)->assertStatus(207)->assertJsonPath('results.0.status', 'uncertain');
        $this->postJson('/api/v1/calendar/events', $payload)->assertStatus(207)->assertJsonPath('results.1.status', 'saved');
        Http::assertSentCount(1);
        $this->assertSame(1, CalendarEvent::count());
    }

    public function test_selection_validation_and_disconnected_provider_report_clear_results(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/calendar/events', $this->payload([]))->assertUnprocessable()->assertJsonValidationErrors('providers');
        $this->postJson('/api/v1/calendar/events', $this->payload(['spot', 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('providers.1');
        $this->assertSame(0, CalendarEvent::count());
        $this->postJson('/api/v1/calendar/events', $this->payload(['google', 'spot']))->assertStatus(207)->assertJsonPath('results.0.status', 'failed')->assertJsonPath('results.1.status', 'saved');
        Http::assertNothingSent();
    }

    public function test_request_key_is_isolated_per_user(): void
    {
        $payload = $this->payload(['spot']);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first)->postJson('/api/v1/calendar/events', $payload)->assertCreated();
        $this->actingAs($second)->postJson('/api/v1/calendar/events', $payload)->assertCreated();
        $this->assertSame(2, CalendarEvent::count());
    }
}
