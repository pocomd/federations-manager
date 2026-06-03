<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SystemPreference;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class FederationMail extends Mailable
{
    public function __construct(
        public string $emailSubject,
        public string $emailBody,
        public ?string $fromName = null,
        public ?string $fromAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                $this->fromAddress ?? SystemPreference::get('mail_from_address', config('mail.from.address', '')),
                $this->fromName    ?? SystemPreference::get('mail_from_name', config('mail.from.name', '')),
            ),
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.federation-mail',
            with: ['body' => $this->emailBody],
        );
    }
}
