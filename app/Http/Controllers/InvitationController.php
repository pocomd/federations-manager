<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\Invitation;
use App\Models\SystemPreference;
use App\Policies\EntityPolicy;
use App\Services\Auth\FederationScopeService;
use App\Services\Auth\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * List invitations — view differs by role.
     *
     * Managers (invitation.manage): all invitations, optionally scoped by federation.
     * Entity Managers (invitation.view): only invitations they personally sent.
     */
    public function index(): View
    {
        if (Auth::user()->can('invitation.manage')) {
            return $this->indexForManager();
        }

        Gate::authorize('invitation.view');

        return $this->indexForEntityManager();
    }

    private function indexForManager(): View
    {
        $scope = app(FederationScopeService::class);
        $query = Invitation::with(['invitedBy', 'federation', 'entity', 'revokedBy']);

        if ($scope->isConstrained()) {
            $query->whereIn('federation_id', $scope->ids());
        }

        $all = $query->get();

        [$pending, $accepted, $expired, $revoked] = $this->partition($all);

        $federations = $scope->scopeQuery(Federation::orderBy('name'))->get();

        return view('invitations.index', compact('pending', 'accepted', 'expired', 'revoked', 'federations'));
    }

    private function indexForEntityManager(): View
    {
        $all = Invitation::with(['entity', 'federation'])
            ->where('invited_by', Auth::id())
            ->get();

        [$pending, $accepted, $expired, $revoked] = $this->partition($all);

        $emEntities = Entity::whereHas('managers', fn ($q) => $q->where('user_id', Auth::id()))
            ->with(['contacts', 'federations' => fn ($q) => $q->wherePivot('status', 'active')])
            ->get();

        $emEntitiesData = $emEntities->map(fn ($e) => [
            'id'    => $e->id,
            'label' => $e->getDisplayName() ?? $e->entity_id,
            'contacts' => $e->contacts->map(fn ($c) => [
                'email' => $c->email,
                'label' => trim(trim($c->given_name . ' ' . $c->sur_name) . ' <' . $c->email . '> (' . $c->type . ')'),
            ])->values()->toArray(),
        ])->values()->toArray();

        return view('invitations.index-em', compact('pending', 'accepted', 'expired', 'revoked', 'emEntitiesData'));
    }

    private function partition(\Illuminate\Support\Collection $all): array
    {
        $pending  = $all->filter(fn ($i) => $i->accepted_at === null && $i->revoked_at === null && ($i->expires_at === null || $i->expires_at->isFuture()))->values();
        $accepted = $all->filter(fn ($i) => $i->accepted_at !== null)->values();
        $expired  = $all->filter(fn ($i) => $i->accepted_at === null && $i->revoked_at === null && $i->expires_at !== null && $i->expires_at->isPast())->values();
        $revoked  = $all->filter(fn ($i) => $i->revoked_at !== null)->values();

        return [$pending, $accepted, $expired, $revoked];
    }

    /**
     * Create and send an invitation — behaviour differs by role.
     *
     * Managers: can invite to any federation, entity assignment is optional.
     * Entity Managers: must be a manager of the entity; email must match an existing entity contact;
     *                  the entity must have at least one active federation membership.
     *
     * @dispatches  InvitationService::create (handles token generation and sends InvitationMail)
     */
    public function store(Request $request): RedirectResponse
    {
        if (Auth::user()->can('invitation.manage')) {
            return $this->storeForManager($request);
        }

        Gate::authorize('invitation.view');

        return $this->storeForEntityManager($request);
    }

    private function storeForManager(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email'         => 'required|email|max:255',
            'federation_id' => 'required|uuid|exists:federations,id',
            'entity_id'     => 'nullable|uuid|exists:entities,id',
        ]);

        $federation = Federation::findOrFail($validated['federation_id']);
        $entity     = ! empty($validated['entity_id']) ? Entity::find($validated['entity_id']) : null;

        $invitation = app(InvitationService::class)->create(
            $validated['email'],
            Auth::user(),
            $federation,
            $entity,
        );

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $entity?->id,
            'action'     => 'invitation_created',
            'old_values' => null,
            'new_values' => [
                'email'         => $invitation->email,
                'federation_id' => $federation->id,
                'entity_id'     => $entity?->id,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->route('invitations.index')
            ->with('success', "Invitation sent to {$invitation->email}.");
    }

    private function storeForEntityManager(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email'     => 'required|email|max:255',
            'entity_id' => 'required|uuid|exists:entities,id',
        ]);

        $entity = Entity::findOrFail($validated['entity_id']);

        abort_unless(EntityPolicy::isManager(Auth::user(), $entity), 403, 'You are not a manager of this entity.');

        abort_unless(
            $entity->contacts()->where('email', $validated['email'])->exists(),
            422,
            'The invitation email must match one of this entity\'s contacts.'
        );

        $federation = $entity->federations()->wherePivot('status', 'active')->first();
        abort_unless($federation !== null, 422, 'This entity is not an active member of any federation.');

        $invitation = app(InvitationService::class)->create(
            $validated['email'],
            Auth::user(),
            $federation,
            $entity,
        );

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $entity->id,
            'action'     => 'invitation_created',
            'old_values' => null,
            'new_values' => [
                'email'         => $invitation->email,
                'entity_id'     => $entity->id,
                'federation_id' => $federation->id,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->route('invitations.index')
            ->with('success', "Invitation sent to {$invitation->email}.");
    }

    /**
     * Resend an invitation email. Non-managers can only resend their own invitations.
     *
     * @dispatches  Mail::InvitationMail
     */
    public function resend(Invitation $invitation): RedirectResponse
    {
        if (! Auth::user()->can('invitation.manage')) {
            Gate::authorize('invitation.view');
            abort_unless($invitation->invited_by === Auth::id(), 403, 'You can only resend your own invitations.');
        }

        abort_unless($invitation->isUsable(), 422, 'Invitation is not active.');

        $invitation->loadMissing('federation');
        Mail::to($invitation->email)->send(new InvitationMail($invitation));

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $invitation->entity_id,
            'action'     => 'invitation_resent',
            'old_values' => null,
            'new_values' => ['email' => $invitation->email, 'invitation_id' => $invitation->id],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->back()->with('success', 'Invitation resent.');
    }

    /**
     * Revoke a pending invitation. Non-managers can only revoke their own invitations.
     */
    public function revoke(Invitation $invitation): RedirectResponse
    {
        if (! Auth::user()->can('invitation.manage')) {
            Gate::authorize('invitation.view');
            abort_unless($invitation->invited_by === Auth::id(), 403, 'You can only revoke your own invitations.');
        }

        $revokedAt = now();

        $invitation->update([
            'revoked_at' => $revokedAt,
            'revoked_by' => Auth::id(),
        ]);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $invitation->entity_id,
            'action'     => 'invitation_revoked',
            'old_values' => ['email' => $invitation->email],
            'new_values' => ['revoked_at' => $revokedAt->toISOString()],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->back()->with('success', 'Invitation revoked.');
    }

    /**
     * Replace an expired or revoked invitation with a fresh one.
     *
     * Deletes the old invitation record and creates a new one with a fresh token and expiry.
     * A reissue_comment is required when reissuing a previously revoked invitation.
     *
     * @authorizes  invitation.manage
     * @dispatches  Mail::InvitationMail (to the new invitation)
     * @sideeffects  Old invitation is hard-deleted; new one stores previous_token for audit trail
     */
    public function reissue(Request $request, Invitation $invitation): RedirectResponse
    {
        Gate::authorize('invitation.manage');

        $needsComment = $invitation->revoked_at !== null;

        if ($needsComment) {
            $request->validate(['reissue_comment' => 'required|string|max:1000']);
        }

        $hours    = (int) SystemPreference::get('invitation_expiry_hours', 72);
        $newToken = Str::random(64);

        Invitation::create([
            'email'           => $invitation->email,
            'token'           => $newToken,
            'role'            => $invitation->role,
            'invited_by'      => $invitation->invited_by,
            'federation_id'   => $invitation->federation_id,
            'entity_id'       => $invitation->entity_id,
            'expires_at'      => now()->addHours($hours),
            'accepted_at'     => null,
            'revoked_at'      => null,
            'reissue_comment' => $request->input('reissue_comment'),
            'previous_token'  => $invitation->token,
        ]);

        $invitation->delete();

        $newInvitation = Invitation::where('token', $newToken)
            ->with('federation')
            ->firstOrFail();

        Mail::to($newInvitation->email)->send(new InvitationMail($newInvitation));

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $invitation->entity_id,
            'action'     => 'invitation_reissued',
            'old_values' => ['email' => $invitation->email, 'old_token' => $invitation->token],
            'new_values' => ['email' => $newInvitation->email],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->back()->with('success', 'Invitation reissued.');
    }
}
