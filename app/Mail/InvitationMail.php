<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invitation;
use App\Models\SystemPreference;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends Mailable
{
    public function __construct(public Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                SystemPreference::get('mail_from_address', config('mail.from.address', '')),
                SystemPreference::get('mail_from_name', config('mail.from.name', '')),
            ),
            subject: 'You have been invited to ' . ($this->invitation->federation?->name ?? 'the federation'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invitation',
            with: ['invitation' => $this->invitation],
        );
    }
}
