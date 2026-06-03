{{-- Layout-free partial — used by buildOpsIndex() for search indexing and included by guide.operations.system --}}

{{-- 1 --}}
<div x-show="op === 1" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Import from Jagger</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.create</span>
    </div>
    <p class="small">The Jagger import tool migrates entity and federation data from a legacy Jagger 2 database directly into the registry. It connects to the remote MySQL database using credentials you supply at import time — no permanent configuration is stored.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Running an import</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Import → From Jagger</strong> in the sidebar.</li>
        <li class="mb-2">Enter the Jagger database connection details: <strong>Host</strong>, <strong>Port</strong>, <strong>Database</strong>, <strong>Username</strong>, and optionally <strong>Password</strong>.</li>
        <li class="mb-2">Click <strong>Test Connection</strong> to verify connectivity and preview what will be imported (entity and federation counts).</li>
        <li class="mb-2">Choose import options:
            <ul class="mt-1">
                <li class="mb-1"><strong>Only local entities</strong> — skip entities sourced from external feeds (recommended for first imports).</li>
                <li class="mb-1"><strong>Skip existing entities</strong> — entities already present in the registry are not overwritten.</li>
                <li class="mb-1"><strong>Clear registry first</strong> — deletes all existing entities and federations before import. Use with extreme caution.</li>
            </ul>
        </li>
        <li>Click <strong>Run Import</strong>. The results page shows counts of entities and federations imported, skipped, and any errors encountered.</li>
    </ol>

    <div class="alert alert-warning py-2 small mt-3">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Clear registry first</strong> is destructive and irreversible. All existing entities, federations, and memberships will be deleted before the import runs. Only use this option on a fresh installation.
    </div>

    <div class="alert alert-info py-2 small mt-2">
        <i class="bi bi-info-circle me-1"></i>
        The import is recorded in the audit log with the connection host, database name, and import options used.
    </div>
</div>

{{-- 2 --}}
<div x-show="op === 2" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Run a scheduled job immediately</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.create</span>
    </div>
    <p class="small">Scheduled jobs normally run automatically on their configured intervals. The <strong>Run Now</strong> button lets you trigger any job immediately — useful after configuration changes or to force a refresh without waiting for the next scheduled run.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Available jobs</h6>
    <ul class="small">
        <li class="mb-2"><strong>Auto-generate metadata</strong> — regenerates the published XML metadata bundle for all active federations.</li>
        <li class="mb-2"><strong>Validate all metadata</strong> — runs the rule engine against all entities and updates their compliance scores.</li>
        <li class="mb-2"><strong>Certificate expiry check</strong> — scans certificates across all entities and sends expiry digest emails to Admin users for any certificates within the 90-day window.</li>
        <li class="mb-2"><strong>Sync eduGAIN metadata</strong> — fetches the latest eduGAIN aggregate and updates entity-level integration status.</li>
        <li><strong>Cleanup</strong> — removes orphaned records, expired tokens, and stale cache entries.</li>
    </ul>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Steps</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Scheduler</strong> in the sidebar.</li>
        <li class="mb-2">Find the job card for the job you want to trigger.</li>
        <li class="mb-2">Click <strong>Run Now</strong>. The job is dispatched to the queue immediately.</li>
        <li>The <strong>Last run</strong> timestamp updates once the job completes. Refresh the page to see the updated time.</li>
    </ol>

    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        Each manual trigger is recorded in the audit log with the job name and the user who initiated it.
    </div>
</div>

{{-- 3 --}}
<div x-show="op === 3" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Configure scheduler timings</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.create</span>
    </div>
    <p class="small">Scheduler settings control how frequently each automated job runs and whether certain jobs are enabled at all. Changes take effect at the next scheduler tick — no application restart is required.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Steps</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Scheduler</strong> in the sidebar.</li>
        <li class="mb-2">In the <strong>Settings</strong> panel, adjust the interval or enabled state for each job group.</li>
        <li>Click <strong>Save Settings</strong>. The new values are stored in the database and picked up by the scheduler on its next run.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Last-run timestamps</h6>
    <p class="small">Each job card shows a <strong>Last run</strong> timestamp drawn from the application cache. If the timestamp shows <em>Never</em>, the job has not been executed since the cache was last cleared, or it has never run.</p>

    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        Scheduler setting changes are recorded in the audit log with the before and after values for every modified key.
    </div>
</div>

{{-- 4 --}}
<div x-show="op === 4" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Set system preferences</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.create</span>
    </div>
    <p class="small">System preferences control application-wide behaviour including branding, mail settings, authentication policy, federation defaults, and eduGAIN integration. All preferences are stored in the database and take effect immediately without a restart.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Preference categories</h6>
    <ul class="small">
        <li class="mb-2"><strong>General</strong> — application name, URL, support email, cookie consent banner, and UI language settings (visible only when <code>I18N_ENABLED=true</code>).</li>
        <li class="mb-2"><strong>Page</strong> — footer custom text and browser tab title prefix.</li>
        <li class="mb-2"><strong>Mail</strong> — sender name and address used for all outgoing notifications, and the mail signature appended to each email.</li>
        <li class="mb-2"><strong>Authentication</strong> — default role assigned to new SAML logins, session timeout, and login attempt lockout threshold.</li>
        <li class="mb-2"><strong>Federation</strong> — number of days before a pending membership request auto-expires (1–7 days).</li>
        <li><strong>eduGAIN</strong> — enable/disable the eduGAIN integration, your federation code (e.g. LEAF), ECCS connectivity status display, and entity-presence checks.</li>
    </ul>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Steps</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Preferences</strong> in the sidebar.</li>
        <li class="mb-2">Edit the values in the relevant category section.</li>
        <li>Click <strong>Save Preferences</strong>. Changes take effect immediately. All changes are recorded in the audit log.</li>
    </ol>
</div>

{{-- 5 --}}
<div x-show="op === 5" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Manage compliance rules</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.create</span>
    </div>
    <p class="small">Compliance rules are defined in code and registered in the database via the <code>rules:sync</code> command. Each rule inspects an entity's metadata and contributes to its overall compliance score. Rules can be enabled or disabled globally without any code changes.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Viewing rules</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Compliance Rules</strong> in the sidebar. The table lists all registered rules with their ID, name, category, severity, and current enabled state.</li>
        <li>A green badge indicates an active rule; a grey badge indicates a disabled rule.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Toggling a rule</h6>
    <ol class="small">
        <li class="mb-2">Click <strong>Enable</strong> or <strong>Disable</strong> next to the rule you want to change. The state toggles immediately.</li>
        <li>Disabled rules are excluded from compliance score calculations and do not generate violations. Re-enabling a rule causes it to be applied again on the next validation run.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Syncing rules from code</h6>
    <p class="small">When new rules are added to the codebase (or existing rules are removed), click <strong>Sync Rules from Code</strong> to update the database. Existing <em>active</em> states are preserved for rules that were already registered; new rules are inserted as enabled by default.</p>

    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        Each toggle is recorded in the audit log (action: <code>rule_toggled</code>) with the rule ID, name, and old/new active state.
    </div>
</div>

{{-- 6 --}}
<div x-show="op === 6" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Configure a webhook endpoint</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.create</span>
    </div>
    <p class="small">Webhooks allow external systems to receive real-time notifications when events occur in the registry (entity changes, federation events, metadata regeneration, etc.). Each endpoint is registered with a URL, a set of subscribed event types, and an auto-generated signing secret.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Creating an endpoint</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Webhooks</strong> in the sidebar and click <strong>Add Endpoint</strong>.</li>
        <li class="mb-2">Enter the <strong>URL</strong> that will receive POST requests.</li>
        <li class="mb-2">Select one or more <strong>Event types</strong> to subscribe to.</li>
        <li class="mb-2">Optionally add a <strong>Description</strong> for your own reference.</li>
        <li>Click <strong>Create Endpoint</strong>. The endpoint is immediately active and a signing secret is generated. Copy the secret now — it is not shown again.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Verifying deliveries</h6>
    <ol class="small">
        <li class="mb-2">Click an endpoint name on the Webhooks list to open its detail page.</li>
        <li class="mb-2">The <strong>Recent Deliveries</strong> table shows each delivery attempt, its HTTP status, and whether it succeeded.</li>
        <li>Click <strong>Retry</strong> on a failed delivery to re-queue it immediately.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Deleting an endpoint</h6>
    <p class="small">On the Webhooks list, click <strong>Delete</strong> next to the endpoint. Deletion is immediate and cannot be undone. All historical delivery records for that endpoint are also removed.</p>

    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        Each webhook payload is signed with the endpoint's secret using HMAC-SHA256. The signature is sent in the <code>X-Webhook-Signature</code> header so your receiver can verify authenticity.
    </div>
</div>
