<?php

namespace App\Mail;

use App\Domain\Mail\SystemMailCopy;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountActivationMail extends Mailable
{
    public function __construct(public string $activationUrl, public string $expiresAt, public string $mailLocale = 'pt') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: SystemMailCopy::for('activation', $this->mailLocale)['subject']);
    }

    public function content(): Content
    {
        $copy = SystemMailCopy::for('activation', $this->mailLocale);
        $copy['expiry'] = str_replace(':date', $this->expiresAt, $copy['expiry']);
        return new Content(view: 'mail.account-activation', text: 'mail.access-text', with: [
            'copy' => $copy, 'mailLanguage' => $copy['mailLanguage'], 'actionUrl' => $this->activationUrl,
        ]);
    }
}
