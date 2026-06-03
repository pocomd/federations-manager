{{-- Layout-free partial — used by buildOpsIndex() for search indexing and included by guide.operations.entities --}}

{{-- 1 --}}
<div x-show="op === 1" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Register a new entity</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.create</span>
    </div>
    <ol class="small">
        <li class="mb-2">Go to <strong>Entities</strong> and click <strong>New Entity</strong>.</li>
        <li class="mb-2">On the <strong>Basic Info</strong> tab:
            <ul class="mt-1">
                <li>Enter the <strong>Entity ID</strong> — a globally unique HTTPS URI (e.g. <code>https://idp.example.org/idp/shibboleth</code>). The Entity ID is permanent and cannot be changed after creation.</li>
                <li>Select the entity <strong>Type</strong>: Service Provider (SP) or Identity Provider (IdP). This is also locked after creation.</li>
                <li>Tick <strong>Export to eduGAIN</strong> if the entity participates in eduGAIN interfederation.</li>
                <li>Enter the <strong>Display Name</strong> and <strong>Description</strong> in English (required).</li>
                <li>Optionally add <strong>Information URL</strong>, <strong>Privacy Statement URL</strong>, and <strong>Logo URL</strong> with declared dimensions.</li>
            </ul>
        </li>
        <li class="mb-2">On the <strong>Endpoints</strong> tab:
            <ul class="mt-1">
                <li><em>IdP</em>: at least one SingleSignOnService URL (HTTP-POST or HTTP-Redirect); optionally SOAP and SLO endpoints; tick supported NameID formats; enter the Shibboleth scope.</li>
                <li><em>SP</em>: HTTP-POST AssertionConsumerService URL (required); optionally HTTP-Redirect and PAOS ACS; SLO endpoints; tick SP security flags.</li>
            </ul>
        </li>
        <li class="mb-2">On the <strong>Certificates</strong> tab, paste the entity's PEM certificate and select its use (<em>signing / encryption / both</em>). Click <strong>Add Certificate</strong> for additional certificates.</li>
        <li class="mb-2">On the <strong>Organisation</strong> tab:
            <ul class="mt-1">
                <li>Fill in the <strong>Organisation Name</strong>, <strong>Display Name</strong>, and <strong>URL</strong>.</li>
                <li>Add at least one <strong>Contact Person</strong> — type, name, email, and optionally phone. Click <strong>Add Contact</strong> for additional contacts.</li>
                <li>Optionally select one or more <strong>Federations</strong> to request membership immediately. The entity joins as <span class="badge bg-warning text-dark">Pending</span> and awaits FM approval.</li>
            </ul>
        </li>
        <li class="mb-2">On the <strong>REFEDS</strong> tab, optionally select entity categories and tick <strong>SIRTFI</strong> if applicable. SIRTFI requires a security contact with an email address.</li>
        <li class="mb-2">On the <strong>Languages</strong> tab, optionally add translated display names, descriptions, and URLs.</li>
        <li>Click <strong>Register Entity</strong>. The entity is created as <span class="badge bg-secondary">Draft</span>. If a federation was selected, an approval request is sent to the federation manager.</li>
    </ol>
</div>

{{-- 2 --}}
<div x-show="op === 2" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Import an entity from XML</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.create</span>
    </div>
    <ol class="small">
        <li class="mb-2">Go to <strong>Entities</strong> and click <strong>Import from XML</strong>.</li>
        <li class="mb-2">Upload an <code>.xml</code> file using the file picker <em>or</em> paste the raw <code>&lt;md:EntityDescriptor&gt;</code> XML into the text area. Selecting a file populates the text area automatically — you can still edit the content before parsing.</li>
        <li class="mb-2">Click <strong>Parse XML</strong>. On success the preview page shows everything extracted: entity ID, type, scope, display names and descriptions in all languages found, organisation details, contacts (type, name, email), endpoints, certificates, and REFEDS entity categories. If the XML is invalid, an error is shown and you can correct and retry.</li>
        <li class="mb-2">Review the preview carefully. Verify that the entity ID, type, and certificate data are correct — these cannot be changed without editing the entity after import.</li>
        <li>Click <strong>Confirm Import</strong>. The entity is created as <span class="badge bg-secondary">Draft</span> and you are taken to its detail page.</li>
    </ol>
    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        To adjust any field before saving, open the entity in <strong>Edit</strong> mode after import.
    </div>
</div>

{{-- 3 --}}
<div x-show="op === 3" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Edit entity details</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.edit</span>
    </div>
    <ol class="small">
        <li class="mb-2">Open the entity's detail page and click <strong>Edit</strong>.</li>
        <li class="mb-2">The form opens pre-filled. The <strong>Entity ID</strong> and <strong>Type</strong> are locked.</li>
        <li class="mb-2">Update any fields across the tabs:
            <ul class="mt-1">
                <li><strong>Basic Info</strong> — display name, description, URLs, logo, eduGAIN flag.</li>
                <li><strong>Endpoints</strong> — SSO / ACS / SLO URLs, bindings, NameID formats, scope, SP security flags.</li>
                <li><strong>Certificates</strong> — add, remove, or change certificate use.</li>
                <li><strong>Organisation</strong> — organisation name, URL, coordinates, contacts (name, email, phone, type).</li>
                <li><strong>REFEDS</strong> — entity categories, SIRTFI, assurance profiles.</li>
                <li><strong>Languages</strong> — multilingual display name, description, URLs.</li>
            </ul>
        </li>
        <li>Click <strong>Save Changes</strong>. Fix any validation errors and acknowledge warnings if acceptable. The metadata XML cache is cleared automatically on save.</li>
    </ol>
    <div class="alert alert-warning py-2 small mt-3">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Federation membership is managed separately — use the federation's <strong>Membership</strong> tab to add or remove this entity from a federation.
    </div>
</div>

{{-- 4 --}}
<div x-show="op === 4" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Suspend an entity</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.edit</span>
        <span class="ms-1 badge bg-success-subtle text-success-emphasis border border-success-subtle">Active entities only</span>
    </div>
    <p class="small">Suspending removes the entity from all active federation metadata feeds immediately.</p>
    <ol class="small">
        <li class="mb-2">Open the entity's detail page and click <strong>Suspend</strong>. A four-step wizard opens.</li>
        <li class="mb-2"><strong>Step 1 — Impact</strong>: review the active federation memberships and certificate warnings that will be affected. Read-only.</li>
        <li class="mb-2"><strong>Step 2 — Memberships</strong>: choose what happens to the entity's active memberships:
            <ul class="mt-1">
                <li><em>Disable memberships</em> — all active memberships are set to Suspended. The entity can be reactivated into the same federations later.</li>
                <li><em>Move to another federation</em> — membership is transferred to a selected federation as Pending, awaiting that FM's approval.</li>
            </ul>
        </li>
        <li class="mb-2"><strong>Step 3 — Notify</strong>: choose whether to send a suspension notification email to entity contacts. Select which contact types (technical, administrative, support) receive the message. Click <strong>Preview</strong> to review the email before sending.</li>
        <li class="mb-2"><strong>Step 4 — Confirm</strong>: review the full suspension summary, then click <strong>Suspend Entity</strong>.</li>
    </ol>
    <p class="small">The entity status changes to <span class="badge bg-danger">Suspended</span>. The federation metadata cache is cleared immediately. If notification was enabled, emails are sent to the selected contacts.</p>
</div>

{{-- 5 --}}
<div x-show="op === 5" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Reactivate a suspended entity</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.edit</span>
        <span class="ms-1 badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Suspended entities only</span>
    </div>
    <ol class="small">
        <li class="mb-2">Open the entity's detail page and click <strong>Reactivate</strong>. A three-step wizard opens.</li>
        <li class="mb-2"><strong>Step 1 — Impact</strong>: review the current suspension state, federation membership status, and certificate health.</li>
        <li class="mb-2"><strong>Step 2 — Federation</strong>: choose how to handle federation membership:
            <ul class="mt-1">
                <li><em>Re-apply to existing federation</em> — the suspended membership is re-submitted as Pending to the same federation.</li>
                <li><em>Apply to a different federation</em> — the existing membership is removed and a new Pending request is submitted to the selected federation.</li>
                <li><em>No change</em> — the entity is reactivated without any federation membership change.</li>
            </ul>
        </li>
        <li class="mb-2"><strong>Step 3 — Confirm</strong>: optionally enable a reactivation notification to selected contact types, review the summary, then click <strong>Reactivate Entity</strong>.</li>
    </ol>
    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        The entity status changes to <span class="badge bg-success">Active</span> immediately. However, if federation membership was re-applied it starts as <span class="badge bg-warning text-dark">Pending</span> — the entity will not appear in federation metadata until the federation manager approves it again.
    </div>
</div>

{{-- 6 --}}
<div x-show="op === 6" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Delete / restore an entity</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.delete</span>
        <span class="ms-1 badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">entity.edit (restore)</span>
    </div>

    <h6 class="fw-semibold mb-2" style="font-size:.875rem;">Soft delete</h6>
    <ol class="small">
        <li class="mb-2">The entity must be in <span class="badge bg-secondary">Draft</span>, <span class="badge bg-warning text-dark">Pending</span>, or <span class="badge bg-danger">Suspended</span> status. <span class="badge bg-success">Active</span> entities cannot be deleted — suspend first.</li>
        <li class="mb-2">Open the entity's detail page, click <strong>Delete</strong>, and confirm.</li>
        <li>The entity moves to the trash. Its data is preserved and can be recovered.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-4" style="font-size:.875rem;">Restore</h6>
    <ol class="small">
        <li class="mb-2">From the <strong>Entities</strong> list, click <strong>Deleted Entities</strong>.</li>
        <li class="mb-2">Find the entity and click <strong>Restore</strong>.</li>
        <li>The entity is recovered. Entities deleted while Active are restored as <span class="badge bg-danger">Suspended</span> — use <em>Reactivate</em> to return them to Active.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-4" style="font-size:.875rem;">Permanent delete</h6>
    <ol class="small">
        <li class="mb-2">From <strong>Deleted Entities</strong>, click <strong>Delete permanently</strong>.</li>
        <li class="mb-2">Permanent deletion is blocked if the entity still has federation memberships — remove all memberships first.</li>
        <li>Confirm the prompt. All entity data is permanently removed and cannot be recovered.</li>
    </ol>
</div>

{{-- 7 --}}
<div x-show="op === 7" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Validate entity compliance</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.view</span>
    </div>
    <p class="small">Validation checks the entity's metadata against structural, certificate, REFEDS, eduGAIN, and XSD rules. It also runs automatically when saving through the registration or edit form.</p>
    <ol class="small">
        <li class="mb-2">Open the entity's detail page and click <strong>Validate</strong>.</li>
        <li class="mb-2">The results page opens with an overall status badge: <em>All checks passed</em>, <em>Passed with warnings</em>, or <em>Validation failed</em>.</li>
        <li class="mb-2">The results table lists each rule by ID with its status (<span class="badge bg-success">Pass</span> / <span class="badge bg-danger">Fail</span> / <span class="badge bg-warning text-dark">Warning</span>) and a plain-language description of the finding.</li>
        <li class="mb-2">Click <strong>Re-validate</strong> to force a fresh check, bypassing the cached result.</li>
    </ol>
    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        Rule severity can be adjusted per entity at <code>/entities/{id}/rules</code>, or globally under <strong>Compliance Rules</strong> in the Admin section.
    </div>
</div>

{{-- 8 --}}
<div x-show="op === 8" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Preview entity XML</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">metadata.view</span>
    </div>
    <p class="small">
        The XML preview shows exactly what the entity's SAML2 <code>&lt;md:EntityDescriptor&gt;</code> looks like at the moment of viewing — the same block that would appear in a published federation aggregate.
        This is the <strong>unsigned</strong> XML; signing happens at federation metadata generation time.
    </p>
    <ol class="small">
        <li class="mb-2">Open the entity's detail page. The <strong>Metadata XML</strong> card near the bottom shows the XML in a scrollable panel. The preview reflects the entity's current data immediately.</li>
        <li class="mb-2">Click <strong>Copy</strong> in the card header to copy the full XML to the clipboard.</li>
        <li class="mb-2">Click <strong>Download</strong> to save the XML as a file (<code>metadata.xml</code>).</li>
    </ol>
    <div class="alert alert-secondary py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        The XML is cached server-side for 1 hour and refreshed automatically whenever the entity or any related record is saved or deleted. The raw URL <code>/entities/{id}/metadata.xml</code> is stable and shareable, but is not an MDQ endpoint.
    </div>
</div>

{{-- 9 --}}
<div x-show="op === 9" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Manage SP requested attributes</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.view</span>
        <span class="ms-1 badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">entity.edit (modify)</span>
        <span class="ms-1 badge bg-info-subtle text-info-emphasis border border-info-subtle">SP entities only</span>
    </div>
    <p class="small">
        The requested attributes list declares which user attributes the SP needs from IdPs. It is published in the generated metadata XML as <code>&lt;md:RequestedAttribute&gt;</code> elements inside <code>&lt;md:AttributeConsumingService&gt;</code>.
    </p>
    <ol class="small">
        <li class="mb-2">Open the SP's detail page and click <strong>Requested Attributes</strong>.</li>
        <li class="mb-2">The page shows two sections:
            <ul class="mt-1">
                <li><strong>Current attributes</strong> — already declared by this SP, showing full name, schema, required/optional flag, and any internal reason note.</li>
                <li><strong>Available attributes</strong> — the global attribute catalogue, grouped by schema (eduPerson, SCHAC, LDAP, voPerson, etc.). Already-added attributes are excluded.</li>
            </ul>
        </li>
        <li class="mb-2">To <strong>add</strong> an attribute: use the schema filter tabs to find it, optionally tick <strong>Required</strong> and enter a <strong>Reason</strong> (an internal note for IdP operators — not published in metadata), then click <strong>Add</strong>.</li>
        <li>To <strong>remove</strong> an attribute: click <strong>Remove</strong> next to it in the current list. This immediately removes it from the SP's metadata.</li>
    </ol>
</div>

{{-- 10 --}}
<div x-show="op === 10" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Configure IdP attribute release policy (ARP)</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">arp.view</span>
        <span class="ms-1 badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">arp.edit (modify)</span>
        <span class="ms-1 badge bg-info-subtle text-info-emphasis border border-info-subtle">IdP entities only</span>
    </div>
    <p class="small">
        The ARP records which attributes the IdP intends to release to each SP that shares a federation with it. This is a <strong>documentation tool</strong> — the registry stores the policy for reference and transparency but does not enforce it at runtime. The IdP software (Shibboleth, SimpleSAMLphp, etc.) governs what is actually released.
    </p>
    <ol class="small">
        <li class="mb-2">Open the IdP's detail page and click <strong>Attribute Release Policy</strong>.</li>
        <li class="mb-2">The page lists all SPs that share at least one federation with this IdP, with their declared requested attributes.</li>
        <li class="mb-2">Use the <strong>schema filter</strong> at the top to narrow the attribute list (All / eduPerson / LDAP / SCHAC / voPerson).</li>
        <li class="mb-2">For each SP–attribute row: toggle the <strong>Permitted</strong> switch, optionally add a <strong>Note</strong> to record the reasoning, then click <strong>Save</strong>.</li>
        <li>If a <strong>Stale rules</strong> banner appears, it lists rules for SPs that no longer share a federation with this IdP. Click the delete button next to each stale rule to clean them up.</li>
    </ol>
    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        REFEDS and eduGAIN best practice is <strong>default deny</strong>: document only what is explicitly permitted, following the principle of data minimisation.
    </div>
</div>

{{-- 11 --}}
<div x-show="op === 11" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Invite a contact as co-manager</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.requestContactInvitation</span>
    </div>
    <p class="small">An entity manager can request that a contact listed on the entity is invited to become a co-manager. The request goes to the federation manager for approval. Once approved, the co-manager has the same access rights as the requesting entity manager.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Submitting a request <small class="fw-normal text-muted">(Entity Manager)</small></h6>
    <ol class="small">
        <li class="mb-2">Open the entity's detail page. The <strong>Request Co-Manager</strong> section lists the entity's registered contacts. If there are no contacts, add one in the entity edit form first.</li>
        <li class="mb-2">Click <strong>Request Invitation</strong> next to the contact's email address.</li>
        <li class="mb-2">In the modal, select the <strong>federation</strong> the contact will manage the entity within. If the entity belongs to only one federation it is pre-selected. Click <strong>Send Request</strong>.</li>
        <li>The request is submitted and the federation manager is notified immediately.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Tracking request status <small class="fw-normal text-muted">(Entity Manager)</small></h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>My Requests</strong> in the sidebar.</li>
        <li class="mb-2">Each submitted request shows its current status:
            <ul class="mt-1">
                <li><span class="badge bg-warning text-dark">Pending</span> — not yet reviewed by the FM.</li>
                <li><span class="badge bg-success">Approved</span> — accepted. If the contact had an existing account they were added immediately; otherwise an invitation email was sent.</li>
                <li><span class="badge bg-danger">Rejected</span> — declined. The <em>Note</em> column shows the FM's reason.</li>
            </ul>
        </li>
        <li>To withdraw a <span class="badge bg-warning text-dark">Pending</span> request before it is reviewed, click <strong>Cancel</strong> on the request row and confirm. The request is permanently removed; you can submit a new one at any time.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Reviewing requests <small class="fw-normal text-muted">(Federation Manager)</small></h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>Invitation Requests</strong> in the sidebar.</li>
        <li class="mb-2">Click <strong>Approve</strong> — the contact is added as co-manager if they already have an account, or an invitation email is sent. If a pending invitation to the same address already exists it is not duplicated.</li>
        <li>Click <strong>Reject</strong>, enter a reason, and click <strong>Confirm Reject</strong>. The requester sees the reason in their My Requests view.</li>
    </ol>
</div>

{{-- 12 --}}
<div x-show="op === 12" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Send a direct invitation</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">invitation.view</span>
    </div>
    <p class="small">Entity managers can send an invitation directly from the <strong>My Invitations</strong> page without going through the co-manager request workflow. The invitee must be listed as a contact on the entity. Once they accept, they are registered as a user and added as co-manager for that entity.</p>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Sending the invitation</h6>
    <ol class="small">
        <li class="mb-2">Go to <strong>My Invitations</strong> in the sidebar.</li>
        <li class="mb-2">Click <strong>New Invitation</strong>.</li>
        <li class="mb-2">In the modal, select the <strong>Entity</strong> — the dropdown shows only entities you manage.</li>
        <li class="mb-2">Select the <strong>Contact to invite</strong> — only contacts already registered on the entity are available. If the contact you need is not listed, add them on the entity's edit page first.</li>
        <li>Click <strong>Send Invitation</strong>. An email is sent with a one-time registration link.</li>
    </ol>

    <h6 class="fw-semibold mb-2 mt-3" style="font-size:.875rem;">Managing sent invitations</h6>
    <p class="small">The pending tab lists all active invitations you have sent. For each invitation you can:</p>
    <ul class="small">
        <li class="mb-1"><strong>Copy URL</strong> — copy the registration link to share it via another channel if the email was not received.</li>
        <li class="mb-1"><strong>Resend</strong> — send the invitation email again. Use this if the original email expired or was lost.</li>
        <li><strong>Revoke</strong> — cancel the invitation immediately. The link becomes invalid.</li>
    </ul>
    <p class="small mt-2">Accepted, expired, and revoked invitations appear in their respective tabs for your records.</p>

    <div class="alert alert-info py-2 small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        This flow does not require FM approval — the invitation is sent immediately. Use the co-manager request flow (op 11) if your federation policy requires FM sign-off before new managers are added.
    </div>
</div>
