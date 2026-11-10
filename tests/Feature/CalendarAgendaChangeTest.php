<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarAgendaChangeTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        Http::preventStrayRequests();
        return User::factory()->create(['google_access_token' => 'google', 'google_token_expires_at' => now()->addHour(),
            'microsoft_access_token' => 'microsoft', 'microsoft_token_expires_at' => now()->addHour()]);
    }

    private function event(User $user, string $provider = 'spot', ?string $group = null): CalendarEvent
    {
        return CalendarEvent::create(['user_id' => $user->id, 'provider' => $provider, 'title' => 'Anterior',
            'starts_at' => '2026-11-15T10:00:00Z', 'ends_at' => '2026-11-15T11:00:00Z',
            'external_id' => $provider === 'spot' ? null : 'external-'.$provider, 'group_key' => $group ?: (string) Str::uuid()]);
    }

    private function payload(array $providers): array
    {
        return ['providers' => $providers, 'request_key' => (string) Str::uuid(), 'title' => 'Alterado',
            'starts_at' => '2026-11-15T12:00:00Z', 'ends_at' => '2026-11-15T13:00:00Z', 'color' => '#0879b7'];
    }

    public function test_linked_copies_are_reused_and_source_can_be_removed_with_safe_replay(): void
    {
        $user = $this->user();
        $source = $this->event($user);
        $google = $this->event($user, 'google', $source->group_key);
        $unrelated = $this->event($user);
        Http::fake(['www.googleapis.com/*' => Http::response(['id' => 'external-google']), 'graph.microsoft.com/*' => Http::response(['id' => 'external-outlook'])]);
        $payload = $this->payload(['google', 'microsoft']);
        $url = "/api/v1/calendar/events/{$source->id}/agendas";
        $this->actingAs($user)->patchJson($url, $payload)->assertOk()->assertJsonPath('complete', true);
        $this->assertModelMissing($source);
        $this->assertModelExists($google);
        $this->assertModelExists($unrelated);
        $this->assertSame('Alterado', $google->fresh()->title);
        $this->assertSame('Anterior', $unrelated->fresh()->title);
        $this->assertSame(2, CalendarEvent::where('group_key', $source->group_key)->count());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && str_contains($request->url(), 'googleapis'));
        $this->patchJson($url, $payload)->assertOk()->assertJsonPath('complete', true);
        Http::assertSentCount(2);
        $this->patchJson($url, [...$payload, 'title' => 'Outro conteúdo'])->assertConflict();
    }

    public function test_rejected_addition_keeps_source_and_retry_creates_only_one_new_copy(): void
    {
        $user = $this->user();
        $source = $this->event($user);
        Http::fake(['www.googleapis.com/*' => Http::sequence()->push([], 429)->push(['id' => 'new-google'])]);
        $payload = $this->payload(['google']);
        $url = "/api/v1/calendar/events/{$source->id}/agendas";
        $this->actingAs($user)->patchJson($url, $payload)->assertStatus(207)->assertJsonPath('results.0.status', 'failed')->assertJsonPath('results.1.status', 'waiting');
        $this->assertModelExists($source);
        $this->patchJson($url, $payload)->assertOk()->assertJsonPath('complete', true);
        $this->assertModelMissing($source);
        $this->assertSame(1, CalendarEvent::count());
        $this->patchJson($url, $payload)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_removing_failed_remote_copy_can_be_retried_without_repeating_updates(): void
    {
        $user = $this->user();
        $source = $this->event($user);
        $google = $this->event($user, 'google', $source->group_key);
        Http::fake(['www.googleapis.com/*' => Http::sequence()->push([], 403)->push([], 404)]);
        $payload = $this->payload(['spot']);
        $url = "/api/v1/calendar/events/{$source->id}/agendas";
        $this->actingAs($user)->patchJson($url, $payload)->assertStatus(207)->assertJsonPath('results.1.status', 'failed');
        $this->assertModelExists($google);
        $this->patchJson($url, $payload)->assertOk()->assertJsonPath('results.1.status', 'removed');
        $this->assertModelMissing($google);
        $this->assertModelExists($source);
    }

    public function test_uncertain_addition_is_not_repeated_and_old_copy_is_not_removed(): void
    {
        $user = $this->user();
        $source = $this->event($user);
        Http::fake(['www.googleapis.com/*' => Http::response([], 503)]);
        $payload = $this->payload(['google']);
        $url = "/api/v1/calendar/events/{$source->id}/agendas";
        $this->actingAs($user)->patchJson($url, $payload)->assertStatus(207)->assertJsonPath('results.0.status', 'uncertain');
        $this->patchJson($url, $payload)->assertStatus(207)->assertJsonPath('results.1.status', 'waiting');
        Http::assertSentCount(1);
        $this->assertModelExists($source);
    }

    public function test_outlook_and_teams_share_one_copy_and_existing_outlook_can_become_a_meeting(): void
    {
        $user = $this->user();
        $source = $this->event($user, 'microsoft');
        Http::fake(['graph.microsoft.com/*' => Http::response(['id' => 'external-microsoft', 'onlineMeeting' => ['joinUrl' => 'https://teams.example.com/join']])]);
        $url = "/api/v1/calendar/events/{$source->id}/agendas";
        $this->actingAs($user)->patchJson($url, $this->payload(['microsoft', 'teams']))->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('event.provider', 'teams');
        $this->assertSame(1, CalendarEvent::count());
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['isOnlineMeeting'] === true);
        $this->patchJson($url, $this->payload(['microsoft']))->assertOk()->assertJsonPath('event.provider', 'teams');
        Http::assertSentCount(3);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_old_creation_retry_reuses_a_copy_added_by_editing_the_group(): void
    {
        $user = $this->user();
        Http::fake(['www.googleapis.com/*' => Http::sequence()->push([], 429)->push(['id' => 'new-google'])]);
        $creation = $this->payload(['spot', 'google']);
        $this->actingAs($user)->postJson('/api/v1/calendar/events', $creation)->assertStatus(207);
        $source = CalendarEvent::where('user_id', $user->id)->where('provider', 'spot')->firstOrFail();
        $this->patchJson("/api/v1/calendar/events/{$source->id}/agendas", [...$this->payload(['spot', 'google']), 'title' => 'Editado'])
            ->assertOk()->assertJsonCount(2, 'event.providers');
        $this->postJson('/api/v1/calendar/events', $creation)->assertCreated()->assertJsonPath('complete', true);
        Http::assertSentCount(2);
        $this->assertSame(2, CalendarEvent::count());
        $this->assertSame('Editado', CalendarEvent::where('provider', 'google')->firstOrFail()->title);
    }

    public function test_access_and_selection_are_validated_before_changing_copies(): void
    {
        $user = $this->user();
        $source = $this->event($user);
        $url = "/api/v1/calendar/events/{$source->id}/agendas";
        $this->actingAs(User::factory()->create())->patchJson($url, $this->payload(['spot']))->assertNotFound();
        $this->actingAs($user)->patchJson($url, $this->payload([]))->assertUnprocessable()->assertJsonValidationErrors('providers');
        $this->patchJson($url, [...$this->payload(['spot']), 'project_id' => 9999])->assertUnprocessable()->assertJsonValidationErrors('project_id');
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('calendar_event_changes')->count());
        $this->assertSame('Anterior', $source->fresh()->title);
    }
}
