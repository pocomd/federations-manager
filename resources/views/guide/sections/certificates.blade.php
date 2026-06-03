<h2 class="h4 fw-bold mb-1">Certificates</h2>
<p class="text-muted small mb-4">URL: <code>/certificates/monitor</code></p>

<p>The certificate monitoring dashboard shows all entity certificates grouped by expiry severity. Click any severity card to filter the table to that group.</p>

<h5 class="fw-semibold mt-4 mb-2">Severity Thresholds</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light"><tr><th>Severity</th><th>Threshold</th></tr></thead>
    <tbody>
        <tr><td><span class="badge bg-dark">Expired</span></td><td><code>not_after</code> &lt; now</td></tr>
        <tr><td><span class="badge bg-danger">Critical</span></td><td>≤ 14 days remaining</td></tr>
        <tr><td><span class="badge bg-warning text-dark">Warning</span></td><td>≤ 30 days remaining</td></tr>
        <tr><td><span class="badge bg-info text-dark">Advisory</span></td><td>≤ 60 days remaining</td></tr>
        <tr><td><span class="badge bg-secondary">Info</span></td><td>≤ 90 days remaining</td></tr>
        <tr><td><span class="badge bg-success">Healthy</span></td><td>&gt; 90 days remaining</td></tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Filters</h5>
<p>The table can be filtered by <strong>severity</strong>, <strong>federation</strong>, and <strong>entity type</strong>.</p>

<h5 class="fw-semibold mt-4 mb-2">Send Notifications</h5>
<p>The <strong>Send Notifications</strong> button (Admin only) manually triggers expiry notification emails to entity technical contacts. The scheduler runs this automatically each day based on the configured thresholds.</p>

<h5 class="fw-semibold mt-4 mb-2">API Access</h5>
<p>Send <code>Accept: application/json</code> to <code>/certificates/monitor</code> to get a structured expiry report as JSON — useful for external monitoring tools.</p>

<div class="alert alert-info small mt-4">
    <i class="bi bi-info-circle me-1"></i>
    Certificate thresholds (critical / warning / advisory days) are configurable in <a href="{{ route('scheduler.index') }}">Scheduler settings</a> under the <strong>Certificates</strong> group.
</div>
