<h2 class="h4 fw-bold mb-1">Compliance Rules</h2>
<p class="text-muted small mb-4">URL: <code>/rules</code> &nbsp;|&nbsp; Permission: <code>federation.create</code> (Admin only)</p>

<p>Lists all validation rules in the system with their IDs, names, current severity, and enabled status. <strong>Toggle</strong> enables or disables a rule globally. <strong>Sync</strong> re-registers rules from the PHP codebase (run after a code update that adds new rules).</p>

<h5 class="fw-semibold mt-4 mb-2">Override Levels</h5>
<p>Rule severity can be overridden at three levels — the most specific wins:</p>
<ol>
    <li><strong>Global</strong> — <code>/rules</code> — applies everywhere</li>
    <li><strong>Federation</strong> — <code>/federations/{id}/rules</code> — applies to all entities in this federation</li>
    <li><strong>Entity</strong> — <code>/entities/{id}/rules</code> — applies to this entity only</li>
</ol>

<h5 class="fw-semibold mt-4 mb-2">Rule Groups</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light"><tr><th>Group</th><th>IDs</th><th>What they check</th></tr></thead>
    <tbody>
        <tr><td>Structural</td><td>S01–S10</td><td>entityID URI validity, uniqueness, role descriptors, endpoints, HTTPS, bindings, ACS index, protocol support</td></tr>
        <tr><td>Certificate</td><td>C01–C05</td><td>Certificate present, key size ≥ 2048 bits, not expired, not Debian weak key, SHA-256 or stronger</td></tr>
        <tr><td>REFEDS/eduGAIN</td><td>R01–R15</td><td>DisplayName, Description, Organisation, contacts, SIRTFI, CoCo privacy URL, R&amp;S URI, scope, RegistrationInfo, WantAssertionsSigned</td></tr>
        <tr><td>XSD Schema</td><td>X01</td><td>Validates XML against the official SAML2 metadata XSD schema</td></tr>
        <tr><td>OIDC</td><td>O01–O03</td><td>Redirect URI HTTPS, grant type subset, openid scope present</td></tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Severity Levels</h5>
<ul>
    <li><span class="badge bg-danger">error</span> — hard failure; entity should not be published until resolved</li>
    <li><span class="badge bg-warning text-dark">warning</span> — best-practice issue; entity can be published but should be reviewed</li>
    <li><span class="badge bg-info text-dark">info</span> — informational only; no action required</li>
</ul>
