<?php

namespace App\Jobs;

use App\Mail\ProjectInvitationMail;
use App\Models\ProjectInvitation;
use App\Models\User;
use App\Models\UserActivationToken;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAccessEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [60, 300];

    public function __construct(
        public string $type,
        public int $recordId,
        public string $url,
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        if ($this->type === 'invitation') {
            $invitation = ProjectInvitation::with(['project', 'inviter'])->find($this->recordId);
            if (! $invitation?->isUsable() || ! $invitation->project || ! $invitation->inviter) {
                return;
            }

            Mail::to($invitation->email)->send(new ProjectInvitationMail(
                $invitation->project,
                $this->url,
                $invitation->inviter->name,
                max(1, (int) ceil(now()->diffInHours($invitation->expires_at))),
            ));

            return;
        }

        $activation = UserActivationToken::find($this->recordId);
        if (! $activation?->isUsable()) {
            return;
        }
        $user = User::find($activation->user_id);
        if (! $user || $user->account_status !== 'pending_activation') {
            return;
        }

        $expiresAt = $activation->expires_at->format('d/m/Y H:i T');
        Mail::raw("Seu link de primeiro acesso ao Spot é: {$this->url}\n\nO link expira em {$expiresAt}.",
            fn ($message) => $message->to($user->email)->subject('Primeiro acesso ao Spot'));
    }
}
