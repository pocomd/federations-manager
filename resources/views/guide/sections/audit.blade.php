<h2 class="h4 fw-bold mb-1">Audit Log</h2>
<p class="text-muted small mb-4">URL: <code>/audit</code> &nbsp;|&nbsp; Permission: <code>user.view</code> (Admin only)</p>

<p>Every significant action in the system is recorded automatically: entity create/update/delete, federation changes, membership approvals/rejections, user role changes, preference updates, scheduler runs, and more.</p>

<h5 class="fw-semibold mt-4 mb-2">Filters</h5>
<ul>
    <li><strong>Entity</strong> — show only actions on a specific entity</li>
    <li><strong>User</strong> — show only actions by a specific user</li>
    <li><strong>Action type</strong> — filter by event name (e.g. <code>entity.updated</code>, <code>federation.approved</code>)</li>
    <li><strong>Date range</strong> — narrow to a specific time window</li>
</ul>

<h5 class="fw-semibold mt-4 mb-2">Expandable Diff Rows</h5>
<p>Click any row to expand it and see the <strong>before/after JSON diff</strong> of exactly what changed. Fields that were added, removed, or modified are highlighted.</p>

<h5 class="fw-semibold mt-4 mb-2">Per-User Activity</h5>
<p>On any user's show page, click <strong>View full audit log</strong> to open the audit log pre-filtered to that user. This is also accessible via the <em>My Activity</em> link in the top-right user dropdown for any user to view their own history.</p>

<div class="alert alert-info small mt-4">
    <i class="bi bi-info-circle me-1"></i>
    Old audit records are automatically pruned based on the <strong>Cleanup</strong> retention setting in <a href="{{ route('scheduler.index') }}">Scheduler</a>.
</div>
