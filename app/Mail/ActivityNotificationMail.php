<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use App\Domain\Mail\SystemMailCopy;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ActivityNotificationMail extends Mailable
{
    public function __construct(public string $frequency, public array $items, public string $mailLocale = 'pt') {}

    public function envelope(): Envelope
    {
        $copy = SystemMailCopy::for('activity', $this->mailLocale);
        return new Envelope(subject: match ($this->frequency) {
            'daily' => $copy['daily'],
            'weekly' => $copy['weekly'],
            default => 'Spot — '.($this->items[0]['title'] ?? $copy['subject']),
        });
    }

    public function content(): Content
    {
        $copy = SystemMailCopy::for('activity', $this->mailLocale);
        if (in_array($this->frequency, ['daily', 'weekly'], true)) {
            $copy['title'] = $copy[$this->frequency];
        }
        return new Content(view: 'mail.activity-notification', text: 'mail.activity-notification-text', with: [
            'copy' => $copy, 'mailLanguage' => $copy['mailLanguage'], 'actionUrl' => url('/'),
        ]);
    }
}
