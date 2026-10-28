<?php

namespace App\Domain\Notifications;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;

final class DueReminders
{
    public function __construct(private NotificationCenter $center) {}

    public function generate(): void
    {
        Task::with(['project', 'participants'])->where('status', '!=', 'done')->whereNotNull('due_date')
            ->whereHas('project', fn ($q) => $q->whereNull('finalized_at'))->whereDate('due_date', '>=', now()->subDays(config('notifications.reminder_lookback_days', 30))->toDateString())->whereDate('due_date', '<=', now()->addDays(2)->toDateString())
            ->orderBy('id')->chunkById(200, function ($tasks) {
                foreach ($tasks as $task) {
                    foreach (User::whereIn('id', [$task->user_id, ...$task->participants->pluck('id')->all()])->get() as $user) {
                        $today = CarbonImmutable::now($user->timezone ?: 'UTC')->startOfDay();
                        $due = CarbonImmutable::parse($task->due_date->toDateString(), $user->timezone ?: 'UTC');
                        if ($due->greaterThan($today->addDay())) {
                            continue;
                        }
                        $phase = $due->lessThan($today) ? 'overdue' : 'upcoming';
                        $this->center->record($user, 'task_due', "task:{$task->id}:{$task->due_date->toDateString()}:$phase",
                            $phase === 'overdue' ? 'Tarefa atrasada' : 'Prazo de tarefa próximo', $task->title.' — '.$due->format('d/m/Y'), $task->project,
                            ['task_id' => $task->id, 'due_date' => $task->due_date->toDateString(), 'phase' => $phase]);
                    }
                }
            });
        CalendarEvent::where('starts_at', '>', now())->where('starts_at', '<=', now()->addMinutes(30))->orderBy('id')->chunkById(200, function ($events) {
            foreach ($events as $event) {
                $user = User::find($event->user_id);
                if (! $user) {
                    continue;
                }
                $this->center->record($user, 'calendar_due', "calendar:{$event->id}:{$event->starts_at->timestamp}", 'Compromisso começa em breve',
                    $event->title.' — '.$event->starts_at->copy()->timezone($user->timezone ?: 'UTC')->format('d/m/Y H:i'),
                    $event->project_id ? Project::find($event->project_id) : null,
                    ['calendar_event_id' => $event->id, 'starts_at' => $event->starts_at->toIso8601String()]);
            }
        });
        ProjectInvitation::with('project')->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->where('expires_at', '<=', now()->addDay())
            ->orderBy('id')->chunkById(200, function ($invitations) {
                foreach ($invitations as $invitation) {
                    if ($user = User::find($invitation->invited_by)) {
                        $this->center->record($user, 'invitation_status', 'invitation:'.$invitation->id.':expiring',
                            'Convite próximo de expirar', $invitation->project?->name ?? 'Convite do projeto', $invitation->project);
                    }
                }
            });
    }
}
