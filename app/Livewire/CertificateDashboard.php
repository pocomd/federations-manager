<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\EntityCertificate;
use App\Models\Federation;
use App\Notifications\CertificateExpiryNotification;
use App\Services\Auth\FederationScopeService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CertificateDashboard Livewire component
 *
 * Displays X.509 certificate expiry status across all active entities,
 * grouped by severity (expired / critical / warning / advisory / info / healthy).
 *
 * Severity thresholds (days remaining):
 *   expired  → < 0
 *   critical → 0–14
 *   warning  → 15–30
 *   advisory → 31–60
 *   info     → 61–90
 *   healthy  → > 90
 *
 * Note: do NOT rename any method to validate() — conflicts with Livewire base class.
 */
class CertificateDashboard extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    private const THRESHOLD_CRITICAL = 14;
    private const THRESHOLD_WARNING  = 30;
    private const THRESHOLD_ADVISORY = 60;
    private const THRESHOLD_INFO     = 90;

    #[Url]
    public string $filterSeverity   = '';    // ''|expired|critical|warning|advisory|info|healthy

    #[Url]
    public string $filterFederation = '';    // federation id

    #[Url]
    public string $filterType       = '';    // ''|idp|sp

    #[Url]
    public string $sortBy           = 'not_after';

    #[Url]
    public string $sortDir          = 'asc';

    public bool   $notifying        = false;
    public ?string $lastNotified    = null;  // human-readable timestamp

    public array $availableFederations = [];

    public function mount(): void
    {
        $scope = app(FederationScopeService::class);
        $this->availableFederations = $scope->scopeQuery(Federation::orderBy('name'))
            ->get(['id', 'name'])
            ->toArray();
    }

    public function updatedFilterSeverity():   void { $this->resetPage(); }
    public function updatedFilterFederation(): void { $this->resetPage(); }
    public function updatedFilterType():       void { $this->resetPage(); }


    #[Computed]
    public function summary(): array
    {
        $base = $this->baseCertQuery()
            ->when($this->filterType, fn($q) => $q->whereHas('entity', fn($q2) => $q2->where('type', $this->filterType)))
            ->when($this->filterFederation, fn($q) => $q->whereHas('entity.federations', fn($q2) => $q2->where('federations.id', $this->filterFederation)));

        $now = now();

        return [
            'expired'  => (clone $base)->where('not_after', '<', $now)->count(),
            'critical' => (clone $base)->where('not_after', '>=', $now)->where('not_after', '<=', $now->copy()->addDays(self::THRESHOLD_CRITICAL))->count(),
            'warning'  => (clone $base)->where('not_after', '>', $now->copy()->addDays(self::THRESHOLD_CRITICAL))->where('not_after', '<=', $now->copy()->addDays(self::THRESHOLD_WARNING))->count(),
            'advisory' => (clone $base)->where('not_after', '>', $now->copy()->addDays(self::THRESHOLD_WARNING))->where('not_after', '<=', $now->copy()->addDays(self::THRESHOLD_ADVISORY))->count(),
            'info'     => (clone $base)->where('not_after', '>', $now->copy()->addDays(self::THRESHOLD_ADVISORY))->where('not_after', '<=', $now->copy()->addDays(self::THRESHOLD_INFO))->count(),
            'healthy'  => (clone $base)->where('not_after', '>', $now->copy()->addDays(self::THRESHOLD_INFO))->count(),
        ];
    }


    #[Computed]
    public function certificates(): LengthAwarePaginator
    {
        $now = now();

        return $this->baseCertQuery()
            ->with(['entity:id,entity_id,type,status', 'entity.uiInfo'])
            ->when($this->filterType, fn($q) => $q->whereHas('entity', fn($q2) => $q2->where('type', $this->filterType)))
            ->when($this->filterFederation, fn($q) => $q->whereHas('entity.federations', fn($q2) => $q2->where('federations.id', $this->filterFederation)))
            ->when($this->filterSeverity === 'expired',  fn($q) => $q->where('not_after', '<', $now))
            ->when($this->filterSeverity === 'critical', fn($q) => $q->where('not_after', '>=', $now)->where('not_after', '<=', $now->copy()->addDays(self::THRESHOLD_CRITICAL)))
            ->when($this->filterSeverity === 'warning',  fn($q) => $q->where('not_after', '>', $now->copy()->addDays(self::THRESHOLD_CRITICAL))->where('not_after', '<=', $now->copy()->addDays(self::THRESHOLD_WARNING)))
            ->when($this->filterSeverity === 'advisory', fn($q) => $q->where('not_after', '>', $now->copy()->addDays(self::THRESHOLD_WARNING))->where('not_after', '<=', $now->copy()->addDays(self::THRESHOLD_ADVISORY)))
            ->when($this->filterSeverity === 'info',     fn($q) => $q->where('not_after', '>', $now->copy()->addDays(self::THRESHOLD_ADVISORY))->where('not_after', '<=', $now->copy()->addDays(self::THRESHOLD_INFO)))
            ->when($this->filterSeverity === 'healthy',  fn($q) => $q->where('not_after', '>', $now->copy()->addDays(self::THRESHOLD_INFO)))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(25);
    }


    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }


    public function filterBySeverity(string $severity): void
    {
        $this->filterSeverity = $this->filterSeverity === $severity ? '' : $severity;
        $this->resetPage();
    }


    /**
     * Send CertificateExpiryNotification to all Admin users for certificates expiring within
     * THRESHOLD_INFO days. Deduplicates via Cache::put("cert_notified:{certId}:{date}", 23h TTL)
     * to prevent duplicate notifications within a calendar day.
     *
     * @authorizes  metadata.generate (Gate::check — soft error)
     * @dispatches  CertificateExpiryNotification mail (to Admin role users)
     * @sideeffects  Cache::put cert_notified keys for each notified certificate
     */
    public function sendNotifications(): void
    {
        if (!Gate::check('metadata.generate')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to send notifications.');
            return;
        }

        $this->notifying = true;

        $now      = now();
        $expiring = EntityCertificate::query()
            ->with(['entity:id,entity_id,type,status', 'entity.uiInfo', 'entity.contacts'])
            ->whereHas('entity', fn($q) => $q->where('status', 'active'))
            ->where('not_after', '<=', $now->copy()->addDays(self::THRESHOLD_INFO))
            ->orderBy('not_after')
            ->get();

        $recipients = \App\Models\User::role('Admin')->pluck('email')->toArray();
        $notified   = 0;

        foreach ($expiring as $cert) {
            $notifKey = "cert_notified:{$cert->id}:" . $now->toDateString();

            if (\Illuminate\Support\Facades\Cache::has($notifKey)) {
                continue;
            }

            $daysRemaining = (int) $now->diffInDays($cert->not_after, false);
            $severity      = $this->certSeverity($daysRemaining);

            Notification::route('mail', $recipients)->notify(
                new CertificateExpiryNotification(
                    entityId:      $cert->entity->entity_id,
                    entityName:    $cert->entity->getDisplayName() ?? $cert->entity->entity_id,
                    entityType:    $cert->entity->type,
                    subject:       $cert->subject,
                    use:           $cert->use,
                    notAfter:      $cert->not_after->toIso8601String(),
                    daysRemaining: $daysRemaining,
                    severity:      $severity,
                )
            );

            \Illuminate\Support\Facades\Cache::put($notifKey, true, $now->copy()->addHours(23));
            $notified++;
        }

        $this->notifying = false;

        $this->lastNotified = now()->format('H:i:s');

        Log::info("Certificate expiry notifications sent via CertificateDashboard", [
            'notified'   => $notified,
            'recipients' => $recipients,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Notifications sent.');
    }


    private function baseCertQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $scope = app(FederationScopeService::class);
        return EntityCertificate::query()
            ->whereHas('entity', fn ($q) => $scope->scopeEntityQuery($q->where('status', 'active')));
    }


    public function severityColor(string $severity): string
    {
        return match($severity) {
            'expired'  => 'danger',
            'critical' => 'orange',
            'warning'  => 'warning',
            'advisory' => 'info',
            'info'     => 'primary',
            default    => 'success',
        };
    }

    public function severityBootstrap(string $severity): string
    {
        return match($severity) {
            'expired'  => 'danger',
            'critical' => 'danger',
            'warning'  => 'warning',
            'advisory' => 'info',
            'info'     => 'primary',
            default    => 'success',
        };
    }

    public function certSeverityForCert(EntityCertificate $cert): string
    {
        if (!$cert->not_after) {
            return 'info';
        }

        return $this->certSeverity((int) now()->diffInDays($cert->not_after, false));
    }

    private function certSeverity(int $daysRemaining): string
    {
        if ($daysRemaining < 0)                             return 'expired';
        if ($daysRemaining <= self::THRESHOLD_CRITICAL)     return 'critical';
        if ($daysRemaining <= self::THRESHOLD_WARNING)      return 'warning';
        if ($daysRemaining <= self::THRESHOLD_ADVISORY)     return 'advisory';
        if ($daysRemaining <= self::THRESHOLD_INFO)         return 'info';

        return 'healthy';
    }

    public function render(): View
    {
        return view('livewire.certificate-dashboard');
    }
}
