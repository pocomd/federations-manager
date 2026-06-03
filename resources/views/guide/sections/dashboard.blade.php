<h2 class="h4 fw-bold mb-1">Dashboard</h2>
<p class="text-muted small mb-4">URL: <code>/dashboard</code></p>

<p>The dashboard gives a real-time overview of the registry state.</p>

<h5 class="fw-semibold mt-4 mb-2">Summary Cards</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light"><tr><th>Card</th><th>What it shows</th></tr></thead>
    <tbody>
        <tr><td>Total Entities</td><td>Count of all entities in the registry</td></tr>
        <tr><td>Active Entities</td><td>Entities with status = <span class="badge bg-success">active</span></td></tr>
        <tr><td>Federations</td><td>Total number of federations</td></tr>
        <tr><td>Critical Certs</td><td>Certificates expiring within 14 days</td></tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Below the Cards</h5>
<ul>
    <li><strong>Recent Entities</strong> — last 5 entities registered, with type badge and status</li>
    <li><strong>Certificate Expiry Summary</strong> — counts by severity: expired / critical / warning / advisory</li>
    <li><strong>Recent Audit Log</strong> — last 5 actions taken by any user across the system</li>
</ul>
