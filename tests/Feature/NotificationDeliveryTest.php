<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Notifications\DueReminders;
use App\Domain\Notifications\NotificationCenter;
use App\Jobs\SendNotificationBatch;
use App\Mail\ActivityNotificationMail;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\NotificationDelivery;
use App\Models\NotificationMailBatch;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['notifications.email_enabled' => true]);
        Queue::fake();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-26T12:00:00-03:00'));
    }

    private function context(): array
    {
        $company = Company::create(['legal_name' => 'Empresa de teste']);
        $admin = User::factory()->create(['current_company_id' => $company->id]);
        $profile = Profile::create(['name' => 'Administrador', 'slug' => 'administrador']);
        $admin->profiles()->attach($profile, ['company_id' => $company->id]);
        $user = User::factory()->create(['current_company_id' => $company->id, 'timezone' => 'America/Sao_Paulo']);
        $profile = Profile::create(['name' => 'Analista', 'slug' => 'analista']);
        foreach (['projects.view_assigned', 'expenses.create', 'invitations.create'] as $slug) {
            $permission = Permission::create(['name' => $slug, 'slug' => $slug, 'module' => 'teste']);
            $profile->permissions()->attach($permission);
        }
        $user->profiles()->attach($profile, ['company_id' => $company->id]);
        $project = Project::create(['company_id' => $company->id, 'user_id' => $admin->id, 'name' => 'Projeto reservado', 'status' => 'in_progress', 'progress' => 10]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => 'analyst', 'active' => true]);

        return [$admin, $user, $project];
    }

    private function notify(User $user, Project $project, string $key = 'test'): ?NotificationDelivery
    {
        return app(NotificationCenter::class)->record($user, 'project_assigned', $key, 'Projeto atribuído', $project->name, $project);
    }

    private function runBatch(NotificationMailBatch $batch): void
    {
        app()->call([new SendNotificationBatch($batch->id), 'handle']);
    }

    public function test_business_task_creation_records_a_private_notification_and_deduplicates_deliveries(): void
    {
        [$admin, $user, $project] = $this->context();
        $task = Task::create(['project_id' => $project->id, 'user_id' => $user->id, 'title' => 'Revisar entrega', 'status' => 'todo', 'priority' => 'high']);
        app(AuditRecorder::class)->record('task.created', $task, $admin, [], $task->getAttributes());
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'event' => 'task_assigned']);
        $center = app(NotificationCenter::class);
        $center->dispatchPending();
        $center->dispatchPending();
        $this->assertDatabaseCount('inbox_items', 1);
        $this->assertDatabaseCount('notification_mail_batches', 1);
        Queue::assertPushed(SendNotificationBatch::class, 1);
        $this->actingAs($user)->getJson('/api/v1/inbox')->assertOk()->assertJsonPath('items.0.title', 'Nova tarefa atribuída');
        $other = User::factory()->create();
        $this->actingAs($other)->getJson('/api/v1/inbox')->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_rolled_back_business_events_leave_no_delivery_or_email(): void
    {
        [$admin, $user, $project] = $this->context();
        DB::beginTransaction();
        $this->notify($user, $project);
        DB::rollBack();
        $this->assertDatabaseCount('notification_deliveries', 0);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_preferences_disable_channels_and_deduplication_uses_the_recipient(): void
    {
        [, $user, $project] = $this->context();
        $user->update(['notification_preferences' => ['events' => ['project_assigned' => ['in_app' => false, 'email' => false]]]]);
        $this->assertNull($this->notify($user, $project));
        $user->update(['notification_preferences' => null]);
        $first = $this->notify($user, $project);
        $second = $this->notify($user, $project);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('notification_deliveries', 1);
    }

    public function test_silence_crossing_midnight_defers_both_channels_in_the_user_timezone(): void
    {
        [, $user, $project] = $this->context();
        $user->update(['notification_preferences' => ['quiet_hours' => ['enabled' => true, 'start' => '19:00', 'end' => '08:00']]]);
        $this->travelTo(Carbon::parse('2026-10-26T22:00:00-03:00'));
        $delivery = $this->notify($user, $project);
        $this->assertSame('2026-10-27 11:00:00', $delivery->available_at->utc()->format('Y-m-d H:i:s'));
        app(NotificationCenter::class)->dispatchPending();
        $this->assertDatabaseCount('inbox_items', 0);
        Queue::assertNothingPushed();
        $this->travelTo(Carbon::parse('2026-10-27T08:00:00-03:00'));
        app(NotificationCenter::class)->dispatchPending();
        $this->assertDatabaseCount('inbox_items', 1);
        Queue::assertPushed(SendNotificationBatch::class, 1);
    }

    public function test_daily_digest_batches_once_and_carries_later_events_to_the_next_day(): void
    {
        [, $user, $project] = $this->context();
        $user->update(['notification_preferences' => ['digest' => ['frequency' => 'daily', 'time' => '08:00']]]);
        $this->travelTo(Carbon::parse('2026-10-26T07:00:00-03:00'));
        $this->notify($user, $project, 'a');
        $this->notify($user, $project, 'b');
        app(NotificationCenter::class)->dispatchPending();
        $this->assertDatabaseCount('inbox_items', 2);
        $this->assertDatabaseCount('notification_mail_batches', 0);
        $this->travelTo(Carbon::parse('2026-10-26T08:00:00-03:00'));
        app(NotificationCenter::class)->dispatchPending();
        $this->runBatch(NotificationMailBatch::sole());
        Mail::assertSent(ActivityNotificationMail::class, fn ($mail) => $mail->frequency === 'daily' && count($mail->items) === 2);
        $this->notify($user, $project, 'c');
        app(NotificationCenter::class)->dispatchPending();
        $this->assertDatabaseCount('notification_mail_batches', 1);
        $this->travelTo(Carbon::parse('2026-10-27T08:00:00-03:00'));
        app(NotificationCenter::class)->dispatchPending();
        $this->assertDatabaseCount('notification_mail_batches', 2);
    }

    public function test_weekly_digest_waits_for_monday_and_immediate_delivery_is_not_sent_twice(): void
    {
        [, $user, $project] = $this->context();
        $user->update(['notification_preferences' => ['digest' => ['frequency' => 'weekly', 'time' => '08:00']]]);
        $this->travelTo(Carbon::parse('2026-10-26T07:00:00-03:00'));
        $this->notify($user, $project);
        app(NotificationCenter::class)->dispatchPending();
        $this->assertDatabaseCount('notification_mail_batches', 0);
        $this->travelTo(Carbon::parse('2026-10-26T08:00:00-03:00'));
        app(NotificationCenter::class)->dispatchPending();
        $batch = NotificationMailBatch::sole();
        $this->runBatch($batch);
        $this->runBatch($batch);
        Mail::assertSent(ActivityNotificationMail::class, 1);
    }

    public function test_permission_revocation_and_company_change_are_rechecked_before_email(): void
    {
        [, $user, $project] = $this->context();
        $this->notify($user, $project);
        app(NotificationCenter::class)->dispatchPending();
        ProjectMember::where('user_id', $user->id)->update(['active' => false]);
        $this->runBatch(NotificationMailBatch::sole());
        Mail::assertNothingSent();
        $this->assertNull($this->notify($user, $project, 'revoked'));
        ProjectMember::where('user_id', $user->id)->update(['active' => true]);
        $foreign = Company::create(['legal_name' => 'Outra empresa']);
        $user->update(['current_company_id' => $foreign->id]);
        $this->assertNull($this->notify($user, $project, 'foreign'));
    }

    public function test_due_alerts_are_once_per_phase_and_completed_or_finalized_tasks_are_excluded(): void
    {
        [, $user, $project] = $this->context();
        $task = Task::create(['project_id' => $project->id, 'user_id' => $user->id, 'title' => 'Prazo', 'due_date' => '2026-10-27', 'status' => 'todo', 'priority' => 'high']);
        $reminders = app(DueReminders::class);
        $reminders->generate();
        $reminders->generate();
        $this->assertSame(1, NotificationDelivery::where('event', 'task_due')->count());
        $this->travelTo(Carbon::parse('2026-10-28T12:00:00-03:00'));
        $reminders->generate();
        $this->assertSame(2, NotificationDelivery::where('event', 'task_due')->count());
        $task->update(['status' => 'done']);
        app(NotificationCenter::class)->dispatchPending();
        $this->assertDatabaseCount('inbox_items', 0);
        $task->update(['status' => 'todo', 'due_date' => '2026-10-29']);
        $project->forceFill(['finalized_at' => now()])->save();
        $reminders->generate();
        $this->assertSame(2, NotificationDelivery::where('event', 'task_due')->count());
    }

    public function test_calendar_alert_expires_if_event_has_started_and_never_notifies_another_user(): void
    {
        [, $user] = $this->context();
        $event = CalendarEvent::create(['user_id' => $user->id, 'title' => 'Reunião', 'provider' => 'spot', 'starts_at' => now()->addMinutes(20), 'ends_at' => now()->addHour()]);
        app(DueReminders::class)->generate();
        app(DueReminders::class)->generate();
        $this->assertDatabaseCount('notification_deliveries', 1);
        app(NotificationCenter::class)->dispatchPending();
        $this->assertDatabaseHas('inbox_items', ['user_id' => $user->id, 'title' => 'Compromisso começa em breve']);
        $this->travelTo(Carbon::parse('2026-10-26T12:25:00-03:00'));
        $this->assertTrue($event->fresh()->starts_at->isPast(), 'Evento: '.$event->fresh()->starts_at->toIso8601String().' Agora: '.now()->toIso8601String());
        $this->runBatch(NotificationMailBatch::sole());
        Mail::assertNothingSent();
    }

    public function test_worker_observes_new_preferences_and_silence_after_queueing(): void
    {
        [, $user, $project] = $this->context();
        $this->notify($user, $project);
        app(NotificationCenter::class)->dispatchPending();
        $batch = NotificationMailBatch::sole();
        $user->update(['notification_preferences' => ['quiet_hours' => ['enabled' => true, 'start' => '11:00', 'end' => '14:00']]]);
        $this->runBatch($batch);
        Mail::assertNothingSent();
        $this->assertNull($batch->fresh()->queued_at);
        $this->travelTo(Carbon::parse('2026-10-26T14:00:00-03:00'));
        $user->update(['notification_preferences' => ['events' => ['project_assigned' => ['in_app' => true, 'email' => false]]]]);
        $this->runBatch($batch);
        Mail::assertNothingSent();
        $this->assertNotNull($batch->fresh()->sent_at);
    }

    public function test_disabled_outbound_email_keeps_internal_delivery_without_queueing_external_mail(): void
    {
        config(['notifications.email_enabled' => false]);
        [, $user, $project] = $this->context();
        $this->notify($user, $project);
        app(NotificationCenter::class)->dispatchPending();
        $this->assertDatabaseCount('inbox_items', 1);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_catalog_only_offers_produced_events_and_rejects_a_full_day_silence(): void
    {
        [, $user] = $this->context();
        $response = $this->actingAs($user)->getJson('/api/v1/notification-preferences')->assertOk();
        $this->assertArrayHasKey('calendar_due', $response->json('events'));
        $this->assertArrayNotHasKey('task_comment', $response->json('events'));
        $payload = $response->json('preferences');
        $payload['quiet_hours'] = ['enabled' => true, 'start' => '08:00', 'end' => '08:00'];
        $this->putJson('/api/v1/notification-preferences', $payload)->assertUnprocessable();
    }

    public function test_smtp_failure_preserves_the_outbox_and_a_retry_sends_the_same_batch_once(): void
    {
        [, $user, $project] = $this->context();
        $this->notify($user, $project);
        app(NotificationCenter::class)->dispatchPending();
        $batch = NotificationMailBatch::sole();
        $mailFake = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP indisponível'));
        try {
            $this->runBatch($batch);
            $this->fail('A falha SMTP deve ser propagada para a fila.');
        } catch (\RuntimeException $failure) {
            $this->assertSame('SMTP indisponível', $failure->getMessage());
        }
        $this->assertNull($batch->fresh()->sent_at);
        $this->assertNull(NotificationDelivery::sole()->email_processed_at);
        (new SendNotificationBatch($batch->id))->failed(new \RuntimeException('Falha final'));
        $this->assertNotNull($batch->fresh()->failed_at);
        Mail::swap($mailFake);
        $this->runBatch($batch);
        $this->runBatch($batch);
        Mail::assertSent(ActivityNotificationMail::class, 1);
        $this->assertNull($batch->fresh()->failed_at);
    }

    public function test_scheduler_generates_due_alerts_and_repeated_runs_do_not_duplicate_them(): void
    {
        [, $user, $project] = $this->context();
        Task::create(['project_id' => $project->id, 'user_id' => $user->id, 'title' => 'Entrega', 'due_date' => '2026-10-26', 'status' => 'todo', 'priority' => 'high']);
        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->assertDatabaseCount('inbox_items', 1);
        Queue::assertPushed(SendNotificationBatch::class, 1);
    }

    public function test_security_preferences_apply_to_the_account_owner_only(): void
    {
        [, $user] = $this->context();
        $user->update(['notify_new_login' => true]);
        app(AuditRecorder::class)->record('user.login', $user, $user, [], ['provider' => 'password']);
        app(NotificationCenter::class)->dispatchPending();
        $this->assertDatabaseHas('inbox_items', ['user_id' => $user->id, 'title' => 'Novo acesso à sua conta']);
        $user->update(['notify_new_login' => false]);
        app(AuditRecorder::class)->record('user.login', $user, $user, [], ['provider' => 'password']);
        $this->assertDatabaseCount('notification_deliveries', 1);
    }
}
