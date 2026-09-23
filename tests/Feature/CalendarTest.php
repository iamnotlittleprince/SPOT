<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CalendarTest extends TestCase
{
    use RefreshDatabase;
    public function test_user_can_create_and_list_a_spot_calendar_event(): void
    {
        $user=User::factory()->create();
        $payload=['title'=>'Reunião de projeto','starts_at'=>'2026-08-25T10:00:00-03:00','ends_at'=>'2026-08-25T11:00:00-03:00','provider'=>'spot'];
        $this->actingAs($user)->postJson('/api/v1/calendar/events',$payload)->assertCreated()->assertJsonPath('event.title','Reunião de projeto');
        $this->getJson('/api/v1/calendar?start=2026-08-01T00:00:00-03:00&end=2026-09-01T00:00:00-03:00')->assertOk()->assertJsonCount(1,'events')->assertJsonPath('providers.google',false);
    }

    public function test_event_end_must_be_after_start_with_a_clear_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/calendar/events', [
            'title' => 'Reunião',
            'starts_at' => '2026-09-18T20:00:00-03:00',
            'ends_at' => '2026-09-18T20:00:00-03:00',
            'provider' => 'spot',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.ends_at.0', 'O horário de término deve ser posterior ao horário de início.');
    }
}
