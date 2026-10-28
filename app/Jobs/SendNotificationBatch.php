<?php

namespace App\Jobs;

use App\Domain\Notifications\NotificationCenter;
use App\Domain\Notifications\NotificationPreferences;
use App\Mail\ActivityNotificationMail;
use App\Models\NotificationDelivery;
use App\Models\NotificationMailBatch;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationBatch implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [60, 300];

    public function __construct(public int $batchId)
    {
        $this->onQueue('emails');
    }

    public function handle(NotificationCenter $center, NotificationPreferences $preferences): void
    {
        if (! config('notifications.email_enabled')) {
            return;
        }
        DB::transaction(function () use ($center, $preferences) {
            $batch = NotificationMailBatch::lockForUpdate()->find($this->batchId);
            if (! $batch || $batch->sent_at) {
                return;
            }
            $user = User::find($batch->user_id);
            if ($user && $preferences->availableAt($user)->isFuture()) {
                $batch->update(['queued_at' => null]);

                return;
            }
            if ($user && $batch->frequency !== $preferences->for($user)['digest']['frequency']) {
                NotificationDelivery::where('mail_batch_id', $batch->id)->update(['mail_batch_id' => null]);
                $batch->update(['sent_at' => now(), 'failed_at' => null]);

                return;
            }
            $items = NotificationDelivery::where('mail_batch_id', $batch->id)->whereNull('email_processed_at')->get();
            $permitted = $items->filter(fn ($item) => $user && $center->canDeliver($item, $user, 'email'));
            if ($user && $permitted->isNotEmpty()) {
                Mail::to($user->email)->send(new ActivityNotificationMail(
                    $batch->frequency, $permitted->map->only(['title', 'body', 'actor_name'])->values()->all(),
                ));
            }
            NotificationDelivery::whereIn('id', $items->pluck('id'))->update(['email_processed_at' => now()]);
            $batch->update(['sent_at' => now(), 'failed_at' => null]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        NotificationMailBatch::whereKey($this->batchId)->update(['failed_at' => now()]);
    }
}
