<h2 class="h4 fw-bold mb-1">Users</h2>
<p class="text-muted small mb-4">URL: <code>/users</code> &nbsp;|&nbsp; Permission: <code>user.view</code> (Admin only)</p>

<p>The user list shows name, email, role badge, status, last login date, and join date. Use the search box and the role/status filters to find specific users.</p>

<h5 class="fw-semibold mt-4 mb-2">Roles</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light"><tr><th>Role</th><th>What they can do</th></tr></thead>
    <tbody>
        <tr><td><span class="badge bg-danger">Admin</span></td><td>Full access — user management, system preferences, scheduler, audit log, all federations</td></tr>
        <tr><td><span class="badge bg-primary">Federation Manager</span></td><td>Manages assigned federations — approves membership, generates metadata, sends invitations</td></tr>
        <tr><td><span class="badge bg-secondary">Entity Manager</span></td><td>Creates and edits own entities, submits to federations, requests co-manager access</td></tr>
        <tr><td><span class="badge bg-light text-dark border">Guest</span></td><td>View metadata only</td></tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Actions</h5>
<ul>
    <li><strong>Change Role</strong> — use the role dropdown on the list or the user edit page. You cannot change your own role.</li>
    <li><strong>Suspend</strong> — prevents new logins. Existing sessions remain until they expire. You cannot suspend yourself.</li>
    <li><strong>Reinstate</strong> — restores login access for a suspended user.</li>
</ul>

<h5 class="fw-semibold mt-4 mb-2">User Show Page</h5>
<p>Shows the user's profile, role, status, last login, and their last 10 audit log entries with a link to the full audit log filtered to that user.</p>
