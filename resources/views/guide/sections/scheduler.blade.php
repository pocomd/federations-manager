<h2 class="h4 fw-bold mb-1">Scheduler</h2>
<p class="text-muted small mb-4">URL: <code>/scheduler</code> &nbsp;|&nbsp; Permission: <code>federation.create</code> (Admin only)</p>

<p>Controls all automated background jobs in the system. Every timing, threshold, and toggle is stored in the database — no code or server changes required. Each job group shows its last run time and has a <strong>Run Now</strong> button for immediate manual execution.</p>

<div class="alert alert-danger small">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    <strong>Both the cron entry and the queue worker must run at all times.</strong>
    The cron fires the scheduler every minute; the scheduler dispatches jobs to the queue;
    the queue worker processes those jobs. If the worker stops, all background tasks
    silently stop — metadata goes stale, certificate alerts are never sent, and the health
    check reports "No heartbeat".
</div>

<h5 class="fw-semibold mt-4 mb-2"><i class="bi bi-terminal me-1 text-muted"></i> System Setup</h5>

<p class="small mb-2 fw-semibold">1 — Crontab (run as the web server user)</p>
<pre class="bg-dark text-light p-3 rounded small mb-3">* * * * * cd /var/www/federations-manager && php artisan schedule:run >> /dev/null 2>&1</pre>

<p class="small mb-2 fw-semibold">2 — Queue worker</p>
<p class="small text-muted mb-2">
    Choose one method. systemd is recommended on modern distros (Ubuntu 22.04+, RHEL 9+).
</p>

<p class="small mb-1 fw-semibold">Option A — systemd (recommended)</p>
<p class="small text-muted mb-1">
    A ready-made unit file is included in the repository at <code>deploy/federations-management.service</code>.
    It starts automatically on boot and restarts the worker if it crashes.
</p>
<pre class="bg-dark text-light p-3 rounded small mb-3">sudo cp /var/www/federations-manager/deploy/federations-management.service /etc/systemd/system/federations-management.service
sudo systemctl daemon-reload
sudo systemctl enable --now federations-management
sudo systemctl status federations-management</pre>

<p class="small mb-1 fw-semibold">Option B — Supervisor</p>
<pre class="bg-dark text-light p-3 rounded small mb-2">[program:jagger-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/federations-manager/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=90
directory=/var/www/federations-manager
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/federations-manager/storage/logs/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600</pre>

<pre class="bg-dark text-light p-3 rounded small mb-3">sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start jagger-worker:*</pre>

<p class="small mb-2 fw-semibold">After deploying updates</p>
<pre class="bg-dark text-light p-3 rounded small mb-4">php artisan queue:restart
# The worker finishes its current job then exits; systemd/Supervisor restarts it automatically.</pre>

<div class="alert alert-warning small mb-4">
    <i class="bi bi-exclamation-triangle me-1"></i>
    On CentOS/RHEL, replace <code>www-data</code> with <code>nginx</code> and adjust paths if needed.
    Edit the <code>User=</code> and <code>Group=</code> lines in the systemd file before copying.
</div>

{{-- ── Metadata ── --}}
<h5 class="fw-semibold mt-4 mb-2"><i class="bi bi-file-code me-1 text-muted"></i> Metadata</h5>
<p>Controls automatic generation of signed aggregate SAML metadata XML for all active federations.</p>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr><th style="width:38%;">Setting</th><th style="width:15%;">Default</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>metadata_auto_generate_enabled</code><br><span class="text-muted small">Auto-generate metadata</span></td>
            <td>Off</td>
            <td>When enabled the system automatically regenerates metadata for every active federation on the configured interval. Disable if you prefer to trigger generation manually.</td>
        </tr>
        <tr>
            <td><code>metadata_auto_generate_interval</code><br><span class="text-muted small">Generation interval (minutes)</span></td>
            <td>60</td>
            <td>How often (in minutes) metadata is regenerated. Lower values mean consumers always get fresh metadata but increase CPU/signing load. Typical values: 60 (hourly) or 360 (every 6 hours).</td>
        </tr>
        <tr>
            <td><code>metadata_valid_until_hours</code><br><span class="text-muted small">Metadata validUntil (hours)</span></td>
            <td>6</td>
            <td>Sets the <code>validUntil</code> attribute on the generated <code>&lt;md:EntitiesDescriptor&gt;</code>. Remote consumers (eduGAIN, SP federations) will reject metadata past this timestamp. Must be greater than the generation interval — a common rule is validUntil = 2–4× the interval.</td>
        </tr>
        <tr>
            <td><code>metadata_cache_duration_hours</code><br><span class="text-muted small">Cache duration (hours)</span></td>
            <td>6</td>
            <td>How long the generated XML is kept in the Redis cache before being considered stale. When a public feed endpoint is requested and the cache is empty, metadata is regenerated on-the-fly.</td>
        </tr>
    </tbody>
</table>

{{-- ── Validation ── --}}
<h5 class="fw-semibold mt-4 mb-2"><i class="bi bi-shield-check me-1 text-muted"></i> Validation</h5>
<p>Controls scheduled bulk re-validation of all entities against compliance rules.</p>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr><th style="width:38%;">Setting</th><th style="width:15%;">Default</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>validation_auto_enabled</code><br><span class="text-muted small">Auto-validate entities</span></td>
            <td>Off</td>
            <td>When enabled, all entities are re-validated on the configured day and time. Useful for catching regressions after rule updates without manually triggering validation per entity.</td>
        </tr>
        <tr>
            <td><code>validation_schedule_day</code><br><span class="text-muted small">Validation day</span></td>
            <td>0 (Sunday)</td>
            <td>Day of the week to run the full bulk validation. 0 = Sunday, 1 = Monday, … 6 = Saturday. Choose a low-traffic day as validation can be intensive on large registries.</td>
        </tr>
        <tr>
            <td><code>validation_schedule_time</code><br><span class="text-muted small">Validation time (HH:MM)</span></td>
            <td>03:00</td>
            <td>Time of day (24-hour, server timezone) to run validation. Schedule during off-peak hours to avoid impacting interactive users.</td>
        </tr>
    </tbody>
</table>

{{-- ── Certificates ── --}}
<h5 class="fw-semibold mt-4 mb-2"><i class="bi bi-key me-1 text-muted"></i> Certificates</h5>
<p>Controls automated certificate expiry monitoring and notification emails. Thresholds also drive the colour-coding on the <a href="{{ route('certificates.monitor') }}">Certificates</a> monitoring page.</p>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr><th style="width:38%;">Setting</th><th style="width:15%;">Default</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>cert_check_enabled</code><br><span class="text-muted small">Auto certificate expiry check</span></td>
            <td>On</td>
            <td>When enabled, the system runs a daily check and sends notification emails to entity technical contacts whose certificates are approaching expiry.</td>
        </tr>
        <tr>
            <td><code>cert_check_time</code><br><span class="text-muted small">Daily check time (HH:MM)</span></td>
            <td>08:00</td>
            <td>Time of day to run the certificate check. Notifications are sent at this time — choose a working-hours time so recipients see them promptly.</td>
        </tr>
        <tr>
            <td><code>cert_notify_days_critical</code><br><span class="text-muted small">Critical threshold (days)</span></td>
            <td>14</td>
            <td>Certificates expiring within this many days are flagged <span class="badge bg-danger">Critical</span>. An urgent notification is sent. Federation metadata publication may be affected once the cert expires.</td>
        </tr>
        <tr>
            <td><code>cert_notify_days_warning</code><br><span class="text-muted small">Warning threshold (days)</span></td>
            <td>30</td>
            <td>Certificates expiring within this many days (but outside the critical window) are flagged <span class="badge bg-warning text-dark">Warning</span>. A standard renewal reminder is sent.</td>
        </tr>
        <tr>
            <td><code>cert_notify_days_advisory</code><br><span class="text-muted small">Advisory threshold (days)</span></td>
            <td>60</td>
            <td>Certificates expiring within this many days (but outside warning) are flagged <span class="badge bg-info text-dark">Advisory</span>. An informational notice is sent — useful for organisations with slow renewal processes.</td>
        </tr>
        <tr>
            <td><code>cert_notify_days_info</code><br><span class="text-muted small">Info threshold (days)</span></td>
            <td>90</td>
            <td>Certificates expiring within this many days are shown in the <span class="badge bg-secondary">Info</span> severity bucket on the monitoring dashboard. No notification email is sent at this level.</td>
        </tr>
    </tbody>
</table>

{{-- ── eduGAIN ── --}}
<h5 class="fw-semibold mt-4 mb-2"><i class="bi bi-globe me-1 text-muted"></i> eduGAIN</h5>
<p>Controls automated synchronisation with the eduGAIN upstream metadata feed.</p>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr><th style="width:38%;">Setting</th><th style="width:15%;">Default</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>edugain_sync_enabled</code><br><span class="text-muted small">Auto eduGAIN sync</span></td>
            <td>Off</td>
            <td>When enabled, the system periodically fetches the full eduGAIN aggregate XML, imports new entities with <code>source = edugain</code>, updates existing ones, and marks entities no longer in the feed as <code>suspended</code>.</td>
        </tr>
        <tr>
            <td><code>edugain_sync_interval_hours</code><br><span class="text-muted small">Sync interval (hours)</span></td>
            <td>24</td>
            <td>How often to fetch and process the upstream eduGAIN feed. The eduGAIN MDS updates approximately every 2 hours; daily sync (24 h) is sufficient for most deployments.</td>
        </tr>
        <tr>
            <td><code>edugain_metadata_url</code><br><span class="text-muted small">eduGAIN metadata URL</span></td>
            <td><code>https://mds.edugain.org/edugain-v2.xml</code></td>
            <td>URL of the eduGAIN aggregate XML file to fetch. The default points to the official eduGAIN MDS v2 feed. Change this only if your organisation uses a national mirror or a test feed.</td>
        </tr>
    </tbody>
</table>

{{-- ── Cleanup ── --}}
<h5 class="fw-semibold mt-4 mb-2"><i class="bi bi-trash me-1 text-muted"></i> Cleanup</h5>
<p>Controls automatic pruning of historical records to keep the database from growing unbounded.</p>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr><th style="width:38%;">Setting</th><th style="width:15%;">Default</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>cleanup_enabled</code><br><span class="text-muted small">Auto cleanup</span></td>
            <td>On</td>
            <td>Master switch for all cleanup jobs. When disabled no records are pruned regardless of the individual thresholds below.</td>
        </tr>
        <tr>
            <td><code>cleanup_metadata_days</code><br><span class="text-muted small">Delete metadata files older than (days)</span></td>
            <td>7</td>
            <td>Removes cached metadata XML files older than this many days. Short retention (7 days) is usually sufficient — old cached XML has no value once superseded.</td>
        </tr>
        <tr>
            <td><code>cleanup_validation_days</code><br><span class="text-muted small">Prune validation results older than (days)</span></td>
            <td>90</td>
            <td>Deletes entity validation results older than this many days. Keeping 90 days gives enough history for trend analysis on the Statistics page. Increase if you need a longer compliance audit trail.</td>
        </tr>
        <tr>
            <td><code>cleanup_audit_days</code><br><span class="text-muted small">Archive audit logs older than (days)</span></td>
            <td>365</td>
            <td>Removes audit log entries older than this many days. 365 days (one year) satisfies most compliance requirements. Increase for longer retention; decrease for lower storage usage.</td>
        </tr>
    </tbody>
</table>
