<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\FederationEntityInvitation;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class FederationMembership extends Component
{
    public string $federationId;

    public function mount(string $federationId): void
    {
        $this->federationId = $federationId;
    }


    #[Computed]
    public function federation(): Federation
    {
        return Federation::findOrFail($this->federationId);
    }

    #[Computed]
    public function pendingEntities(): Collection
    {
        return $this->federation->entities()
            ->wherePivot('status', 'pending')
            ->withPivot(['expires_at', 'rejection_reason'])
            ->with('uiInfo')
            ->orderBy('entity_id')
            ->get();
    }

    #[Computed]
    public function idps(): Collection
    {
        return $this->federation->entities()
            ->where('entities.type', 'idp')
            ->wherePivot('status', 'active')
            ->with('uiInfo')
            ->orderBy('entity_id')
            ->get();
    }

    #[Computed]
    public function sps(): Collection
    {
        return $this->federation->entities()
            ->where('entities.type', 'sp')
            ->wherePivot('status', 'active')
            ->with('uiInfo')
            ->orderBy('entity_id')
            ->get();
    }

    #[Computed]
    public function pendingInvitations(): Collection
    {
        return FederationEntityInvitation::where('federation_id', $this->federationId)
            ->where('status', 'pending')
            ->with(['entity.uiInfo', 'invitedBy'])
            ->orderBy('created_at')
            ->get();
    }


    /**
     * Approve a pending membership. Also promotes entity status from draft/pending to active.
     * Busts #[Computed] cache for pendingEntities, idps, and sps via unset().
     *
     * @authorizes  federation.approveRequest (Gate::check — soft error)
     * @dispatches  NotificationService::entity_approved
     * @emits       membership-pending-count-changed
     */
    public function approveEntity(string $entityId): void
    {
        if (! Gate::check('federation.approveRequest')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to approve memberships.');
            return;
        }

        $federation = $this->federation;
        $entity     = Entity::findOrFail($entityId);

        if (\App\Models\EntityFederation::hasActiveMembership($entity->id, $federation->id)) {
            $this->dispatch('notify', type: 'error', message: 'Entity already has an active membership in another federation.');
            return;
        }

        $federation->entities()->updateExistingPivot($entity->id, [
            'status'      => 'active',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        if (in_array($entity->status, ['draft', 'pending'], true)) {
            $entity->update(['status' => 'active']);
        }

        app(NotificationService::class)->dispatch(
            'entity_approved',
            ['entity_name' => $entity->entity_id],
            $entity,
            $federation
        );

        Log::info('Entity membership approved via FederationMembership', [
            'federation_id' => $this->federationId,
            'entity_id'     => $entity->entity_id,
        ]);

        unset($this->pendingEntities, $this->idps, $this->sps);

        $this->dispatch('notify', type: 'success', message: 'Entity approved.');
        $this->dispatch('membership-pending-count-changed', count: $this->pendingEntities->count());
    }

    /**
     * @authorizes  federation.rejectRequest (Gate::check — soft error)
     * @dispatches  NotificationService::entity_rejected
     * @emits       membership-pending-count-changed
     */
    public function rejectEntity(string $entityId, string $reason = ''): void
    {
        if (! Gate::check('federation.rejectRequest')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to reject memberships.');
            return;
        }

        $federation = $this->federation;
        $entity     = Entity::findOrFail($entityId);

        $federation->entities()->updateExistingPivot($entity->id, [
            'status'           => 'rejected',
            'rejection_reason' => $reason ?: null,
        ]);

        app(NotificationService::class)->dispatch(
            'entity_rejected',
            ['entity_name' => $entity->entity_id, 'reason' => $reason],
            $entity,
            $federation
        );

        Log::info('Entity membership rejected via FederationMembership', [
            'federation_id' => $this->federationId,
            'entity_id'     => $entity->entity_id,
        ]);

        unset($this->pendingEntities);

        $this->dispatch('notify', type: 'success', message: 'Entity rejected.');
        $this->dispatch('membership-pending-count-changed', count: $this->pendingEntities->count());
    }

    /** @listens  entity-suspended-in-federation — busts idps/sps computed cache */
    #[On('entity-suspended-in-federation')]
    public function onEntitySuspended(): void
    {
        unset($this->idps, $this->sps);
    }

    /**
     * Set entity status to active directly (no federation re-approval required).
     * Intended for re-enabling a suspended entity within an existing federation context.
     *
     * @authorizes  entity.edit (Gate::check — soft error)
     */
    public function enableEntity(string $entityId): void
    {
        if (! Gate::check('entity.edit')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to enable entities.');
            return;
        }

        $entity = Entity::findOrFail($entityId);

        $entity->update(['status' => 'active']);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $entity->id,
            'action'     => 'entity_reactivated',
            'old_values' => ['status' => 'suspended'],
            'new_values' => ['status' => 'active'],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        Log::info('Entity enabled via FederationMembership', [
            'federation_id' => $this->federationId,
            'entity_id'     => $entity->entity_id,
        ]);

        unset($this->idps, $this->sps);

        $this->dispatch('notify', type: 'success', message: 'Entity enabled.');
    }

    /**
     * Detach a suspended entity from this federation. Requires entity to be suspended first
     * — guards against removing active entities accidentally.
     *
     * @authorizes  entity.removeFromFederation (Gate::check — soft error)
     */
    public function removeEntity(string $entityId): void
    {
        if (! Gate::check('entity.removeFromFederation')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to remove entities.');
            return;
        }

        $entity = Entity::findOrFail($entityId);

        if ($entity->status !== 'suspended') {
            $this->dispatch('notify', type: 'error',
                message: 'Entity must be suspended before it can be removed from the federation.');
            return;
        }

        $this->federation->entities()->detach($entity->id);

        Log::info('Entity removed from federation via FederationMembership', [
            'federation_id' => $this->federationId,
            'entity_id'     => $entity->entity_id,
        ]);

        unset($this->idps, $this->sps);

        $this->dispatch('notify', type: 'success', message: 'Entity removed from federation.');
    }

    /**
     * Cancel a pending FederationEntityInvitation. Guards against cancelling already-responded invitations.
     *
     * @authorizes  FederationPolicy::update
     */
    public function cancelInvitation(string $invitationId): void
    {
        if (! Gate::check('update', $this->federation)) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to cancel invitations.');
            return;
        }

        $invitation = FederationEntityInvitation::findOrFail($invitationId);

        if (! $invitation->isPending()) {
            $this->dispatch('notify', type: 'error', message: 'This invitation has already been responded to.');
            return;
        }

        $invitation->delete();

        unset($this->pendingInvitations);

        $this->dispatch('notify', type: 'success', message: 'Invitation cancelled.');
    }

    /** @listens  invitations-sent — busts pendingInvitations computed cache */
    #[On('invitations-sent')]
    public function refreshInvitations(): void
    {
        unset($this->pendingInvitations);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.federation-membership');
    }
}
