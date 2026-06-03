<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\GenerateMetadataJob;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Federation;
use App\Services\Auth\FederationScopeService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * FederationManager Livewire component
 *
 * Renders the full federation management UI:
 *   - Accordion list of all federations with entity counts
 *   - Expand a federation to see member entities with pivot status
 *   - Approve / reject pending entity memberships (two-step: startReject + confirmReject)
 *   - Dispatch GenerateMetadataJob and poll until the cache key appears
 *
 * Note: do NOT rename any method to validate() — conflicts with Livewire base class.
 */
class FederationManager extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url] public string $search = '';
    #[Url] public string $statusFilter = '';

    public ?string $expandedFederationId = null;

    // federationId => true while a GenerateMetadataJob is in flight
    public array $generatingFor = [];


    #[Computed]
    public function federations(): LengthAwarePaginator
    {
        $query = Federation::withCount([
            'entities',
            'entities as active_entities_count'  => fn ($q) => $q->where('entity_federation.status', 'active'),
            'entities as pending_entities_count' => fn ($q) => $q->where('entity_federation.status', 'pending'),
        ])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('uri', 'like', '%' . $this->search . '%');
            }))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter));

        return app(FederationScopeService::class)
            ->scopeQuery($query)
            ->orderBy('name')
            ->paginate(20);
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->search       = '';
        $this->statusFilter = '';
        $this->resetPage();
    }


    #[Computed]
    public function expandedFederation(): ?Federation
    {
        if ($this->expandedFederationId === null) {
            return null;
        }

        return Federation::find($this->expandedFederationId);
    }

    #[Computed]
    public function expandedEntities(): Collection
    {
        if ($this->expandedFederationId === null) {
            return collect();
        }

        $federation = Federation::find($this->expandedFederationId);

        if (!$federation) {
            return collect();
        }

        return $federation->entities()
            ->with(['uiInfo', 'certificates', 'contacts'])
            ->withPivot(['status', 'approved_by', 'approved_at'])
            ->orderBy('entity_id')
            ->get();
    }


    public function toggleFederation(string $id): void
    {
        $this->expandedFederationId = $this->expandedFederationId === $id ? null : $id;

        // nothing to clear now that rejection is handled via SweetAlert2
    }


    /**
     * Approve a pending entity membership. Promotes entity status from draft/pending to active.
     * Clears both the regular and eduGAIN metadata caches for the federation.
     *
     * @authorizes  federation.approveRequest (Gate::check — soft error)
     * @dispatches  NotificationService::entity_approved
     * @sideeffects  Cache::forget federation_metadata + federation_edugain_metadata
     */
    public function approveEntity(string $federationId, string $entityId): void
    {
        if (!Gate::check('federation.approveRequest')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to approve entity memberships.');
            return;
        }

        $federation = Federation::findOrFail($federationId);
        $entity     = Entity::findOrFail($entityId);

        if (\App\Models\EntityFederation::hasActiveMembership($entity->id, $federation->id)) {
            $this->dispatch('notify', type: 'error', message: 'Entity already has an active membership in another federation.');
            return;
        }

        DB::transaction(function () use ($federation, $entity) {
            $federation->entities()->updateExistingPivot($entity->id, [
                'status'      => 'active',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            if (in_array($entity->status, ['draft', 'pending'], true)) {
                $entity->update(['status' => 'active']);
            }

            AuditLog::create([
                'user_id'    => Auth::id(),
                'entity_id'  => $entity->id,
                'action'     => 'federation_membership_approved',
                'old_values' => ['pivot_status' => 'pending'],
                'new_values' => ['pivot_status' => 'active', 'federation_id' => $federation->id],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);
        });

        app(\App\Services\Notification\NotificationService::class)->dispatch(
            'entity_approved',
            ['entity_name' => $entity->entity_id],
            $entity,
            $federation
        );

        Cache::forget("federation_metadata:{$federation->id}");
        Cache::forget("federation_edugain_metadata:{$federation->id}");

        Log::info('Entity membership approved via FederationManager', [
            'federation_id' => $federationId,
            'entity_id'     => $entity->entity_id,
            'approved_by'   => Auth::id(),
        ]);

        $this->dispatch('notify', type: 'success', message: 'Entity approved.');
    }


    /**
     * @authorizes  federation.rejectRequest (Gate::check — soft error)
     * @dispatches  NotificationService::entity_rejected
     */
    public function confirmReject(string $federationId, string $entityId, string $reason = ''): void
    {
        if (!Gate::check('federation.rejectRequest')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to reject entity memberships.');
            return;
        }

        $federation = Federation::findOrFail($federationId);
        $entity     = Entity::findOrFail($entityId);

        $federation->entities()->updateExistingPivot($entity->id, [
            'status' => 'rejected',
        ]);

        app(\App\Services\Notification\NotificationService::class)->dispatch(
            'entity_rejected',
            ['entity_name' => $entity->entity_id, 'reason' => $reason],
            $entity,
            $federation
        );

        Log::info('Entity membership rejected via FederationManager', [
            'federation_id' => $federationId,
            'entity_id'     => $entity->entity_id,
            'reason'        => $reason,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Entity rejected.');
    }


    /**
     * Dispatch GenerateMetadataJob to the queue and set the spinner state for this federation.
     *
     * @authorizes  metadata.generate (Gate::check — soft error)
     * @dispatches  GenerateMetadataJob (queued)
     */
    public function generateMetadata(string $federationId): void
    {
        if (!Gate::check('metadata.generate')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to generate metadata.');
            return;
        }

        $federation = Federation::findOrFail($federationId);

        // Mark as generating so the blade shows the spinner
        $this->generatingFor[$federationId] = true;

        // Dispatch to the configured queue (sync in local/test, redis in production)
        GenerateMetadataJob::dispatch($federation->id);

        Log::info('GenerateMetadataJob dispatched via FederationManager', [
            'federation_id' => $federationId,
        ]);

        $this->dispatch('notify', type: 'info', message: 'Metadata generation started.');
    }

    /**
     * Called by wire:poll — checks the cache for completed metadata jobs.
     * Early return (no queries) when nothing is pending.
     */
    public function pollMetadataStatus(): void
    {
        if (empty($this->generatingFor)) {
            return;
        }

        foreach (array_keys($this->generatingFor) as $federationId) {
            if (Cache::has("federation_metadata:{$federationId}")) {
                unset($this->generatingFor[$federationId]);
            }
        }
    }

    /**
     * Returns true when the cache has generated metadata for the given federation.
     * Called directly in the blade template.
     */
    public function hasMetadata(string $federationId): bool
    {
        return Cache::has("federation_metadata:{$federationId}");
    }

    #[On('federation-deactivated')]
    public function handleFederationDeactivated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.federation-manager');
    }
}
