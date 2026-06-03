{{-- Layout-free partial — used by buildOpsIndex() for search indexing and included by guide.operations.monitoring --}}

{{-- 1 --}}
<div x-show="op === 1" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Monitor certificate expiry</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.view</span>
    </div>
    <p class="small">The certificate monitor gives an overview of X.509 certificate health across all active entities. Expired or near-expiry certificates in published metadata can cause authentication failures for all federation participants consuming that metadata.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Reading the dashboard</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Certificates</strong> in the sidebar. The summary row at the top shows counts for each severity level.</li>
        <li class="mb-2">Below the summary, certificates are grouped by severity:
            <ul class="mt-1">
                <li class="mb-1"><span class="badge bg-danger">Expired</span> — past the <code>not_after</code> date. Replace immediately.</li>
                <li class="mb-1"><span class="badge bg-danger">Critical</span> — expires within <strong>14 days</strong>. Rotation is urgent.</li>
                <li class="mb-1"><span class="badge bg-warning text-dark">Warning</span> — expires within <strong>30 days</strong>. Plan rotation now.</li>
                <li class="mb-1"><span class="badge bg-info text-dark">Advisory</span> — expires within <strong>60 days</strong>. Schedule rotation.</li>
                <li><span class="badge bg-secondary">Info</span> — expires within <strong>90 days</strong>. No immediate action required.</li>
            </ul>
        </li>
        <li class="mb-2">Each row shows the entity name and ID, certificate use (signing / encryption / both), the subject CN, and exact expiry date. Click the entity name to go to its detail page and update the certificate.</li>
        <li>Use the <strong>Show certificates expiring within</strong> filter to narrow the view (14, 30, 60, or 90 days).</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Automated notifications</h6>
    <p class="small">The scheduler sends a daily certificate expiry digest email to all Admin users when any certificate falls within the 90-day window. The notification is rate-limited to one per certificate per calendar day to avoid inbox flooding.</p>

    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        Federation managers see only certificates for entities in their managed federations. Admins see all certificates across the registry.
    </div>
</div>

{{-- 2 --}}
<div x-show="op === 2" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">View the audit log</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">user.view</span>
    </div>
    <p class="small">The audit log records every significant action taken in the system — entity changes, federation operations, invitation events, user role changes, and more. Each entry captures who acted, what changed (before and after values), which entity was affected, and the actor's IP address.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Browsing the log</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Audit Log</strong> in the sidebar. Entries are shown newest-first, 50 per page.</li>
        <li class="mb-2">Each row shows the timestamp, acting user, action badge, affected entity (linked), and IP address.</li>
        <li>Click any row to expand it and see the <strong>before / after diff</strong> — old values on the left, new values on the right, with changed fields highlighted.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Filtering</h6>
    <p class="small">Use the filter bar at the top to narrow the log:</p>
    <ul class="small">
        <li class="mb-1"><strong>Entity</strong> — show only entries for a specific entity.</li>
        <li class="mb-1"><strong>User</strong> — show only entries from a specific user (Admin / FM only; non-admin users see only their own entries).</li>
        <li class="mb-1"><strong>Action</strong> — partial-match filter on the action string (e.g. type <code>approved</code> to see all approval events).</li>
        <li><strong>Date range</strong> — from / to date pickers for time-bounded queries.</li>
    </ul>
    <p class="small mt-2">Filters combine with AND logic. Click <strong>Reset</strong> to clear all filters at once.</p>

    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        Entity managers and guests see only their own log entries. Federation managers and admins see the full log.
    </div>
</div>

{{-- 3 --}}
<div x-show="op === 3" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Export statistics as CSV</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">compliance.view</span>
    </div>
    <p class="small">The Statistics page provides charts for entity trends, compliance scores, and certificate forecasts. Three CSV exports are available for use in external reporting tools or spreadsheets.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Available exports</h6>
    <ul class="small">
        <li class="mb-2">
            <strong>Export Entities (CSV)</strong> — one row per entity. Columns: <code>entity_id</code>, <code>type</code>, <code>status</code>, <code>display_name</code>, <code>source</code>, <code>created_at</code>.
        </li>
        <li class="mb-2">
            <strong>Export Certificates (CSV)</strong> — one row per certificate. Columns: <code>entity_id</code>, <code>use</code>, <code>not_before</code>, <code>not_after</code>, <code>subject</code>, <code>issuer</code>. Ordered by expiry date ascending — useful for spotting near-expiry certificates in a spreadsheet.
        </li>
        <li>
            <strong>Export Memberships (CSV)</strong> — one row per entity–federation membership. Columns: <code>entity_id</code>, <code>federation_name</code>, <code>membership_status</code>, <code>approved_at</code>.
        </li>
    </ul>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Downloading</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Statistics</strong> in the sidebar.</li>
        <li class="mb-2">Scroll to the <strong>Data Exports</strong> section.</li>
        <li>Click the relevant <strong>Export … (CSV)</strong> button. The file is generated and downloaded immediately. Filenames follow the pattern <code>{type}-{date}.csv</code>.</li>
    </ol>

    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        The statistics charts are scoped to federations you manage (FM view) or show the full registry (Admin view). The CSV exports include all data regardless of scope.
    </div>
</div>
