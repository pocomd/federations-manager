{{-- Layout-free partial — used by buildOpsIndex() for search indexing and included by guide.operations.users --}}

{{-- 1 --}}
<div x-show="op === 1" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Invite a new user</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">invitation.manage</span>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle ms-1">invitation.view</span>
    </div>
    <p class="small">Federation managers and admins can send an invitation to any email address. Entity managers can invite contacts from their entities' contact lists. The recipient registers via a one-time link and is added to the system immediately upon accepting.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Sending an invitation <small class="fw-normal text-muted">(Federation Manager / Admin)</small></h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Invitations</strong> in the sidebar and click <strong>New Invitation</strong>.</li>
        <li class="mb-2">Enter the recipient's <strong>email address</strong>, select the <strong>federation</strong>, and optionally select an <strong>entity</strong> they will co-manage.</li>
        <li class="mb-2">Click <strong>Send Invitation</strong> — an email with a registration link is sent immediately.</li>
        <li>The invitation appears in the <strong>Pending</strong> tab. From there you can <strong>Copy URL</strong> (to share via another channel), <strong>Resend</strong> (if the email was lost or expired), or <strong>Revoke</strong> (to cancel the invitation).</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Sending an invitation <small class="fw-normal text-muted">(Entity Manager)</small></h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>My Invitations</strong> in the sidebar and click <strong>New Invitation</strong>.</li>
        <li class="mb-2">Select the <strong>Entity</strong> — only entities you manage are shown.</li>
        <li class="mb-2">Select the <strong>Contact to invite</strong> — only contacts already registered on the entity are available. Add the contact in the entity edit form first if they are not listed.</li>
        <li>Click <strong>Send Invitation</strong>. This does not require FM approval — the email is sent immediately.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Accepting an invitation <small class="fw-normal text-muted">(recipient)</small></h6>
    <ol class="small">
        <li class="mb-2">Open the link from the invitation email.</li>
        <li class="mb-2">Fill in your <strong>name</strong> and <strong>password</strong> and click <strong>Register</strong>.</li>
        <li>You are logged in immediately with the role assigned by the inviter.</li>
    </ol>

    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        If the link has expired before the recipient registers, find the invitation under the <strong>Expired</strong> tab and click <strong>Reissue</strong> to generate and send a new link. Expired links cannot be used — the recipient sees an error page with the inviter's contact details so they know who to ask for a new link.
    </div>
</div>

{{-- 2 --}}
<div x-show="op === 2" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Approve / reject an access request</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">invitation.manage</span>
    </div>
    <p class="small">Entity managers can request that a contact person listed on their entity is invited to become a co-manager. The request goes to the federation manager for approval. This operation covers the FM side — reviewing, approving, or rejecting those requests.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Reviewing requests</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Invitation Requests</strong> in the sidebar. The list shows all pending requests scoped to federations you manage, ordered by submission date.</li>
        <li class="mb-2">Each row shows the entity, the contact's name and email, the contact type, and who submitted the request.</li>
        <li class="mb-2">To <strong>approve</strong>: click <strong>Approve</strong>. The system checks if the contact already has an account:
            <ul class="mt-1">
                <li>If yes — they are added as co-manager for the entity immediately, with no email sent.</li>
                <li>If no — an invitation email is sent. If an active (not accepted, not revoked) invitation to that address already exists for the entity, a duplicate is not created.</li>
            </ul>
        </li>
        <li>To <strong>reject</strong>: click <strong>Reject</strong>, enter a reason (required), and click <strong>Confirm Reject</strong>. The requester sees the reason in their My Requests view.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Submitting a request <small class="fw-normal text-muted">(Entity Manager)</small></h6>
    <ol class="small">
        <li class="mb-2">Open the entity's detail page. The <strong>Request Co-Manager</strong> section lists the entity's contacts. Add a contact in the entity edit form if the person you need is not listed.</li>
        <li class="mb-2">Click <strong>Request Invitation</strong> next to the contact's email. In the modal, select the federation and click <strong>Send Request</strong>.</li>
        <li class="mb-2">Track status under <strong>My Requests</strong> in the sidebar:
            <ul class="mt-1">
                <li><span class="badge bg-warning text-dark">Pending</span> — not yet reviewed.</li>
                <li><span class="badge bg-success">Approved</span> — accepted.</li>
                <li><span class="badge bg-danger">Rejected</span> — declined; the Note column shows the FM's reason.</li>
            </ul>
        </li>
        <li>To withdraw a <span class="badge bg-warning text-dark">Pending</span> request, click <strong>Cancel</strong> on the row and confirm.</li>
    </ol>
</div>

{{-- 3 --}}
<div x-show="op === 3" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Change a user's role</h5>
        <span class="badge bg-danger bg-opacity-75 text-white border border-danger-subtle">Admin only</span>
    </div>
    <p class="small">User roles control what actions a user can perform across the application. Only administrators can change roles. Available roles are:</p>
    <ul class="small mb-3">
        <li class="mb-1"><strong>Admin</strong> — full access to all features including user management, system preferences, and all federation and entity operations.</li>
        <li class="mb-1"><strong>Federation Manager</strong> — can create and manage federations, approve entity membership, send invitations, and manage entity managers within their federations.</li>
        <li class="mb-1"><strong>Entity Manager</strong> — can register, edit, and manage entities they are assigned to. Can request co-manager invitations and send direct invitations to entity contacts.</li>
        <li><strong>Guest</strong> — read-only access. Can view entities, federations, and metadata but cannot make any changes.</li>
    </ul>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Changing a role</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Users</strong> in the sidebar and find the user.</li>
        <li class="mb-2">Open the user's detail page and click <strong>Edit</strong>.</li>
        <li class="mb-2">In the <strong>Role</strong> card on the right, select the new role from the dropdown and click <strong>Apply</strong>.</li>
        <li>The change takes effect on the user's next request — no re-login is required. The user receives an in-app notification about the change.</li>
    </ol>

    <div class="alert alert-warning py-2 small mt-3">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Downgrading a Federation Manager to Entity Manager or Guest does not automatically remove them from any federations they manage. Review their federation assignments after the role change and remove them from their federation assignments (federation op 7).
    </div>
</div>

{{-- 4 --}}
<div x-show="op === 4" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Suspend / reactivate a user account</h5>
        <span class="badge bg-danger bg-opacity-75 text-white border border-danger-subtle">Admin only</span>
    </div>
    <p class="small">Suspending an account prevents the user from logging in. Their data, entity assignments, and federation memberships are preserved — nothing is deleted. The account can be reinstated at any time.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Suspending an account</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Users</strong> in the sidebar and open the user's detail page.</li>
        <li class="mb-2">Click <strong>Edit</strong>, then find the <strong>Suspend Account</strong> card on the right.</li>
        <li class="mb-2">Click <strong>Suspend Account</strong> and confirm the prompt.</li>
        <li>The account status changes to <span class="badge bg-danger">Suspended</span> immediately. If the user is currently logged in, their next request will be rejected and they will be redirected to the login page. An in-app notification is created on their account.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Reinstating an account</h6>
    <ol class="small">
        <li class="mb-2">Open the suspended user's detail page and click <strong>Edit</strong>.</li>
        <li class="mb-2">The card now shows <strong>Account Suspended</strong> with a <strong>Reinstate Account</strong> button. Click it — no confirmation prompt is shown.</li>
        <li>The status returns to <span class="badge bg-success">Active</span> immediately. The user can log in again on their next attempt. An in-app notification is created on their account.</li>
    </ol>

    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        You cannot suspend your own account. The suspend/reinstate card is hidden when viewing your own user edit page.
    </div>
</div>
