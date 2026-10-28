<?php

namespace App\Domain\Notifications;

use App\Jobs\SendNotificationBatch;
use App\Models\CalendarEvent;
use App\Models\InboxItem;
use App\Models\NotificationDelivery;
use App\Models\NotificationMailBatch;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class NotificationCenter
{
    public function __construct(private NotificationPreferences $preferences) {}

    public function eligible(User $user, ?Project $project, string $event): bool
    {
        if (! $user->active || $user->account_status !== 'active') {
            return false;
        }
        if (! $project) {
            return true;
        }
        if ($project->trashed() || $project->company_id !== $user->current_company_id) {
            return false;
        }
        if ($user->can('projects.view_all')) {
            return true;
        }

        return $project->members()->where('user_id', $user->id)
            ->when($event !== 'project_finalized', fn ($q) => $q->where('active', true))->exists();
    }

    public function record(User $user, string $event, string $key, string $title, string $body, ?Project $project = null, array $data = [], ?string $actor = null): ?NotificationDelivery
    {
        if (! $this->eligible($user, $project, $event)) {
            return null;
        }
        $channels = $this->preferences->channels($user, $event);
        $channels['email'] = $channels['email'] && config('notifications.email_enabled');
        if (! $channels['in_app'] && ! $channels['email']) {
            return null;
        }
        $type = str_starts_with($event, 'task_') ? 'task' : ($event === 'file_uploaded' ? 'document' : 'update');

        return NotificationDelivery::firstOrCreate(['deduplication_key' => hash('sha256', $user->id.':'.$key)], [
            'user_id' => $user->id, 'project_id' => $project?->id, 'event' => $event, 'type' => $type,
            'title' => $title, 'body' => $body, 'actor_name' => $actor ?: 'Spot', 'data' => $data,
            'in_app' => $channels['in_app'], 'email' => $channels['email'], 'available_at' => $this->preferences->availableAt($user),
        ]);
    }

    public function dispatchPending(): void
    {
        NotificationDelivery::whereNull('in_app_delivered_at')->where('available_at', '<=', now())->orderBy('id')->chunkById(200, function ($records) {
            foreach ($records as $record) {
                DB::transaction(function () use ($record) {
                    $delivery = NotificationDelivery::lockForUpdate()->find($record->id);
                    if (! $delivery || $delivery->in_app_delivered_at) {
                        return;
                    }
                    $user = User::find($delivery->user_id);
                    if ($user && $this->preferences->availableAt($user)->isFuture()) {
                        return;
                    }
                    if ($user && $delivery->in_app && $this->canDeliver($delivery, $user, 'in_app')) {
                        InboxItem::create($delivery->only(['user_id', 'project_id', 'type', 'title', 'body', 'actor_name']) + ['data' => [...($delivery->data ?? []), 'event' => $delivery->event, 'delivery_id' => $delivery->id]]);
                    }
                    $delivery->update(['in_app_delivered_at' => now()]);
                });
            }
        });

        if (! config('notifications.email_enabled')) {
            return;
        }

        User::whereIn('id', NotificationDelivery::where('email', true)->whereNull('email_processed_at')->whereNull('mail_batch_id')->where('available_at', '<=', now())->select('user_id'))
            ->orderBy('id')->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $this->batchEmail($user);
                }
            });
        // Outbox durável: recupera a janela de falha entre criar o lote e publicá-lo na fila.
        NotificationMailBatch::whereNull('sent_at')->whereNull('failed_at')
            ->where(fn ($q) => $q->whereNull('queued_at')->orWhere('queued_at', '<', now()->subMinutes(15)))
            ->orderBy('id')->chunkById(100, function ($batches) {
                foreach ($batches as $batch) {
                    DB::transaction(function () use ($batch) {
                        $locked = NotificationMailBatch::lockForUpdate()->find($batch->id);
                        if (! $locked || $locked->sent_at || $locked->failed_at || $locked->queued_at?->greaterThan(now()->subMinutes(15))) {
                            return;
                        }
                        $locked->update(['queued_at' => now()]);
                        SendNotificationBatch::dispatch($locked->id)->afterCommit();
                    });
                }
            });
    }

    public function canDeliver(NotificationDelivery $delivery, User $user, string $channel): bool
    {
        $project = $delivery->project_id ? Project::withTrashed()->find($delivery->project_id) : null;
        if ($delivery->project_id && ! $project) {
            return false;
        }
        if (! $this->eligible($user, $project, $delivery->event)) {
            return false;
        }
        if (! $this->preferences->channels($user, $delivery->event)[$channel]) {
            return false;
        }
        $data = $delivery->data ?? [];
        if (isset($data['task_id'])) {
            $task = Task::find($data['task_id']);
            if (! $task || ($delivery->event === 'task_due' && ($task->status === 'done' || $task->due_date?->toDateString() !== ($data['due_date'] ?? null)))) {
                return false;
            }
            if ($delivery->event === 'task_due' && isset($data['phase'])) {
                $phase = $task->due_date->toDateString() < now($user->timezone ?: 'UTC')->toDateString() ? 'overdue' : 'upcoming';
                if ($phase !== $data['phase']) {
                    return false;
                }
            }
            if (! $user->can('tasks.view_others') && (int) $task->user_id !== $user->id && ! $task->participants()->where('users.id', $user->id)->exists()) {
                return false;
            }
        }
        if (isset($data['calendar_event_id'])) {
            $event = CalendarEvent::find($data['calendar_event_id']);
            if (! $event || $event->user_id !== $user->id || $event->starts_at->isPast() || $event->starts_at->toIso8601String() !== ($data['starts_at'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function batchEmail(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Serializa a formação de lotes por destinatário sem misturar empresas.
            $user = User::lockForUpdate()->findOrFail($user->id);
            if ($this->preferences->availableAt($user)->isFuture()) {
                return;
            }
            $period = $this->preferences->digestPeriod($user);
            if ($period === '') {
                return;
            }
            if ($period && NotificationMailBatch::where('user_id', $user->id)->where('period_key', $period)->exists()) {
                return;
            }
            $query = NotificationDelivery::where('user_id', $user->id)->where('email', true)->whereNull('email_processed_at')->whereNull('mail_batch_id')->where('available_at', '<=', now());
            $items = $query->orderBy('id')->limit($period ? 500 : 100)->lockForUpdate()->get();
            foreach ($items as $item) {
                if (! $this->canDeliver($item, $user, 'email')) {
                    $item->update(['email_processed_at' => now()]);
                }
            }
            $items = $items->whereNull('email_processed_at');
            if ($items->isEmpty()) {
                return;
            }
            $groups = $period ? [$period => $items] : $items->mapWithKeys(fn ($item) => ['instant:'.$item->id => collect([$item])]);
            foreach ($groups as $key => $entries) {
                $batch = NotificationMailBatch::firstOrCreate(['user_id' => $user->id, 'period_key' => $key], ['frequency' => $period ? $this->preferences->for($user)['digest']['frequency'] : 'none']);
                NotificationDelivery::whereIn('id', $entries->pluck('id'))->update(['mail_batch_id' => $batch->id]);
            }
        });
    }
}
