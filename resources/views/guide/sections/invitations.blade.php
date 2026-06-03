<h2 class="h4 fw-bold mb-1">Invitations</h2>
<p class="text-muted small mb-4">URL: <code>/invitations</code> &nbsp;|&nbsp; Requests: <code>/invitation-requests</code></p>

<h5 class="fw-semibold mt-4 mb-2">Send an Invitation</h5>
<p>Permission required: <code>invitation.manage</code> (Admin or Federation Manager). Click <strong>New Invitation</strong> and fill in:</p>
<ul>
    <li><strong>Email</strong> — recipient's email address</li>
    <li><strong>Federation</strong> — which federation this invitation is for</li>
    <li><strong>Entity</strong> <em>(optional)</em> — the registrant will be added as co-manager of this entity on registration</li>
</ul>
<p>The invitation email is sent immediately with a unique token-based registration link. Federation Managers see only invitations for their assigned federations.</p>

<h5 class="fw-semibold mt-4 mb-2">Invitation Actions</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light"><tr><th>Action</th><th>When</th><th>What it does</th></tr></thead>
    <tbody>
        <tr><td>Copy URL</td><td>Always</td><td>Copies the registration link to clipboard</td></tr>
        <tr><td>Resend</td><td>Pending only</td><td>Re-sends the email with the same token</td></tr>
        <tr><td>Revoke</td><td>Pending only</td><td>Marks as revoked — link becomes invalid</td></tr>
        <tr><td>Reissue</td><td>Expired or Revoked</td><td>Creates a new token; revoked invitations require a comment</td></tr>
    </tbody>
</table>
<p><strong>Tabs:</strong> Pending · Accepted · Expired · Revoked</p>

<h5 class="fw-semibold mt-4 mb-2">Co-Manager Invitation Requests</h5>
<p>Entity Managers can request that a Federation Manager invites people listed in an entity's contact records.</p>
<p><strong>As Entity Manager:</strong> open the entity show page → <strong>Request Co-Manager</strong> section → click <strong>Request Invitation</strong>.</p>
<p><strong>As Federation Manager</strong> on <code>/invitation-requests</code>:</p>
<ul>
    <li><strong>Approve</strong> — if the email belongs to an existing user they are added directly; otherwise an invitation is created and sent.</li>
    <li><strong>Reject</strong> — requires a rejection reason shown to the requester.</li>
</ul>

<h5 class="fw-semibold mt-4 mb-2">Accepting an Invitation</h5>
<p>The link <code>/register/{token}</code> is public — no prior login needed. The page shows which federation you are joining and which entity you will co-manage (if applicable). Fill in your name and a password to register and be logged in immediately.</p>
<p>Expired, revoked, or already-used links show a clear error page.</p>
