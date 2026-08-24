<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_and_restore_available_notification_preferences(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $payload = $this->actingAs($admin)->getJson('/api/v1/notification-preferences')->assertOk()->json();
        $payload['preferences']['events']['project_assigned']['email'] = false;
        $payload['preferences']['digest'] = ['frequency' => 'weekly', 'time' => '09:30'];
        $payload['preferences']['quiet_hours'] = ['enabled' => true, 'start' => '19:00', 'end' => '08:00'];

        $this->putJson('/api/v1/notification-preferences', $payload['preferences'])
            ->assertOk()->assertJsonPath('preferences.digest.frequency', 'weekly');
        $this->assertSame('weekly', $admin->fresh()->notification_preferences['digest']['frequency']);

        $this->deleteJson('/api/v1/notification-preferences')->assertOk()
            ->assertJsonPath('preferences.digest.frequency', 'none');
        $this->assertNull($admin->fresh()->notification_preferences);
    }

    public function test_regular_user_cannot_configure_manager_only_notification_events(): void
    {
        $user = User::factory()->create();
        $preferences = $this->actingAs($user)->getJson('/api/v1/notification-preferences')->assertOk();
        $this->assertArrayNotHasKey('approval_pending', $preferences->json('events'));

        $payload = $preferences->json('preferences');
        $payload['events']['approval_pending'] = ['in_app' => true, 'email' => true];
        $this->putJson('/api/v1/notification-preferences', $payload)->assertUnprocessable();
    }
}
