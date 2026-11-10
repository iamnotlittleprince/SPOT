<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarEditTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['title' => 'Título alterado', 'description' => 'Nova descrição', 'starts_at' => '2026-11-15T10:00:00',
            'ends_at' => '2026-11-15T11:00:00', 'color' => '#8b5cc7', 'meeting_url' => 'https://meet.example.com/new',
            'project_id' => null, 'task_id' => null, 'location' => 'Sala 2'];
    }

    private function event(User $user, string $provider = 'spot'): CalendarEvent
    {
        return CalendarEvent::create(['user_id' => $user->id, 'provider' => $provider, 'title' => 'Anterior',
            'description' => 'Anterior', 'starts_at' => '2026-11-15T12:00:00Z', 'ends_at' => '2026-11-15T13:00:00Z',
            'external_id' => $provider === 'spot' ? null : 'external/123']);
    }

    public function test_owner_can_edit_spot_event_without_creating_a_copy_and_uses_profile_timezone(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $event = $this->event($user);
        $this->actingAs($user)->patchJson("/api/v1/calendar/events/{$event->id}", $this->payload())
            ->assertOk()->assertJsonPath('event.title', 'Título alterado')->assertJsonPath('event.edit_id', $event->id)
            ->assertJsonPath('event.start', '2026-11-15T13:00:00+00:00')->assertJsonPath('event.color', '#8b5cc7');
        $this->assertSame(1, CalendarEvent::count());
        $this->getJson('/api/v1/calendar?start=2026-11-01&end=2026-12-01')->assertOk()->assertJsonPath('events.0.title', 'Título alterado');
        Http::assertNothingSent();
    }

    public function test_other_users_and_invalid_dates_cannot_change_an_event(): void
    {
        $owner = User::factory()->create();
        $event = $this->event($owner);
        $this->actingAs(User::factory()->create())->patchJson("/api/v1/calendar/events/{$event->id}", $this->payload())->assertNotFound();
        $this->actingAs($owner)->patchJson("/api/v1/calendar/events/{$event->id}", [...$this->payload(), 'ends_at' => '2026-11-14T10:00:00'])->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $this->assertSame('Anterior', $event->fresh()->title);
    }

    public function test_google_event_is_patched_in_place_and_provider_cannot_be_changed(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['google_access_token' => 'google', 'google_token_expires_at' => now()->addHour()]);
        $event = $this->event($user, 'google');
        Http::fake(['www.googleapis.com/*' => Http::response(['id' => 'external/123'])]);
        $this->actingAs($user)->patchJson("/api/v1/calendar/events/{$event->id}", [...$this->payload(), 'provider' => 'spot'])->assertOk()->assertJsonPath('event.id', 'google:external/123');
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && str_ends_with($request->url(), '/external%2F123')
            && $request['summary'] === 'Título alterado' && !isset($request['attendees']));
        $this->assertSame('google', $event->fresh()->provider);
        $this->assertSame(1, CalendarEvent::count());
    }

    public function test_teams_edit_preserves_meeting_body_and_does_not_create_a_new_meeting(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['microsoft_access_token' => 'microsoft', 'microsoft_token_expires_at' => now()->addHour()]);
        $event = $this->event($user, 'teams');
        $blob = '<div id="meeting-blob"><a href="https://teams.example.com/join">Entrar na reunião</a></div>';
        Http::fake(['graph.microsoft.com/*' => Http::response(['body' => ['contentType' => 'html', 'content' => $blob], 'isOnlineMeeting' => true])]);
        $this->actingAs($user)->patchJson("/api/v1/calendar/events/{$event->id}", $this->payload())->assertOk();
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && str_contains($request['body']['content'], $blob)
            && str_contains($request['body']['content'], 'Nova descrição') && !isset($request['isOnlineMeeting']) && !isset($request['attendees']));
        Http::assertSentCount(2);
        $this->assertSame(1, CalendarEvent::count());
    }

    public function test_remote_rejection_keeps_local_details_unchanged(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['google_access_token' => 'google', 'google_token_expires_at' => now()->addHour()]);
        $event = $this->event($user, 'google');
        Http::fake(['www.googleapis.com/*' => Http::response([], 403)]);
        $this->actingAs($user)->patchJson("/api/v1/calendar/events/{$event->id}", $this->payload())->assertUnprocessable();
        $this->assertSame('Anterior', $event->fresh()->title);
    }
}
