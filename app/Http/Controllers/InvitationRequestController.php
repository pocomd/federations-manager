<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\EntityManager;
use App\Models\Invitation;
use App\Models\InvitationRequest;
use App\Models\User;
use App\Policies\EntityPolicy;
use App\Services\Auth\FederationScopeService;
use App\Services\Auth\InvitationService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Illuminate\Auth\Access\AuthorizationException;

class InvitationRequestController extends Controller
{
    /**
     * List invitation requests — view differs by role.
     *
     * Managers (invitation.manage): all pending requests, optionally scoped by federation.
     * Entity Managers (invitation.view): only their own requests across all statuses.
     */
    public function index(): View
    {
        if (Auth::user()->can('invitation.manage')) {
            $scope = app(FederationScopeService::class);

            $query = InvitationRequest::with(['entity', 'requestedBy', 'federation'])
                ->pending()
                ->orderBy('created_at', 'asc');

            if ($scope->isConstrained()) {
                $query->whereIn('federation_id', $scope->ids());
            }

            return view('invitation-requests.index', [
                'requests'  => $query->get(),
                'isManager' => true,
            ]);
        }

        Gate::authorize('invitation.view');

        $requests = InvitationRequest::with(['entity', 'federation'])
            ->where('requested_by', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('invitation-requests.index', [
            'requests'  => $requests,
            'isManager' => false,
        ]);
    }

    /**
     * Submit an invitation request for a contact to be granted Entity Manager access.
     *
     * Caller must be a manager of the entity. Guards against duplicate pending requests for the same email.
     *
     * @dispatches  NotificationService::invitation_request_created
     */
    public function store(Request $request, Entity $entity): RedirectResponse
    {
        Gate::authorize('entity.requestContactInvitation');

        abort_unless(EntityPolicy::isManager(Auth::user(), $entity), 403, 'You are not a manager of this entity.');

        $validated = $request->validate([
            'contact_email' => 'required|email|max:255',
            'contact_type'  => 'required|in:technical,support,security,administrative',
            'contact_name'  => 'nullable|string|max:255',
            'federation_id' => 'required|uuid|exists:federations,id',
        ]);

        $duplicate = InvitationRequest::where('entity_id', $entity->id)
            ->where('contact_email', $validated['contact_email'])
            ->where('status', 'pending')
            ->exists();

        if ($duplicate) {
            return redirect()->back()
                ->with('error', 'A pending invitation request for this contact already exists.');
        }

        $invRequest = InvitationRequest::create([
            'entity_id'     => $entity->id,
            'contact_email' => $validated['contact_email'],
            'contact_type'  => $validated['contact_type'],
            'contact_name'  => $validated['contact_name'],
            'requested_by'  => Auth::id(),
            'federation_id' => $validated['federation_id'],
            'status'        => 'pending',
        ]);

        app(NotificationService::class)->dispatch(
            'invitation_request_created',
            ['contact_email' => $invRequest->contact_email, 'entity_id' => $entity->id],
            $entity
        );

        return redirect()->back()->with('success', 'Invitation request submitted.');
    }

    /**
     * Approve an invitation request — branches on whether the contact already has an account.
     *
     * Existing user: immediately creates an EntityManager record; no invitation email sent.
     * New user: creates an Invitation via InvitationService, which sends InvitationMail.
     *
     * @authorizes  invitation.manage
     * @dispatches  InvitationService::create + InvitationMail (new users only)
     * @dispatches  NotificationService::invitation_request_approved
     */
    public function approve(Request $request, InvitationRequest $invRequest): RedirectResponse
    {
        Gate::authorize('invitation.manage');

        $existingUser = User::where('email', $invRequest->contact_email)->first();

        if ($existingUser) {
            $alreadyManager = EntityManager::where('entity_id', $invRequest->entity_id)
                ->where('user_id', $existingUser->id)
                ->exists();

            if (! $alreadyManager) {
                EntityManager::create([
                    'entity_id' => $invRequest->entity_id,
                    'user_id'   => $existingUser->id,
                    'role'      => 'manager',
                    'added_by'  => Auth::id(),
                    'added_at'  => now(),
                ]);
            }

            $invRequest->update([
                'status'      => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);
        } else {
            $invRequest->loadMissing(['entity', 'federation']);

            $pendingExists = Invitation::where('email', $invRequest->contact_email)
                ->where('entity_id', $invRequest->entity_id)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->exists();

            if (! $pendingExists) {
                $invitation = app(InvitationService::class)->create(
                    $invRequest->contact_email,
                    Auth::user(),
                    $invRequest->federation,
                    $invRequest->entity
                );

                $invitation->update(['invitation_request_id' => $invRequest->id]);
            }

            $invRequest->update([
                'status'      => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);
        }

        app(NotificationService::class)->dispatch(
            'invitation_request_approved',
            ['contact_email' => $invRequest->contact_email],
            $invRequest->entity
        );

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $invRequest->entity_id,
            'action'     => 'invitation_request_approved',
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'approved', 'contact_email' => $invRequest->contact_email],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->back()->with('success', 'Request approved.');
    }

    /**
     * Reject an invitation request. A FM note (reason) is required.
     *
     * @authorizes  invitation.manage
     * @dispatches  NotificationService::invitation_request_rejected
     */
    public function reject(Request $request, InvitationRequest $invRequest): RedirectResponse
    {
        Gate::authorize('invitation.manage');

        $request->validate(['fm_note' => 'required|string|max:1000']);

        $invRequest->update([
            'status'      => 'rejected',
            'fm_note'     => $request->fm_note,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        app(NotificationService::class)->dispatch(
            'invitation_request_rejected',
            ['reason' => $invRequest->fm_note, 'contact_email' => $invRequest->contact_email],
            $invRequest->entity
        );

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $invRequest->entity_id,
            'action'     => 'invitation_request_rejected',
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'rejected', 'contact_email' => $invRequest->contact_email, 'reason' => $invRequest->fm_note],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->back()->with('success', 'Request rejected.');
    }

    /**
     * Cancel a pending invitation request. Only the original requester may cancel their own request.
     */
    public function cancel(InvitationRequest $invRequest): RedirectResponse
    {
        Gate::authorize('entity.requestContactInvitation');

        if ($invRequest->requested_by !== Auth::id()) {
            abort(403, 'You can only cancel your own requests.');
        }

        if ($invRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending requests can be cancelled.');
        }

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $invRequest->entity_id,
            'action'     => 'invitation_request_cancelled',
            'old_values' => ['status' => 'pending', 'contact_email' => $invRequest->contact_email],
            'new_values' => ['status' => 'cancelled'],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        $invRequest->delete();

        return redirect()->back()->with('success', 'Invitation request cancelled.');
    }
}
