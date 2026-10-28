<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ActivityNotificationMail extends Mailable
{
    public function __construct(public string $frequency, public array $items) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->frequency) {
            'daily' => 'Seu resumo diário do Spot',
            'weekly' => 'Seu resumo semanal do Spot',
            default => 'Spot — '.($this->items[0]['title'] ?? 'Nova atualização'),
        });
    }

    public function content(): Content
    {
        return new Content(view: 'mail.activity-notification');
    }
}
