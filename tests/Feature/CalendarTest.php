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

    public function test_event_details_are_saved_and_task_implies_its_project(): void
    {
        $this->seed();
        $user = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $company = \App\Models\Company::findOrFail($user->current_company_id);
        $project = \App\Models\Project::create(['company_id' => $company->id, 'user_id' => $user->id, 'name' => 'Projeto acessível']);
        $task = \App\Models\Task::create(['project_id' => $project->id, 'user_id' => $user->id, 'title' => 'Preparar reunião']);
        $payload = ['title' => 'Alinhamento', 'starts_at' => '2026-11-08T10:00:00-03:00', 'ends_at' => '2026-11-08T11:00:00-03:00', 'provider' => 'spot', 'color' => '#328052', 'meeting_url' => 'https://meet.example.com/room', 'task_id' => $task->id];
        $this->actingAs($user)->postJson('/api/v1/calendar/events', $payload)->assertCreated()
            ->assertJsonPath('event.color', '#328052')->assertJsonPath('event.url', $payload['meeting_url'])
            ->assertJsonPath('event.project.id', $project->id)->assertJsonPath('event.task.id', $task->id);
        $this->getJson('/api/v1/calendar?start=2026-11-01&end=2026-12-01')->assertOk()
            ->assertJsonPath('events.0.task.title', 'Preparar reunião')->assertJsonPath('events.0.color', '#328052')
            ->assertJsonCount(1, 'projects')->assertJsonCount(1, 'tasks');
        $other = \App\Models\Project::create(['company_id' => $company->id, 'user_id' => $user->id, 'name' => 'Outro projeto']);
        $this->postJson('/api/v1/calendar/events', [...$payload, 'project_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('task_id');
        $foreignCompany = \App\Models\Company::create(['legal_name' => 'Outra empresa']);
        $foreign = \App\Models\Project::create(['company_id' => $foreignCompany->id, 'user_id' => $user->id, 'name' => 'Privado']);
        $this->postJson('/api/v1/calendar/events', [...$payload, 'project_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors('project_id');
        $this->postJson('/api/v1/calendar/events', [...$payload, 'color' => 'red', 'meeting_url' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors(['color', 'meeting_url']);
    }

    public function test_inaccessible_projects_and_tasks_are_not_offered_or_assignable(): void
    {
        $company = \App\Models\Company::create(['legal_name' => 'Agenda']);
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $other = User::factory()->create(['current_company_id' => $company->id]);
        $project = \App\Models\Project::create(['company_id' => $company->id, 'user_id' => $other->id, 'name' => 'Privado']);
        $task = \App\Models\Task::create(['project_id' => $project->id, 'user_id' => $other->id, 'title' => 'Privada']);
        $this->actingAs($user)->getJson('/api/v1/calendar?start=2026-11-01&end=2026-12-01')->assertOk()->assertJsonCount(0, 'projects')->assertJsonCount(0, 'tasks');
        $payload = ['title' => 'Teste', 'starts_at' => '2026-11-08T10:00:00Z', 'ends_at' => '2026-11-08T11:00:00Z', 'provider' => 'spot'];
        $this->postJson('/api/v1/calendar/events', [...$payload, 'project_id' => $project->id])->assertUnprocessable()->assertJsonValidationErrors('project_id');
        $this->postJson('/api/v1/calendar/events', [...$payload, 'task_id' => $task->id])->assertUnprocessable()->assertJsonValidationErrors('task_id');
    }

    public function test_connected_calendar_keeps_spot_metadata_and_receives_the_meeting_link(): void
    {
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $user = User::factory()->create(['google_access_token' => 'token', 'google_token_expires_at' => now()->addHour()]);
        \Illuminate\Support\Facades\Http::fake(['www.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['id' => 'external-1', 'items' => [['id' => 'external-1', 'summary' => 'Conectado', 'start' => ['dateTime' => '2026-11-08T10:00:00Z'], 'end' => ['dateTime' => '2026-11-08T11:00:00Z']]]])]);
        $this->actingAs($user)->postJson('/api/v1/calendar/events', ['title' => 'Conectado', 'starts_at' => '2026-11-08T10:00:00Z', 'ends_at' => '2026-11-08T11:00:00Z', 'provider' => 'google', 'color' => '#8b5cc7', 'meeting_url' => 'https://meet.example.com/test'])->assertCreated();
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request['description'], 'https://meet.example.com/test'));
        $this->getJson('/api/v1/calendar?start=2026-11-01&end=2026-12-01')->assertOk()->assertJsonPath('events.0.color', '#8b5cc7')->assertJsonPath('events.0.url', 'https://meet.example.com/test');
    }
}
