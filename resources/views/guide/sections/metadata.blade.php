<h2 class="h4 fw-bold mb-1">Metadata</h2>
<p class="text-muted small mb-4">URL: <code>/metadata</code></p>

<p>The metadata page lists all federations with their current cache status. From here you can generate and download signed aggregate SAML metadata XML for each federation.</p>

<h5 class="fw-semibold mt-4 mb-2">Generating Metadata</h5>
<p>Click <strong>Generate</strong> next to a federation (or use the <strong>Sign metadata</strong> button on the federation Metadata tab). This dispatches a background job that:</p>
<ol>
    <li>Collects all entities with pivot status = <span class="badge bg-success">active</span></li>
    <li>Builds a signed <code>&lt;md:EntitiesDescriptor&gt;</code> XML document</li>
    <li>Sets a <code>validUntil</code> attribute (configurable in Scheduler settings)</li>
    <li>Signs with <em>xmlsectool</em> if a signing key is configured</li>
    <li>Caches the result (default: 6 hours)</li>
</ol>

<h5 class="fw-semibold mt-4 mb-2">Automatic Cache Invalidation</h5>
<p>
    The federation metadata cache is cleared automatically in these situations — no manual regeneration needed:
</p>
<ul class="small">
    <li>An entity belonging to the federation is saved (any tab)</li>
    <li>A membership is approved, rejected, or removed</li>
    <li>An entity is suspended or reactivated</li>
</ul>
<p class="small text-muted">
    The <strong>Generate</strong> action triggered from the UI runs synchronously, so published feeds reflect
    changes immediately without waiting for a background queue worker.
</p>

<h5 class="fw-semibold mt-4 mb-2">Public Feed Endpoints</h5>
<p>These URLs require no authentication and are consumed by eduGAIN and remote federations:</p>
<table class="table table-sm table-bordered">
    <thead class="table-light"><tr><th>Endpoint</th><th>Description</th></tr></thead>
    <tbody>
        <tr>
            <td><code>/metadata/{federation}/feed</code></td>
            <td>Full signed aggregate for the federation</td>
        </tr>
        <tr>
            <td><code>/metadata/{federation}/edugain</code></td>
            <td>
                eduGAIN-filtered feed — only entities flagged <code>edugain = true</code>
                <strong>and</strong> with at least one X.509 certificate
            </td>
        </tr>
        <tr>
            <td><code>/signedmetadata/federation/{name}/metadata.xml</code></td>
            <td>Jagger-compatibility endpoint — serves the same eduGAIN-filtered subset as above, for federations with <code>jagger_compat_enabled = true</code></td>
        </tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Refreshing Status Without Reloading</h5>
<p class="small text-muted">The <strong>Sign Metadata</strong> card has a <i class="bi bi-arrow-clockwise"></i> refresh icon in its header. Clicking it re-fetches the signing status, timestamps, staleness warnings, and any queued job errors in place — no full page reload required. Use it after uploading a signing key or after a background signing job completes to confirm the updated state.</p>

<h5 class="fw-semibold mt-4 mb-2">Download</h5>
<p>The <strong>Download</strong> button on the federation Metadata tab downloads the cached XML as <code>{federation-slug}-metadata.xml</code>. If metadata has not been generated yet, an error is shown.</p>

<h5 class="fw-semibold mt-4 mb-2">MDQ / Per-Entity</h5>
<p>Each entity's raw SAML XML is available at <code>/entities/{id}/metadata.xml</code> — useful for debugging or copy-pasting into an IdP/SP configuration.</p>

<div class="alert alert-info small mt-4">
    <i class="bi bi-info-circle me-1"></i>
    SAML and OIDC metadata are kept fully separate. OIDC entities have their own discovery endpoints and are not included in SAML XML aggregates.
</div>
