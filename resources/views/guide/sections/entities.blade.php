<h2 class="h4 fw-bold mb-1">Entities</h2>
<p class="text-muted small mb-4">URL: <code>/entities</code></p>

<p>
    An <strong>entity</strong> is a SAML service provider (SP) or identity provider (IdP) registered in the system.
    Entities are the building blocks of federation membership — each federation's signed metadata aggregate
    is composed of the XML descriptors of its active member entities.
</p>

<h5 class="fw-semibold mt-4 mb-2">Entity Statuses</h5>
<table class="table table-sm table-bordered small">
    <thead class="table-light"><tr><th style="width:15%">Status</th><th>Meaning</th></tr></thead>
    <tbody>
        <tr>
            <td><span class="badge bg-secondary">Draft</span></td>
            <td>Newly created, not yet submitted. Not included in any federation metadata. Owner can edit freely. Can be deleted.</td>
        </tr>
        <tr>
            <td><span class="badge bg-warning text-dark">Pending</span></td>
            <td>Submitted for federation membership and awaiting approval. Not yet in metadata. Cannot be suspended or deleted while pending.</td>
        </tr>
        <tr>
            <td><span class="badge bg-success">Active</span></td>
            <td>Approved and included in federation metadata. Can be suspended but not directly deleted.</td>
        </tr>
        <tr>
            <td><span class="badge bg-danger">Suspended</span></td>
            <td>Temporarily excluded from federation metadata. Can be reactivated or deleted.</td>
        </tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Browse &amp; Filter</h5>
<p>The entity list supports live search (entity ID, display name, description) and the following filters:</p>
<ul class="small">
    <li><strong>Type</strong> — All / IdP / SP</li>
    <li><strong>Status</strong> — All / Active / Draft / Pending / Suspended</li>
    <li><strong>Federation</strong> — members of a selected federation</li>
    <li><strong>Sort</strong> — by name, type, status, or created date</li>
</ul>
<p class="small">Clicking a row expands an inline preview showing core details, certificates, and federation memberships. Click the entity name or ID to open the full show page.</p>

<h5 class="fw-semibold mt-4 mb-2">Create / Import an Entity</h5>
<p>Use the <strong>Register Entity</strong> button on the entity list (requires <code>entity.create</code> permission):</p>
<table class="table table-sm table-bordered small">
    <thead class="table-light"><tr><th>Method</th><th>How it works</th></tr></thead>
    <tbody>
        <tr><td><strong>Fill form manually</strong></td><td>Opens the standard multi-tab entity form</td></tr>
        <tr><td><strong>Import from XML</strong></td><td>Paste or upload a SAML 2.0 <code>&lt;EntityDescriptor&gt;</code> — all fields parsed automatically</td></tr>
        <tr><td><strong>Import from JSON</strong></td><td>Paste a JSON object with entity fields; useful for scripted bulk import</td></tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Entity Form Tabs</h5>
<table class="table table-sm table-bordered small">
    <thead class="table-light"><tr><th style="width:18%">Tab</th><th>Fields</th></tr></thead>
    <tbody>
        <tr>
            <td><strong>Basic Info</strong></td>
            <td>
                Entity ID (URI, live-validated for uniqueness), type (SP / IdP — locked after save),
                display name &amp; description (English), logo URL with dimensions, information URL,
                privacy URL, eduGAIN flag, NameID formats, Shibboleth scope (IdP only),
                SP signature options (<em>want authn requests signed</em>, <em>want assertions signed</em>),
                registration authority (<code>mdrpi:RegistrationInfo</code>),
                registration policies (<code>mdrpi:RegistrationPolicy</code> — per-language policy URLs, add/remove rows)
            </td>
        </tr>
        <tr>
            <td><strong>Endpoints</strong></td>
            <td>
                SSO endpoints (IdP), ACS endpoints (SP), SLO endpoints — each with binding (POST / Redirect / Artifact) and location URL.
                Multiple endpoints per type supported with index ordering.
            </td>
        </tr>
        <tr>
            <td><strong>Certificates</strong></td>
            <td>
                X.509 certificates in PEM format. Each certificate has a <em>use</em> field:
                <code>signing</code>, <code>encryption</code>, or <code>both</code>.
                Multiple certificates supported. Certificates are parsed on save — expiry, key size, algorithm, and fingerprint are extracted automatically.
            </td>
        </tr>
        <tr>
            <td><strong>Organisation</strong></td>
            <td>
                Organisation name (English and native language), display name, website URL,
                geographic coordinates. Also manages entity contacts: type (technical / support /
                security / administrative), name, email, phone.
            </td>
        </tr>
        <tr>
            <td><strong>REFEDS</strong></td>
            <td>
                REFEDS entity categories (Research &amp; Scholarship, Code of Conduct, etc.),
                assurance profiles, SIRTFI compliance flag (adds the SIRTFI URI to assurance attributes;
                shows a <span class="badge bg-success">green badge</span> when a security contact is already registered),
                MFA profile, NameID formats, requested attributes for SPs.
            </td>
        </tr>
        <tr>
            <td><strong>OIDC</strong></td>
            <td>
                Only shown for OIDC entities. Redirect URIs, grant types, scopes, application type,
                token endpoint authentication method.
            </td>
        </tr>
        <tr>
            <td><strong>Languages</strong></td>
            <td>
                Additional language variants for display name, description, information URL, and privacy URL.
                English values are set on the Basic Info tab; other languages are added here.
            </td>
        </tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2" id="entity-categories">REFEDS Entity Categories</h5>
<p>Entity categories are URI-tagged policy labels placed in SAML metadata. They signal to IdPs and discovery
services how an entity should be treated — in particular, which attributes an IdP may release automatically.
All categories below are set on the <strong>REFEDS</strong> tab of the entity form.</p>
<table class="table table-sm table-bordered small">
    <thead class="table-light">
        <tr><th style="width:22%">Category</th><th>Purpose &amp; behaviour</th><th style="width:28%">Typical example</th></tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <strong>Research &amp; Scholarship (R&amp;S)</strong><br>
                <code class="small">…/research-and-scholarship</code>
            </td>
            <td>
                For SPs that primarily serve the research and scholarship community.
                IdPs asserting R&amp;S agree to release a standard attribute bundle
                (eduPersonPrincipalName, mail, displayName, eduPersonScopedAffiliation)
                to all R&amp;S SPs <em>without</em> requiring per-SP bilateral agreements.
            </td>
            <td>A journal-submission platform (e.g. Open Journal Systems) that needs user identity to manage manuscript submissions.</td>
        </tr>
        <tr>
            <td>
                <strong>Code of Conduct v2 (CoCo)</strong><br>
                <code class="small">…/code-of-conduct/v2</code>
            </td>
            <td>
                For SPs that have accepted the GÉANT Data Protection Code of Conduct.
                IdPs can release personal data to CoCo SPs on the basis of this policy
                commitment rather than requiring bilateral data-processing agreements.
            </td>
            <td>A cloud collaboration tool (e.g. Microsoft 365, Zoom) that has signed the CoCo data-protection terms.</td>
        </tr>
        <tr>
            <td>
                <strong>Hide from Discovery</strong><br>
                <code class="small">…/hide-from-discovery</code>
            </td>
            <td>
                Instructs discovery services (WAYF, DS) to omit this <strong>IdP</strong>
                from their default list. The IdP is still reachable via direct link or
                SP-initiated login; it simply does not appear in end-user drop-down selectors.
            </td>
            <td>A development or staff-only IdP that should not be visible to students in the login selector.</td>
        </tr>
        <tr>
            <td>
                <strong>Anonymous</strong><br>
                <code class="small">…/anonymous</code>
            </td>
            <td>
                For SPs that need only proof of authentication — no personal data is
                released. The IdP confirms the user is valid but releases no identifying
                attributes whatsoever.
            </td>
            <td>An open-access digital library where login gates paywalled content but no personalisation is required.</td>
        </tr>
        <tr>
            <td>
                <strong>Pseudonymous</strong><br>
                <code class="small">…/pseudonymous</code>
            </td>
            <td>
                For SPs that need a stable but non-identifying user handle to recognise
                returning users across sessions. A pairwise pseudonym is released instead
                of real identity — the SP can track "the same user" without knowing who
                they are.
            </td>
            <td>An e-learning platform that tracks course progress per user but does not need names or email addresses.</td>
        </tr>
        <tr>
            <td>
                <strong>Personalized</strong><br>
                <code class="small">…/personalized</code>
            </td>
            <td>
                For SPs that require real personal data (name, email, affiliation) to
                provide a fully personalised experience. This is the highest tier of the
                REFEDS Personalization category spectrum.
            </td>
            <td>A collaborative research platform (e.g. GitHub Enterprise) where user profiles and email notifications require real identity.</td>
        </tr>
    </tbody>
</table>
<h6 class="fw-semibold mt-3 mb-2">SP form vs IdP form</h6>
<p class="small">
    SAML uses two different attribute names depending on the role, and the entity form enforces this split:
</p>
<table class="table table-sm table-bordered small">
    <thead class="table-light"><tr><th>Form section</th><th>Applies to</th><th>SAML attribute</th><th>Meaning</th></tr></thead>
    <tbody>
        <tr>
            <td><strong>Entity Categories</strong></td>
            <td>SP</td>
            <td><code>entity-category</code></td>
            <td>"I belong to this category" — R&amp;S, CoCo, Anonymous, Pseudonymous, Personalized</td>
        </tr>
        <tr>
            <td><strong>Entity Categories</strong></td>
            <td>IdP</td>
            <td><code>entity-category</code></td>
            <td>Hide from Discovery only</td>
        </tr>
        <tr>
            <td><strong>Entity Category Support</strong></td>
            <td>IdP</td>
            <td><code>entity-category-support</code></td>
            <td>"I will automatically release the defined attribute bundle to SPs in this category"</td>
        </tr>
    </tbody>
</table>
<p class="small text-muted">Categories are not mutually exclusive — an SP may assert both R&amp;S and CoCo simultaneously, for example.</p>

<h5 class="fw-semibold mt-4 mb-2">Entity Show Page</h5>
<p>URL: <code>/entities/{slug}</code> — read-only summary of the entity's current state. Key panels:</p>
<ul class="small">
    <li><strong>Core Details</strong> — entity ID, type, status, registration authority, scope, eduGAIN flag, source (<code>manual</code> / <code>edugain</code> / <code>imported</code>)</li>
    <li><strong>Certificates</strong> — list of registered X.509 certificates with expiry badge (colour-coded: expired / ≤14d / ≤30d / healthy), subject, not-after date, key algorithm and size</li>
    <li><strong>Federation Memberships</strong> — list of federations this entity belongs to with pivot status (pending / active / rejected)</li>
    <li><strong>Endpoints</strong> — SSO, ACS, SLO endpoints with binding and location</li>
    <li><strong>Contacts</strong> — entity contacts with optional co-manager invitation (see below)</li>
    <li><strong>REFEDS attributes</strong> — entity categories and assurance profiles if set</li>
    <li><strong>eduGAIN panel</strong> — links to external eduGAIN check tools (technical.edugain.org, ECCS, access-check, release-check); shown only when eduGAIN checks are enabled in system preferences</li>
    <li><strong>Raw XML</strong> — inline preview of the entity's generated <code>&lt;EntityDescriptor&gt;</code> XML</li>
</ul>

<h5 class="fw-semibold mt-4 mb-2">Entity Lifecycle Actions</h5>
<table class="table table-sm table-bordered small">
    <thead class="table-light"><tr><th>Action</th><th>Available when</th><th>Notes</th></tr></thead>
    <tbody>
        <tr>
            <td><strong>Edit</strong></td>
            <td>Any status</td>
            <td>Requires <code>entity.edit</code>. Entity type cannot be changed after creation.</td>
        </tr>
        <tr>
            <td><strong>Suspend</strong></td>
            <td>Active</td>
            <td>Removes entity from federation metadata immediately. Status → <code>suspended</code>.</td>
        </tr>
        <tr>
            <td><strong>Reactivate</strong></td>
            <td>Suspended</td>
            <td>Multi-step wizard: choose target federation, optionally notify contacts. Status → <code>active</code>.</td>
        </tr>
        <tr>
            <td><strong>Delete</strong></td>
            <td>Draft, Pending, or Suspended</td>
            <td>Soft-deletes the entity. Active entities cannot be deleted — suspend first.</td>
        </tr>
        <tr>
            <td><strong>Requested Attributes</strong></td>
            <td>SP entities only</td>
            <td>Manage <code>&lt;md:RequestedAttributes&gt;</code> for this SP. Accessible via button on the show page.</td>
        </tr>
        <tr>
            <td><strong>Attribute Release Policy</strong></td>
            <td>IdP entities only</td>
            <td>Configure which attributes this IdP releases to which SPs. Accessible via button on the show page.</td>
        </tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Federation Invitations</h5>
<p>
    When a federation operator has sent a pending invitation for this entity to join their federation,
    a blue <strong>Federation Invitations</strong> panel appears at the top of the entity edit page.
    Each invitation shows the federation name, the inviting manager, and when it was sent.
    Click <strong>Accept</strong> to approve the membership or <strong>Decline</strong> to reject it — no page navigation required.
</p>

<h5 class="fw-semibold mt-4 mb-2">Reload from XML / JSON</h5>
<p>
    A collapsible <strong>Reload from XML / JSON</strong> panel appears at the bottom of the entity edit page.
    Paste a SAML2 <code>&lt;EntityDescriptor&gt;</code> or a JSON export (or upload a file) and click <strong>Apply</strong>.
    The system parses the content and <strong>pre-fills all form fields</strong> for review — no data is saved
    until you click Save on each tab.
    Federation memberships, entity status, and access settings are not affected.
</p>

<h5 class="fw-semibold mt-4 mb-2">eduGAIN Certificate Requirement</h5>
<p>
    Entities with the <strong>eduGAIN</strong> flag enabled but without at least one registered X.509 certificate
    are automatically excluded from the <code>/metadata/{federation}/edugain</code> feed.
    A red <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill"></i> No certificates — excluded from feed</span>
    badge appears on the entity show page, and an inline warning is shown in the entity form.
    Add a certificate to restore inclusion.
</p>

<h5 class="fw-semibold mt-4 mb-2">Validation</h5>
<p>
    URL: <code>/entities/{id}/validate</code> — runs the full compliance rule engine against the entity's current data.
    Results are grouped into <span class="badge bg-danger">Errors</span>,
    <span class="badge bg-warning text-dark">Warnings</span>, and
    <span class="badge bg-success">Passed</span> checks.
    Use <strong>Re-validate</strong> to force a fresh run. Individual rule severity can be overridden
    per entity at <code>/entities/{id}/rules</code>.
</p>
<p>
    When the entity has errors or warnings <em>and</em> at least one technical contact on record,
    two additional buttons appear (visible to users with <code>entity.edit</code> permission):
</p>
<ul class="small">
    <li>
        <strong>Preview notification</strong> — opens a modal showing the rendered
        <em>Compliance check failure</em> email as it would be sent: subject and body with the
        entity's actual errors, warnings, contact name, and federation resolved.
    </li>
    <li>
        <strong>Notify contacts</strong> — sends the compliance notification immediately to all
        technical contacts of this entity. Hover the button to see a popover listing the exact
        contacts that will receive the email. All sent messages are recorded in the Mail Log.
    </li>
</ul>

<h5 class="fw-semibold mt-4 mb-2">Co-manager Contact Invitation</h5>
<p>
    From the entity show page (requires <code>entity.requestContactInvitation</code> permission),
    an invitation can be sent to one of the entity's contacts to become a co-manager.
    The recipient receives an email with a link to claim the entity.
    Select the contact and the target federation when submitting the request.
</p>

<h5 class="fw-semibold mt-4 mb-2">Trash</h5>
<p>
    URL: <code>/entities/trashed</code> — lists soft-deleted entities.
    Actions: <strong>Restore</strong> (returns to previous status) or
    <strong>Permanently delete</strong> (blocked if the entity still has active federation memberships — remove from all federations first).
</p>
