<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies federation operators that one or more entity certificates are expiring.
 *
 * Sent by CertificateMonitoringController::notify() — triggered by the daily scheduler.
 */
class CertificateExpiryNotification extends Notification
{
    public function __construct(
        public readonly string  $entityId,
        public readonly ?string $entityName,
        public readonly string  $entityType,
        public readonly ?string $subject,
        public readonly string  $use,
        public readonly string  $notAfter,
        public readonly int     $daysRemaining,
        public readonly string  $severity,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $label = match ($this->severity) {
            'expired'  => '[EXPIRED]',
            'critical' => '[CRITICAL]',
            'warning'  => '[WARNING]',
            default    => '[ADVISORY]',
        };

        return (new MailMessage)
            ->subject("{$label} Certificate expiry — {$this->entityName}")
            ->greeting("Certificate expiry alert ({$this->severity})")
            ->line("Entity: **{$this->entityId}** ({$this->entityType})")
            ->line("Certificate use: {$this->use}")
            ->line("Subject: {$this->subject}")
            ->line("Expiry date: {$this->notAfter}")
            ->line("Days remaining: **{$this->daysRemaining}**")
            ->line('Please rotate the certificate before the expiry date to prevent authentication failures.');
    }
}
