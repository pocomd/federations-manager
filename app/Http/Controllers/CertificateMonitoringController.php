<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\EntityCertificate;
use App\Notifications\CertificateExpiryNotification;
use App\Services\Auth\FederationScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * CertificateMonitoringController
 *
 * Dashboard and API for monitoring X.509 certificate expiry across all entities.
 *
 * Certificate monitoring is a core federation operator duty:
 *   - REFEDS Baseline Expectations IPO5 / SPO5: metadata must be accurate and up-to-date.
 *   - An expired certificate in published metadata causes immediate authentication failures
 *     for all federation participants consuming that metadata.
 *   - eduGAIN metadata validators will reject entities with expired certificates.
 *
 * Important note from the SAML2 spec and federation practice:
 *   An EXPIRED certificate in the metadata does NOT necessarily mean authentication will fail
 *   immediately — many IdPs/SPs verify the certificate against the metadata fingerprint, not
 *   against a CA chain. However, best practice and eduGAIN compliance require valid (non-expired)
 *   certificates. Notify early and rotate before expiry.
 *
 * Alert thresholds:
 *   CRITICAL  → expires within 14 days
 *   WARNING   → expires within 30 days
 *   ADVISORY  → expires within 60 days
 *   INFO      → expires within 90 days
 */
class CertificateMonitoringController extends Controller
{
    private const THRESHOLD_CRITICAL = 14;
    private const THRESHOLD_WARNING  = 30;
    private const THRESHOLD_ADVISORY = 60;
    public  const THRESHOLD_INFO     = 90;

    /**
     * Certificate expiry dashboard. Scoped by federation for Federation Managers.
     *
     * Dual response: JSON for API clients, HTML view for browsers.
     * Dashboard data is cached for 5 minutes (per-user key for FM-scoped views).
     */
    public function index(Request $request)
    {
        Gate::authorize('entity.view');

        $days     = (int) $request->get('days', self::THRESHOLD_INFO);
        $user     = Auth::user();
        $scope    = app(FederationScopeService::class);
        $cacheKey = $scope->isConstrained()
            ? "cert_monitor_dashboard:{$days}:fm:{$user->id}"
            : "cert_monitor_dashboard:{$days}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($days, $scope) {
            return $this->buildDashboardData($days, $scope->isConstrained() ? $scope->ids() : null);
        });

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return view('certificates.monitor', $data);
    }

    /**
     * Return a structured JSON expiry report for all active-entity certificates expiring within 90 days.
     *
     * Intended for Scheduler daily runs and external monitoring integrations.
     */
    public function expiryReport(): JsonResponse
    {
        Gate::authorize('entity.view');

        $report = $this->buildExpiryReport();

        return response()->json($report);
    }

    /**
     * Send certificate expiry notification emails to Admin users.
     *
     * Deduplicates via cache: at most one notification per certificate per calendar day.
     * Intended to be triggered by the Scheduler (CheckCertificateExpiryJob).
     *
     * @dispatches  CertificateExpiryNotification mail (to all Admin users)
     * @sideeffects  Cache::put("cert_notified:{certId}:{date}") for deduplication
     */
    public function notify(Request $request): JsonResponse
    {
        Gate::authorize('metadata.generate');

        $report     = $this->buildExpiryReport();
        $notified   = 0;
        $recipients = $this->resolveRecipients();

        foreach ($report['expiring'] as $item) {
            // Avoid duplicate notifications — one per certificate per day
            $notifKey = "cert_notified:{$item['certificate_id']}:" . now()->toDateString();
            if (Cache::has($notifKey)) {
                continue;
            }

            Notification::route('mail', $recipients)->notify(
                new CertificateExpiryNotification(
                    entityId:    $item['entity_id'],
                    entityName:  $item['entity_name'],
                    entityType:  $item['entity_type'],
                    subject:     $item['subject'],
                    use:         $item['use'],
                    notAfter:    $item['not_after'],
                    daysRemaining: $item['days_remaining'],
                    severity:    $item['severity'],
                )
            );

            Cache::put($notifKey, true, now()->addHours(23));
            $notified++;
        }

        return response()->json([
            'notified'  => $notified,
            'skipped'   => count($report['expiring']) - $notified,
            'critical'  => $report['summary']['critical'],
            'warning'   => $report['summary']['warning'],
            'advisory'  => $report['summary']['advisory'],
        ]);
    }

    public function entityCertificates(Entity $entity): JsonResponse
    {
        Gate::authorize('entity.view');

        $entity->load('certificates');

        $certs = $entity->certificates->map(fn($cert) => $this->formatCertificate($cert));

        return response()->json([
            'entity_id'    => $entity->entity_id,
            'entity_type'  => $entity->type,
            'certificates' => $certs,
        ]);
    }

    private function buildDashboardData(int $days, ?Collection $managedIds = null): array
    {
        $expiringCerts = EntityCertificate::query()
            ->with(['entity:id,entity_id,type,status', 'entity.uiInfo', 'entity.contacts'])
            ->whereHas('entity', function ($q) use ($managedIds) {
                $q->where('status', 'active');
                if ($managedIds !== null) {
                    $q->whereHas('federations', fn($q) => $q->whereIn('federations.id', $managedIds));
                }
            })
            ->where('not_after', '<=', now()->addDays($days))
            ->orderBy('not_after')
            ->get();

        $expired   = $expiringCerts->filter(fn($c) => $c->not_after->isPast());
        $critical  = $expiringCerts->filter(fn($c) => !$c->not_after->isPast() && $this->daysRemaining($c) <= self::THRESHOLD_CRITICAL);
        $warning   = $expiringCerts->filter(fn($c) => $this->daysRemaining($c) > self::THRESHOLD_CRITICAL && $this->daysRemaining($c) <= self::THRESHOLD_WARNING);
        $advisory  = $expiringCerts->filter(fn($c) => $this->daysRemaining($c) > self::THRESHOLD_WARNING);

        // All active entity certs for the health overview
        $allCerts = EntityCertificate::query()
            ->whereHas('entity', function ($q) use ($managedIds) {
                $q->where('status', 'active');
                if ($managedIds !== null) {
                    $q->whereHas('federations', fn($q) => $q->whereIn('federations.id', $managedIds));
                }
            })
            ->where('not_after', '>', now()->addDays($days))
            ->count();

        return [
            'summary' => [
                'expired'  => $expired->count(),
                'critical' => $critical->count(),
                'warning'  => $warning->count(),
                'advisory' => $advisory->count(),
                'healthy'  => $allCerts,
                'checked_at' => now()->toIso8601String(),
            ],
            'expired'  => $expired->map(fn($c)  => $this->formatCertificate($c))->values()->all(),
            'critical' => $critical->map(fn($c) => $this->formatCertificate($c))->values()->all(),
            'warning'  => $warning->map(fn($c)  => $this->formatCertificate($c))->values()->all(),
            'advisory' => $advisory->map(fn($c) => $this->formatCertificate($c))->values()->all(),
        ];
    }

    private function buildExpiryReport(): array
    {
        $expiring = EntityCertificate::query()
            ->with(['entity:id,entity_id,type,status', 'entity.uiInfo', 'entity.contacts'])
            ->whereHas('entity', fn($q) => $q->where('status', 'active'))
            ->where('not_after', '<=', now()->addDays(self::THRESHOLD_INFO))
            ->orderBy('not_after')
            ->get()
            ->map(fn($cert) => [
                'certificate_id' => $cert->id,
                'entity_id'      => $cert->entity->entity_id,
                'entity_name'    => $cert->entity->getDisplayName(),
                'entity_type'    => $cert->entity->type,
                'use'            => $cert->use,
                'subject'        => $cert->subject,
                'not_after'      => $cert->not_after->toIso8601String(),
                'days_remaining' => $this->daysRemaining($cert),
                'severity'       => $this->severity($cert),
                'debian_weak'    => $cert->debian_weak,
                'contact_email'  => $cert->entity->contacts
                    ->whereIn('type', ['technical', 'security'])
                    ->first()?->email,
            ])
            ->values();

        $byEntity = $expiring->groupBy('entity_id');

        return [
            'generated_at' => now()->toIso8601String(),
            'summary'      => [
                'expired'  => $expiring->where('days_remaining', '<', 0)->count(),
                'critical' => $expiring->whereBetween('days_remaining', [0, self::THRESHOLD_CRITICAL])->count(),
                'warning'  => $expiring->whereBetween('days_remaining', [self::THRESHOLD_CRITICAL + 1, self::THRESHOLD_WARNING])->count(),
                'advisory' => $expiring->whereBetween('days_remaining', [self::THRESHOLD_WARNING + 1, self::THRESHOLD_ADVISORY])->count(),
                'info'     => $expiring->whereBetween('days_remaining', [self::THRESHOLD_ADVISORY + 1, self::THRESHOLD_INFO])->count(),
                'entities_affected' => $byEntity->count(),
            ],
            'expiring'     => $expiring,
            'by_entity'    => $byEntity,
        ];
    }

    private function formatCertificate(EntityCertificate $cert): array
    {
        $daysRemaining = $this->daysRemaining($cert);

        return [
            'id'             => $cert->id,
            'entity_db_id'   => $cert->entity?->id,
            'entity_id'      => $cert->entity?->entity_id,
            'entity_name'    => $cert->entity?->getDisplayName(),
            'entity_type'    => $cert->entity?->type,
            'entity_status'  => $cert->entity?->status,
            'contact_email'  => $cert->entity?->contacts
                ?->whereIn('type', ['technical', 'security'])
                ->first()?->email,
            'use'            => $cert->use,           // signing | encryption | both
            'subject'        => $cert->subject,
            'issuer'         => $cert->issuer,
            'serial'         => $cert->serial,
            'not_before'     => $cert->not_before?->toIso8601String(),
            'not_after'      => $cert->not_after?->toIso8601String(),
            'days_remaining' => $daysRemaining,
            'key_bits'       => $cert->key_bits,
            'fingerprint'    => $cert->fingerprint,
            'debian_weak'    => $cert->debian_weak,
            'severity'       => $this->severity($cert),
            'severity_label' => $this->severityLabel($cert),
        ];
    }

    private function daysRemaining(EntityCertificate $cert): int
    {
        return (int) now()->diffInDays($cert->not_after, false);
    }

    private function severity(EntityCertificate $cert): string
    {
        $days = $this->daysRemaining($cert);

        if ($days < 0)                               return 'expired';
        if ($days <= self::THRESHOLD_CRITICAL)        return 'critical';
        if ($days <= self::THRESHOLD_WARNING)         return 'warning';
        if ($days <= self::THRESHOLD_ADVISORY)        return 'advisory';
        if ($days <= self::THRESHOLD_INFO)            return 'info';

        return 'healthy';
    }

    private function severityLabel(EntityCertificate $cert): string
    {
        return match ($this->severity($cert)) {
            'expired'  => 'Expired',
            'critical' => 'Critical (< ' . self::THRESHOLD_CRITICAL . ' days)',
            'warning'  => 'Warning (< ' . self::THRESHOLD_WARNING . ' days)',
            'advisory' => 'Advisory (< ' . self::THRESHOLD_ADVISORY . ' days)',
            'info'     => 'Info (< ' . self::THRESHOLD_INFO . ' days)',
            default    => 'Healthy',
        };
    }

    private function resolveRecipients(): array
    {
        // Notify federation operators (Admin role users)
        return \App\Models\User::role('Admin')
            ->pluck('email')
            ->toArray();
    }
}
