<?php

namespace App\Mail;

use App\Models\Project;
use App\Domain\Mail\SystemMailCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Project $project,
        public string $acceptUrl,
        public string $inviterName,
        public int $expiresInHours,
        public string $mailLocale = 'pt',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: SystemMailCopy::for('invitation', $this->mailLocale)['subject'].' · '.$this->project->name);
    }

    public function content(): Content
    {
        $copy = SystemMailCopy::for('invitation', $this->mailLocale);
        $copy['intro'] = strtr($copy['intro'], [':inviter' => $this->inviterName, ':project' => $this->project->name]);
        $copy['expiry'] = str_replace(':hours', (string) $this->expiresInHours, $copy['expiry']);
        return new Content(view: 'mail.project-invitation', text: 'mail.access-text', with: [
            'copy' => $copy, 'mailLanguage' => $copy['mailLanguage'], 'actionUrl' => $this->acceptUrl,
        ]);
    }
}
