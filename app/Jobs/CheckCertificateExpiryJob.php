<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EntityCertificate;
use App\Models\SchedulerSetting;
use App\Models\User;
use App\Notifications\CertificateExpiryNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * CheckCertificateExpiryJob
 *
 * Queries all certificates expiring within the configured window and sends email
 * notifications to Admin role users, deduplicating per-certificate per-day.
 *
 * Alert thresholds are configurable via SchedulerSetting:
 *   cert_notify_days_critical (default 14)
 *   cert_notify_days_warning  (default 30)
 *   cert_notify_days_advisory (default 60)
 *   cert_notify_days_info     (default 90)
 *
 * Dispatched daily by the scheduler (certificates:check-expiry).
 *
 * Queue: low — email delivery, low priority.
 */
class CheckCertificateExpiryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $daysCritical = (int) SchedulerSetting::get('cert_notify_days_critical', 14);
        $daysWarning  = (int) SchedulerSetting::get('cert_notify_days_warning', 30);
        $daysAdvisory = (int) SchedulerSetting::get('cert_notify_days_advisory', 60);
        $daysInfo     = (int) SchedulerSetting::get('cert_notify_days_info', 90);

        $expiring = EntityCertificate::expiring($daysInfo)
            ->with(['entity:id,entity_id,type,status', 'entity.uiInfo', 'entity.contacts'])
            ->whereHas('entity', fn($q) => $q->where('status', 'active'))
            ->orderBy('not_after')
            ->get();

        $expired = EntityCertificate::expired()
            ->with(['entity:id,entity_id,type,status', 'entity.uiInfo', 'entity.contacts'])
            ->whereHas('entity', fn($q) => $q->where('status', 'active'))
            ->get();

        $all = $expiring->merge($expired)->unique('id');

        if ($all->isEmpty()) {
            Log::info('CheckCertificateExpiryJob: no expiring certificates found');
            Cache::put('last_run_cert_expiry_check', now()->timestamp, now()->addDays(30));
            return;
        }

        $recipients = User::role('Admin')->pluck('email')->toArray();

        if (empty($recipients)) {
            Log::warning('CheckCertificateExpiryJob: no Admin users to notify');
            Cache::put('last_run_cert_expiry_check', now()->timestamp, now()->addDays(30));
            return;
        }

        $notified = 0;

        foreach ($all as $cert) {
            $notifKey = "cert_expiry_notified:{$cert->id}:" . now()->toDateString();
            if (Cache::has($notifKey)) {
                continue;
            }

            $daysRemaining = (int) now()->diffInDays($cert->not_after, false);
            $severity      = $this->severity($daysRemaining, $daysCritical, $daysWarning, $daysAdvisory);

            Notification::route('mail', $recipients)->notify(
                new CertificateExpiryNotification(
                    entityId:      $cert->entity->entity_id,
                    entityName:    $cert->entity->getDisplayName(),
                    entityType:    $cert->entity->type,
                    subject:       $cert->subject,
                    use:           $cert->use,
                    notAfter:      $cert->not_after->toIso8601String(),
                    daysRemaining: $daysRemaining,
                    severity:      $severity,
                )
            );

            $notifType = $daysRemaining < 0 ? 'certificate_expired' : 'certificate_expiring';
            app(\App\Services\Notification\NotificationService::class)->dispatch(
                $notifType,
                ['days' => $daysRemaining, 'entity_id' => $cert->entity->entity_id],
                $cert->entity
            );

            Cache::put($notifKey, true, now()->addHours(23));
            $notified++;
        }

        Cache::put('last_run_cert_expiry_check', now()->timestamp, now()->addDays(30));

        Log::info('CheckCertificateExpiryJob completed', [
            'total_expiring' => $all->count(),
            'notified'       => $notified,
            'skipped'        => $all->count() - $notified,
        ]);
    }

    private function severity(int $days, int $critical, int $warning, int $advisory): string
    {
        if ($days < 0)           return 'expired';
        if ($days <= $critical)  return 'critical';
        if ($days <= $warning)   return 'warning';
        if ($days <= $advisory)  return 'advisory';

        return 'info';
    }
}
