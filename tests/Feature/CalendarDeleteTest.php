<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function event(User $user, string $provider = 'spot'): CalendarEvent
    {
        return CalendarEvent::create(['user_id' => $user->id, 'provider' => $provider, 'title' => 'Evento',
            'starts_at' => '2026-11-15T10:00:00Z', 'ends_at' => '2026-11-15T11:00:00Z',
            'external_id' => $provider === 'spot' ? null : 'external/123']);
    }

    public function test_owner_deletes_only_selected_copy_and_foreign_event_is_protected(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $target = $this->event($user);
        $copy = $this->event($user);
        $foreign = $this->event(User::factory()->create());
        $this->actingAs($user)->deleteJson("/api/v1/calendar/events/{$foreign->id}")->assertNotFound();
        $this->deleteJson("/api/v1/calendar/events/{$target->id}")->assertOk()->assertJsonPath('message', 'Evento excluído.');
        $this->assertModelMissing($target);
        $this->assertModelExists($copy);
        $this->assertModelExists($foreign);
        $this->deleteJson("/api/v1/calendar/events/{$target->id}")->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_connected_calendars_delete_existing_remote_identifier_without_creating_events(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['google_access_token' => 'google', 'google_token_expires_at' => now()->addHour(),
            'microsoft_access_token' => 'microsoft', 'microsoft_token_expires_at' => now()->addHour()]);
        Http::fake(['www.googleapis.com/*' => Http::response(null, 204), 'graph.microsoft.com/*' => Http::response(null, 204)]);
        foreach (['google', 'microsoft', 'teams'] as $provider) {
            $event = $this->event($user, $provider);
            $this->actingAs($user)->deleteJson("/api/v1/calendar/events/{$event->id}")->assertOk();
            $this->assertModelMissing($event);
        }
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/external%2F123'));
    }

    public function test_remote_rejection_or_disconnection_keeps_local_record(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['google_access_token' => 'google', 'google_token_expires_at' => now()->addHour()]);
        Http::fake(['www.googleapis.com/*' => Http::response([], 403)]);
        $event = $this->event($user, 'google');
        $this->actingAs($user)->deleteJson("/api/v1/calendar/events/{$event->id}")->assertUnprocessable();
        $this->assertModelExists($event);
        $disconnected = $this->event($user, 'teams');
        $this->deleteJson("/api/v1/calendar/events/{$disconnected->id}")->assertUnprocessable();
        $this->assertModelExists($disconnected);
        Http::assertSentCount(1);
    }

    public function test_remote_event_already_deleted_can_be_removed_locally_on_retry(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['google_access_token' => 'google', 'google_token_expires_at' => now()->addHour()]);
        Http::fake(['www.googleapis.com/*' => Http::response([], 410)]);
        $event = $this->event($user, 'google');
        $this->actingAs($user)->deleteJson("/api/v1/calendar/events/{$event->id}")->assertOk();
        $this->assertModelMissing($event);
    }
}
