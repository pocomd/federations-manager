<h2 class="h4 fw-bold mb-1">Federations</h2>
<p class="text-muted small mb-4">URL: <code>/federations</code></p>

<p>
    A <strong>federation</strong> is a named group of SAML entities (IdPs and SPs) that share a trust framework.
    The application generates a signed XML metadata aggregate for each federation, which relying parties consume.
</p>

<h5 class="fw-semibold mt-4 mb-2">Roles and Visibility</h5>
<table class="table table-sm table-bordered small">
    <thead class="table-light"><tr><th>Role</th><th>What they can see and do</th></tr></thead>
    <tbody>
        <tr>
            <td><strong>Admin</strong></td>
            <td>Full access to all federations — create, edit, delete, restore, manage managers, upload signing keys</td>
        </tr>
        <tr>
            <td><strong>Federation Manager</strong></td>
            <td>
                Sees only federations they are assigned to. Can edit federation details, manage membership,
                approve/reject entities, and manage signing keys for their federation.
                Cannot create federations or manage other federations.
            </td>
        </tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Create a Federation</h5>
<p>Permission required: <code>federation.create</code> (Admin only).</p>
<table class="table table-sm table-bordered small">
    <thead class="table-light"><tr><th>Field</th><th>Notes</th></tr></thead>
    <tbody>
        <tr><td><strong>Name</strong></td><td>Human-readable label; generates a URL slug automatically</td></tr>
        <tr><td><strong>URI</strong></td><td>Unique registration authority URI — becomes the <code>registrationAuthority</code> in entity metadata. Must be globally unique.</td></tr>
        <tr><td><strong>Description</strong></td><td>Optional free text</td></tr>
        <tr><td><strong>Status</strong></td><td><code>active</code> or <code>inactive</code>. Only active federations serve a public metadata feed.</td></tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Federation Show Page</h5>
<p>URL: <code>/federations/{slug}</code> — contains all federation management in a tabbed interface.</p>

<table class="table table-sm table-bordered small">
    <thead class="table-light"><tr><th style="width:15%">Tab</th><th>Who can see it</th><th>Contents</th></tr></thead>
    <tbody>
        <tr>
            <td><strong>General</strong></td>
            <td>All with access</td>
            <td>
                Name, URI, description, status toggle, registration policies, IdP/SP/pending pie chart,
                contact export links (all / IdP / SP contacts as CSV).
                Edit button visible to Admin and assigned Federation Managers only.
            </td>
        </tr>
        <tr>
            <td><strong>Membership</strong></td>
            <td>All with access</td>
            <td>
                Pending approval table, active member list, add-entity form.
                Approve/Reject/Remove actions visible to users with <code>federation.approveRequest</code> /
                <code>federation.rejectRequest</code> permissions.
            </td>
        </tr>
        <tr>
            <td><strong>Metadata</strong></td>
            <td>All with access</td>
            <td>
                Public feed URLs (full and eduGAIN), signed metadata download link,
                last-signed timestamp, generate/sign button (<code>metadata.generate</code> permission required),
                optional external metadata URL field.
            </td>
        </tr>
        <tr>
            <td><strong>Attributes</strong></td>
            <td>All with access</td>
            <td>
                Required attribute list for this federation. Attributes added here are injected into
                <code>&lt;md:RequestedAttributes&gt;</code> in entity metadata for all member SPs.
            </td>
        </tr>
        <tr>
            <td><strong>Validators</strong></td>
            <td>All with access</td>
            <td>
                External compliance services that validate entity metadata via HTTP (e.g. REFEDS).
                Each validator shows a <strong>Test</strong> button — runs the validator against any entity ID on demand and displays the result inline.
            </td>
        </tr>
        <tr>
            <td><strong>Rules</strong></td>
            <td>All with access</td>
            <td>
                Per-federation overrides for compliance rule severity. Rules not overridden here fall back to
                the global default. Overrides apply only to entities within this federation.
                The <strong>Re-validate entities</strong> button (visible to users with <code>federation.edit</code>
                permission) runs the compliance rule engine against all active member entities immediately.
                If all pass, a success notification is shown. If any fail, the results page is displayed — see
                <em>Compliance Re-check Results</em> below.
            </td>
        </tr>
        <tr>
            <td><strong>Contacts</strong></td>
            <td>All with access</td>
            <td>
                Federation-level contacts (technical, administrative, security, support).
                These are separate from entity-level contacts — they represent the federation operator's contacts.
                Add/remove contacts requires <code>federation.edit</code> permission.
            </td>
        </tr>
        <tr>
            <td><strong>Managers</strong></td>
            <td>All with access (edit: Admin only)</td>
            <td>
                Lists all Federation Managers assigned to this federation with name, email, assigned date,
                and who assigned them. Add/remove requires <code>federation.create</code> (Admin only).
                A Federation Manager can only edit their own assigned federations.
            </td>
        </tr>
        <tr>
            <td><strong>Signing Keys</strong></td>
            <td>Admin and assigned Managers</td>
            <td>
                Upload and manage the per-federation private key and certificate used for metadata signing.
                See <em>Signing Keys</em> section below.
            </td>
        </tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Membership Management</h5>
<ul class="small">
    <li>
        <strong>Add</strong> — select an entity from the dropdown. Added with status
        <span class="badge bg-warning text-dark">pending</span>.
        The entity owner is not automatically notified at this point.
    </li>
    <li>
        <strong>Approve</strong> — pivot status → <span class="badge bg-success">active</span>.
        If the entity was in <code>draft</code> status it is automatically promoted to <code>active</code>.
        The entity owner receives an approval notification.
    </li>
    <li>
        <strong>Reject</strong> — enter a rejection reason. Pivot row is removed; entity owner is notified with the reason.
    </li>
    <li>
        <strong>Remove</strong> — removes the entity from the federation. The entity itself is not deleted or deactivated.
    </li>
</ul>

<h5 class="fw-semibold mt-4 mb-2">Registration Policies</h5>
<p>
    URL: <code>/federations/{id}/policies</code> — adds <code>&lt;mdrpi:RegistrationPolicy&gt;</code> elements to the
    federation metadata. Each policy is a language code + URL pair.
    Toggle <strong>Enabled</strong> to include or exclude a policy from the output without deleting it.
</p>

<h5 class="fw-semibold mt-4 mb-2">External Validators</h5>
<p>
    URL: <code>/federations/{id}/validators</code> — configure external HTTP-based compliance services
    (e.g. REFEDS metadata validators). Each validator has a base URL; the <strong>Test</strong> button
    appends an entity ID and displays the validator's response inline. Useful for checking
    whether an entity's metadata passes federation admission criteria before approving membership.
</p>

<h5 class="fw-semibold mt-4 mb-2">Send Email to Members</h5>
<p>
    URL: <code>/federations/{id}/mail</code> — compose and send a templated email to all contacts of member entities.
    Filter recipients by entity type (IdP / SP) and contact type (technical / administrative / support / security).
    The <strong>Mail Log</strong> tab shows all messages sent for this federation with status, recipients, and timestamp.
</p>

<h5 class="fw-semibold mt-4 mb-2">Contact Export</h5>
<p>
    From the <strong>General</strong> tab, contacts for all member entities can be downloaded as CSV:
    <em>All contacts</em>, <em>IdP contacts only</em>, or <em>SP contacts only</em>.
    Useful for bulk communications or import into external systems.
</p>

<h5 class="fw-semibold mt-4 mb-2">Signing Keys</h5>
<p>
    The <strong>Signing Keys</strong> tab lets Admins and assigned Federation Managers upload the private key
    and certificate used to sign this federation's metadata. No server filesystem access is required.
</p>
<ul class="small">
    <li>Upload a <strong>single file</strong> — the app detects the content automatically.</li>
    <li>If the file contains <strong>both</strong> the key and certificate (combined PEM or PKCS#12 bundle), both are stored in one step.</li>
    <li>If the file contains only one credential, it is saved and you are prompted for the other in step 2.</li>
    <li>The app validates that the private key and certificate form a <strong>matching pair</strong> before saving.</li>
    <li>Keys are stored at <code>0600</code> permissions. Encrypted keys are decrypted on upload using the supplied password — they are always stored unencrypted at rest.</li>
    <li>The <strong>ⓘ info button</strong> on each credential card shows key type, size, and a PEM preview (key), or subject, issuer, serial, and validity dates with a colour-coded expiry badge (certificate).</li>
    <li>To replace an uploaded pair, use <strong>Remove Key Pair</strong> first, then upload the new files.</li>
    <li>Each upload and deletion is recorded in the <strong>Audit Log</strong>.</li>
</ul>
<p class="small text-muted">
    Supported formats: <code>.pem</code> / <code>.key</code> / <code>.crt</code> / <code>.cer</code> (PEM) and
    <code>.p12</code> / <code>.pfx</code> (PKCS#12). If no per-federation key is uploaded, signing falls back to
    the global key pair configured in <code>.env</code> (see <em>Metadata Signing</em> in this guide).
</p>

<h5 class="fw-semibold mt-4 mb-2">Compliance Re-check Results</h5>
<p>
    URL: <code>/federations/{slug}/revalidate</code> (POST, rendered inline) — shown when one or more
    active member entities fail compliance checks after pressing <strong>Re-validate entities</strong>
    on the Rules tab.
</p>
<ul class="small">
    <li><strong>Summary cards</strong> — total entities checked, passed, and failed counts at a glance.</li>
    <li>
        <strong>Advisory note</strong> — failing entities remain in the signed metadata feed.
        Compliance checks are advisory only; to exclude an entity from the feed you must suspend it
        or remove it from the federation.
    </li>
    <li>
        <strong>Per-entity cards</strong> — each failing entity shows its errors (red) and warnings (yellow),
        plus its technical and support contacts with pre-composed <code>mailto:</code> links for quick
        manual outreach.
    </li>
</ul>
<h6 class="fw-semibold mt-3 mb-1">Notifying entity operators</h6>
<p class="small">
    The results page includes a built-in notification workflow for users with <code>federation.edit</code> permission:
</p>
<ol class="small">
    <li>Click <strong>Select entities to notify</strong> — a checkbox appears on each entity card.</li>
    <li>Tick the entities whose operators should be emailed.</li>
    <li>
        Click <strong>Preview template</strong> to open a modal showing exactly how the email will look
        for the first selected entity (subject and body fully rendered with the entity's actual errors and
        contact name).
    </li>
    <li>
        Click <strong>Send notifications</strong> (enabled once at least one entity is selected) — sends the
        <em>Compliance check failure</em> mail template to each selected entity's technical contacts.
        Each email is personalised per entity with that entity's own list of errors and warnings.
    </li>
</ol>
<p class="small text-muted">
    All sent messages are recorded in the Mail Log. The template used is the
    <em>Compliance check failure</em> template (group <code>compliance_failure</code>), which can be
    customised per federation under <strong>Mail Templates</strong>.
</p>

<h5 class="fw-semibold mt-4 mb-2">Delete and Restore</h5>
<ul class="small">
    <li>Federations with <code>status = active</code> cannot be deleted — set to <strong>inactive</strong> first.</li>
    <li>Deleting soft-deletes the record; it remains visible on the trash page at <code>/federations/trashed</code>.</li>
    <li>Permanent delete is blocked if the federation still has entity members — remove all members first.</li>
    <li>Restore and permanent delete require <code>federation.create</code> permission (Admin only).</li>
</ul>
