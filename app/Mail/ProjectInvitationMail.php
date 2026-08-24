<?php

namespace App\Mail;

use App\Models\Project;
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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Convite para o projeto {$this->project->name} — Spot");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.project-invitation');
    }
}
