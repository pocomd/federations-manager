<h2 class="h4 fw-bold mb-1">Statistics</h2>
<p class="text-muted small mb-4">URL: <code>/statistics</code> &nbsp;|&nbsp; Permission: <code>compliance.view</code> (Admin and Federation Manager)</p>

<h5 class="fw-semibold mt-4 mb-2">Charts</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light"><tr><th>Chart</th><th>Type</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td>Entity registration trend</td><td>Bar</td><td>Monthly registration count over 12 months</td></tr>
        <tr><td>Compliance score</td><td>Line</td><td>Average validation pass-rate per month</td></tr>
        <tr><td>Certificate expiry forecast</td><td>Bar</td><td>Certs expiring per 30-day window, next 6 months</td></tr>
        <tr><td>Federation active members</td><td>Horizontal bar</td><td>Active member count per federation</td></tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Summary Cards</h5>
<p>Four stat cards at the top: <strong>Total Entities</strong> · <strong>Active</strong> · <strong>Pending</strong> · <strong>Critical Certs</strong>.</p>

<h5 class="fw-semibold mt-4 mb-2">CSV Exports</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light"><tr><th>Export</th><th>URL</th><th>Contents</th></tr></thead>
    <tbody>
        <tr><td>Entities</td><td><code>/statistics/export/entities</code></td><td>entity_id, type, status, display_name, source, created_at</td></tr>
        <tr><td>Certificates</td><td><code>/statistics/export/certificates</code></td><td>entity_id, entity_name, use, subject, valid_from, valid_until, days_remaining</td></tr>
        <tr><td>Memberships</td><td><code>/statistics/export/memberships</code></td><td>federation, entity_id, entity_type, pivot_status, approved_at, approved_by</td></tr>
    </tbody>
</table>
