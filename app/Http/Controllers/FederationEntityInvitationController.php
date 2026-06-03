<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FederationEntityInvitation;
use App\Services\Notification\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class FederationEntityInvitationController extends Controller
{
    /**
     * Accept a federation invitation — attaches the entity to the federation as active immediately.
     *
     * Bypasses the normal pending/approval flow: the FM's act of inviting constitutes pre-approval.
     * Also promotes entity status from draft/pending → active on first accepted federation membership.
     *
     * @authorizes  EntityPolicy::update (on the invited entity)
     * @dispatches  NotificationService::federation_entity_invitation_accepted
     */
    public function accept(FederationEntityInvitation $invitation): RedirectResponse
    {
        Gate::authorize('update', $invitation->entity);

        if (! $invitation->isPending()) {
            return redirect()->route('entities.show', $invitation->entity)
                ->with('error', 'This invitation has already been responded to.');
        }

        $invitation->update([
            'status'       => 'accepted',
            'responded_by' => Auth::id(),
            'responded_at' => now(),
        ]);

        // Attach entity to the federation as active (FM already approved by inviting)
        $federation = $invitation->federation;
        $entity     = $invitation->entity;

        if (! $federation->entities()->where('entities.id', $entity->id)->exists()) {
            $federation->entities()->attach($entity->id, [
                'status'      => 'active',
                'approved_by' => $invitation->invited_by,
                'approved_at' => now(),
            ]);
        }

        // Promote entity status on first federation acceptance
        if (in_array($entity->status, ['draft', 'pending'], true)) {
            $entity->update(['status' => 'active']);
        }

        app(NotificationService::class)->dispatch(
            'federation_entity_invitation_accepted',
            [
                'entity_name'     => $entity->entity_id,
                'federation_name' => $federation->name,
                'action_url'      => route('federations.show', $federation),
            ],
            $entity,
            $federation
        );

        return redirect()->route('entities.show', $entity)
            ->with('success', "You have joined federation \"{$federation->name}\".");
    }

    /**
     * Cancel a pending federation invitation. Authorized on the federation (not the entity).
     *
     * @authorizes  FederationPolicy::update (on the federation side, not the entity)
     */
    public function cancel(FederationEntityInvitation $invitation): RedirectResponse
    {
        Gate::authorize('update', $invitation->federation);

        if (! $invitation->isPending()) {
            return redirect()->route('federations.show', $invitation->federation)
                ->with('error', 'This invitation has already been responded to and cannot be cancelled.');
        }

        $invitation->delete();

        return redirect()->route('federations.show', $invitation->federation)
            ->with('success', 'Invitation cancelled.');
    }

    /**
     * Decline a federation invitation from the entity side.
     *
     * @authorizes  EntityPolicy::update (on the invited entity)
     * @dispatches  NotificationService::federation_entity_invitation_rejected
     */
    public function reject(FederationEntityInvitation $invitation): RedirectResponse
    {
        Gate::authorize('update', $invitation->entity);

        if (! $invitation->isPending()) {
            return redirect()->route('entities.show', $invitation->entity)
                ->with('error', 'This invitation has already been responded to.');
        }

        $invitation->update([
            'status'       => 'rejected',
            'responded_by' => Auth::id(),
            'responded_at' => now(),
        ]);

        $federation = $invitation->federation;
        $entity     = $invitation->entity;

        app(NotificationService::class)->dispatch(
            'federation_entity_invitation_rejected',
            [
                'entity_name'     => $entity->entity_id,
                'federation_name' => $federation->name,
                'action_url'      => route('federations.show', $federation),
            ],
            $entity,
            $federation
        );

        return redirect()->route('entities.show', $entity)
            ->with('success', "Invitation from \"{$federation->name}\" declined.");
    }
}
