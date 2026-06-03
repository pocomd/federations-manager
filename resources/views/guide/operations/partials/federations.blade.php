{{-- Layout-free partial — used by buildOpsIndex() for search indexing and included by guide.operations.federations --}}

{{-- 1 --}}
<div x-show="op === 1" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Create a federation</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.create</span>
    </div>
    <ol class="small">
        <li class="mb-2">Go to <strong>Federations</strong> and click <strong>New Federation</strong>.</li>
        <li class="mb-2">Enter the <strong>Name</strong> — the human-readable display name used in the UI and in published metadata.</li>
        <li class="mb-2">Enter the <strong>Registration Authority URI</strong> — a globally unique HTTPS URI that identifies this federation as the <code>registrationAuthority</code> in the <code>mdrpi:RegistrationInfo</code> element published in each member entity's metadata. This value should not be changed after entities have been published.</li>
        <li class="mb-2">Optionally enter a <strong>Description</strong> and the <strong>Metadata URL</strong> (the public URL where this federation publishes its aggregate XML — informational only).</li>
        <li class="mb-2">Click <strong>Create Federation</strong>. The federation is created with status <span class="badge bg-success">Active</span> and you are taken to its detail page.</li>
    </ol>
    <p class="small text-muted mb-0">After creation, configure the federation further: assign managers (op 6), upload signing keys (op 9), add a registration policy (op 10), and configure external validators (op 11). If more than one signing driver is active on this instance a <strong>Signing Driver</strong> selector appears on the create form — choose the driver that matches your infrastructure.</p>
</div>

{{-- 2 --}}
<div x-show="op === 2" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Add an entity to a federation</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.addToFederation</span>
    </div>
    <p class="small">An entity can be added to a federation in two ways.</p>
    <p class="small fw-semibold mb-1">FM-initiated (from the federation Members tab):</p>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page and go to the <strong>Members</strong> tab.</li>
        <li class="mb-2">Click <strong>Add IdP directly</strong> or <strong>Add SP directly</strong> to open the entity registration form pre-filled for this federation. The entity is created with a <span class="badge bg-warning text-dark">Pending</span> membership immediately.</li>
        <li class="mb-2">Alternatively click <strong>Invite IdP</strong> or <strong>Invite SP</strong> to send an invitation to an existing entity manager — they accept or decline from their own interface.</li>
    </ol>
    <p class="small fw-semibold mb-1">EM-initiated (from entity registration or reactivation):</p>
    <ol class="small">
        <li class="mb-2">On the <strong>Organisation</strong> tab of the entity registration or edit form, tick one or more federations. The entity is submitted as <span class="badge bg-warning text-dark">Pending</span> for each selected federation and an approval request is sent to the federation manager.</li>
    </ol>
    <p class="small text-muted mb-0">A pending membership expires automatically after the number of days configured in <strong>System Preferences → Federation → Pending membership expiry</strong>. If the deadline passes without action the membership is auto-rejected and the entity manager is notified.</p>
</div>

{{-- 3 --}}
<div x-show="op === 3" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Approve entity membership</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.approveRequest</span>
    </div>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page and go to the <strong>Members</strong> tab. Pending requests are listed in the <strong>Pending Approval</strong> section at the top, with a count badge. Each row shows the entity's display name, type, entity ID, and the deadline by which the request will auto-expire.</li>
        <li class="mb-2">Click the eye icon to open the entity's detail page and review its metadata before deciding.</li>
        <li class="mb-2">Click <strong>Approve</strong> — a confirmation prompt appears. Confirm to proceed.</li>
        <li class="mb-2">The membership status changes to <span class="badge bg-success">Active</span> immediately. If the entity was previously in Draft or Pending status it is promoted to Active at the same time.</li>
    </ol>
    <p class="small text-muted mb-0">The entity manager and entity technical contacts receive an approval notification. The entity will be included in the next metadata generation cycle (op 8).</p>
</div>

{{-- 4 --}}
<div x-show="op === 4" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Reject entity membership</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.rejectRequest</span>
    </div>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page and go to the <strong>Members</strong> tab. Pending requests appear in the <strong>Pending Approval</strong> section.</li>
        <li class="mb-2">Click <strong>Reject</strong> on the relevant row. A prompt opens asking for a <strong>rejection reason</strong> — this field is mandatory.</li>
        <li class="mb-2">Enter the reason and confirm. The membership status changes to <span class="badge bg-danger">Rejected</span>. The reason is stored on the membership record and included in the rejection notification sent to the entity manager and entity technical contacts.</li>
    </ol>
    <p class="small text-muted mb-0">The entity remains in the registry and can re-apply to this or another federation at any time (see entity op 5 — Reactivate).</p>
</div>

{{-- 5 --}}
<div x-show="op === 5" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Remove an entity from a federation</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">entity.removeFromFederation</span>
    </div>
    <p class="small text-muted">Only <span class="badge bg-warning text-dark">Suspended</span> entities can be removed. If the entity is Active, suspend it first (entity op 4).</p>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page and go to the <strong>Members</strong> tab. Find the entity in the IdP or SP list.</li>
        <li class="mb-2">If the entity is Active, the trash icon is disabled — hover it to see the tooltip <em>"Suspend the entity first to remove it."</em></li>
        <li class="mb-2">Once the entity is Suspended, click the trash icon. A confirmation prompt appears. Confirm to proceed.</li>
        <li>The membership record is deleted and the entity is excluded from the next metadata generation immediately. The entity itself remains in the registry unchanged.</li>
    </ol>
</div>

{{-- 6 --}}
<div x-show="op === 6" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Assign a federation manager</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.create</span>
    </div>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page and go to the <strong>Managers</strong> tab.</li>
        <li class="mb-2">The <strong>Add Manager</strong> form lists all users with the Federation Manager role who are not yet assigned to this federation. Select a user from the dropdown and click <strong>Assign</strong>.</li>
        <li>The user appears in the managers list immediately. Their new permissions are active on their next request — no re-login is required. An in-app bell notification is sent to them confirming the assignment.</li>
    </ol>
    <p class="small text-muted mb-0">Admins can see a red <span class="badge bg-danger">Admin</span> badge next to any manager who also holds the application Admin role.</p>
</div>

{{-- 7 --}}
<div x-show="op === 7" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Remove a federation manager</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.create</span>
    </div>
    <p class="small text-muted">A federation must always have at least one manager. Removing the last manager is blocked — assign a replacement first.</p>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page and go to the <strong>Managers</strong> tab.</li>
        <li class="mb-2">Click <strong>Remove</strong> next to the manager you want to remove. A confirmation prompt appears, noting that the user will be logged out immediately.</li>
        <li>Confirm — the manager is detached from the federation. Their active session is invalidated and they are redirected to the login page with a message explaining that their access has been revoked. Their Jagger account and any entity manager roles are not affected.</li>
    </ol>
</div>

{{-- 8 --}}
<div x-show="op === 8" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Generate and publish federation metadata</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">metadata.generate</span>
    </div>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page and go to the <strong>Metadata</strong> tab.</li>
        <li class="mb-2">The <strong>Metadata Endpoints</strong> table lists three URLs:
            <ul class="mt-1">
                <li><strong>Full feed</strong> — the live public aggregate XML at <code>/metadata/{slug}/feed</code>. Share this with eduGAIN and remote federation operators.</li>
                <li><strong>eduGAIN feed</strong> — filtered subset at <code>/metadata/{slug}/edugain</code>, containing only entities marked <em>Export to eduGAIN</em>.</li>
                <li><strong>Download (cached)</strong> — authenticated download of the most recently generated signed XML.</li>
            </ul>
        </li>
        <li class="mb-2">In the <strong>Sign Metadata</strong> card, click <strong>Sign metadata</strong> to trigger immediate generation. The job builds the aggregate, signs it with the federation's own key pair (see op 9), caches the result, and updates the <em>Last signed</em> timestamp. If no key pair is uploaded, metadata is generated unsigned.</li>
        <li>Metadata is also regenerated automatically on the configured scheduler interval.</li>
    </ol>
    <hr class="my-3">
    <p class="small fw-semibold mb-2">Jagger compatibility endpoint</p>
    <p class="small">Federations migrated from Jagger can enable a legacy endpoint that serves signed metadata at the original Jagger URL pattern: <code>/signedmetadata/federation/{name}/metadata.xml</code></p>
    <ol class="small">
        <li class="mb-2">In the <strong>Jagger Compatibility Endpoint</strong> card on the Metadata tab, toggle <strong>Enable Jagger-compatible endpoint</strong> on.</li>
        <li class="mb-2">Set the <strong>Jagger federation name</strong> to match the name used in your existing Jagger URLs. The name must be unique across all federations.</li>
        <li>Click <strong>Save</strong>. The active endpoint URL is shown in the card. It serves the same signed XML as the full feed and returns 404 if the federation is inactive or the endpoint is disabled.</li>
    </ol>
</div>

{{-- 9 --}}
<div x-show="op === 9" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Upload per-federation signing keys</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.edit</span>
    </div>
    <p class="small">Each federation signs its metadata aggregate with its own dedicated private key and certificate. There is no global fallback — if no key pair is uploaded, metadata is generated <strong>unsigned</strong> and a warning is shown on the Metadata tab.</p>

    <p class="small fw-semibold mb-1">Uploading the key pair</p>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page and go to the <strong>Signing Keys</strong> tab. The two status cards show whether the private key and certificate are present. Grey = not uploaded; green = stored; amber/red on the certificate card = near expiry or expired.</li>
        <li class="mb-2">Under <strong>Upload Key Pair</strong>, select a file and click <strong>Upload</strong>. The app auto-detects the format:
            <ul class="mt-1">
                <li class="mb-1"><strong>PKCS#12 bundle</strong> (<code>.p12</code>, <code>.pfx</code>) — both key and certificate extracted in one step.</li>
                <li class="mb-1"><strong>PEM with both key and cert</strong> — both extracted in one step.</li>
                <li class="mb-1"><strong>PEM private key only</strong> — key held in memory; wizard advances to Step 2 asking for the certificate.</li>
                <li class="mb-1"><strong>PEM certificate only</strong> — certificate held in memory; wizard advances to Step 2 asking for the key.</li>
            </ul>
        </li>
        <li class="mb-2">In <strong>Step 2</strong>, upload the companion credential. The app verifies the key and certificate match before storing either. If they do not match, an error is shown and you can retry Step 2 without losing the Step 1 credential.</li>
        <li>On a successful match, both are stored atomically and the status cards turn green. The pair is used on the next metadata generation.</li>
    </ol>

    <p class="small fw-semibold mb-1">Viewing credential details</p>
    <p class="small">Click the <strong>ⓘ</strong> icon on either status card to open the info modal. The key modal shows key type and bit length. The certificate modal shows subject, issuer, serial, validity dates with a coloured expiry badge, the full PEM, and a <strong>Download .crt</strong> button.</p>

    <p class="small fw-semibold mb-1">Removing the key pair</p>
    <p class="small">Click <strong>Remove Key Pair</strong> to delete both credentials in one operation. The status cards return to the "Not uploaded" state and the next metadata generation will be unsigned.</p>

    @if(config('federation.signing_drivers.SOFTHSM_SIGNING_IS_ACTIVE'))
    <p class="small fw-semibold mb-1 mt-3">Migrating from file driver to SoftHSM2</p>
    <p class="small">When a federation has a complete file driver key pair, a <strong>Migrate to SoftHSM2</strong> panel appears at the bottom of the tab. Six pre-flight checks must all pass (shell exec availability, <code>softhsm2-util</code>, <code>pkcs11-tool</code>, <code>JAGGER_HSM_PIN</code>, readable <code>SOFTHSM2_CONF</code>, and the PKCS#11 library). A <strong>Manual CLI migration steps</strong> block is always visible for out-of-band migration regardless of the pre-flight result. On success the federation's signing driver is switched to <code>softhsm</code>, the existing certificate is reused (no relying party changes needed), and a <code>federation_signing_driver_migrated</code> entry is written to the audit log.</p>
    @endif

    <div class="alert alert-warning small py-2 px-3 mb-3 mt-2">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Post-signing integrity check.</strong> After every signing operation the app verifies the signed output: root element is <code>&lt;md:EntitiesDescriptor&gt;</code>, entity count matches, all entityIDs are present, and <code>validUntil</code> is in the future and at most 14 days away. If any check fails, the job aborts, the previous cached metadata is preserved unchanged, and a <code>metadata_sign_failed</code> entry is written to the audit log. Check the audit log and <code>storage/logs/laravel.log</code> for the specific failure reason.
    </div>

    @if(config('federation.signing_drivers.SOFTHSM_SIGNING_IS_ACTIVE'))
    <div class="alert alert-danger small py-2 px-3 mb-0">
        <i class="bi bi-shield-exclamation me-1"></i>
        <strong>SoftHSM2 security note.</strong> Anyone with root access to this server and knowledge of <code>JAGGER_HSM_PIN</code> can sign metadata directly via xmlsectool, bypassing this application entirely. There is no technical prevention. Monitor the audit log for metadata signing entries that do not correspond to scheduled or manually triggered generations.
    </div>
    @endif
</div>

{{-- 10 --}}
<div x-show="op === 10" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Add a registration policy</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.edit</span>
    </div>
    <p class="small">A registration policy is a per-language URL pointing to the federation's published policy document. It appears in every member entity's metadata as an <code>mdrpi:RegistrationPolicy</code> element inside <code>mdrpi:RegistrationInfo</code>. One policy per language is allowed.</p>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page and go to the <strong>Policies</strong> tab, then click <strong>Add Policy</strong>.</li>
        <li class="mb-2">Select the <strong>Language</strong> — the dropdown lists only languages without an existing policy. Once all nine supported languages are used, the form shows a notice and further additions are blocked.</li>
        <li class="mb-2">Enter the <strong>Display Name</strong> — a short label for the policy (e.g. "Federation Registration Policy").</li>
        <li class="mb-2">Enter the <strong>Policy URL</strong> — the public HTTPS URL where the policy document is hosted.</li>
        <li class="mb-2">Optionally enter an <strong>Internal Note</strong> — stored only in the registry; not published in metadata.</li>
        <li class="mb-2">Leave <strong>Enabled</strong> checked (default) to publish the policy immediately, or uncheck to save it without including it in metadata.</li>
        <li>Click <strong>Save Policy</strong>. You are returned to the Policies list.</li>
    </ol>
    <p class="small text-muted mb-0">To edit or delete a policy, use the pencil and trash icons on the Policies list. Disabled policies are excluded from published metadata without being deleted.</p>
</div>

{{-- 11 --}}
<div x-show="op === 11" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Configure an external validator</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.edit</span>
    </div>
    <p class="small">External validators are HTTP services that receive entity metadata XML and return a structured result. Validators are always non-blocking — the entity proceeds regardless of outcome.</p>

    <p class="small fw-semibold mb-1">Adding a validator</p>
    <ol class="small">
        <li class="mb-2">Go to the federation's <strong>Validators</strong> tab and click <strong>Add Validator</strong>.</li>
        <li class="mb-2">Enter the <strong>URL</strong>, <strong>HTTP Method</strong> (GET or POST), <strong>Metadata Param Name</strong> (the parameter the validator expects XML in, default <code>metadata</code>), <strong>Args Separator</strong> (<code>&amp;</code> for query-string style, <code>/</code> for path style), and <strong>Timeout</strong> (5–120 s).</li>
        <li class="mb-2">Optionally add <strong>Additional Arguments</strong> — extra key=value pairs sent with every request.</li>
        <li class="mb-2">In <strong>Response Parsing</strong>, set the XML element names that hold the result code and message, and the values for each severity level. The expected response format is:
            <pre class="bg-light rounded p-2 mt-1 small font-monospace">&lt;validation&gt;
  &lt;returncode&gt;0&lt;/returncode&gt;
  &lt;message&gt;Validation passed&lt;/message&gt;
&lt;/validation&gt;</pre>
        </li>
        <li class="mb-2">Set the three behaviour toggles:
            <ul class="mt-1">
                <li><strong>Active</strong> — off = skipped everywhere (manual test still works).</li>
                <li><strong>Run on Registration</strong> — runs automatically on entity registration, update, or approval. Result is logged to the audit log.</li>
                <li><strong>Mandatory</strong> — if the validator reports error or critical on an automatic run, all federation managers receive a notification.</li>
            </ul>
        </li>
        <li>Click <strong>Save Validator</strong> or <strong>Save &amp; Test</strong> — the latter saves and immediately opens the test panel.</li>
    </ol>

    <p class="small fw-semibold mb-1">Testing a validator</p>
    <p class="small">Click the <i class="bi bi-play-circle"></i> icon on the Validators list. Select a federation entity and click <strong>Run Validator</strong>. The result shows inline: success, warning, error/critical, or a connection problem (timeout, SSL error, unreachable) with the specific reason.</p>

    <p class="small text-muted mb-0">Validators follow the Jagger response format and are compatible with existing Jagger validator deployments.</p>
</div>

{{-- 12 --}}
<div x-show="op === 12" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Send email to federation members</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.edit</span>
    </div>

    <p class="small fw-semibold mb-1">Composing and sending</p>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page and click <strong>Send Email</strong> in the header.</li>
        <li class="mb-2">Optionally load a <strong>template</strong>. Templates marked <strong>★ Custom</strong> use this federation's customised version; others use the system default.</li>
        <li class="mb-2">Choose <strong>Recipients</strong> (All / IdPs only / SPs only) and <strong>Contact Types</strong>. Each option shows entity count and approximate email count.</li>
        <li class="mb-2">Edit the <strong>Subject</strong> and <strong>Body</strong>. Use <code>[[placeholders]]</code> — they are replaced per entity when sent.</li>
        <li class="mb-2">Click <strong>Preview</strong> to see the rendered email using a sample federation entity.</li>
        <li>Click <strong>Send</strong> — confirm the entity and email counts. Emails are queued asynchronously.</li>
    </ol>

    <p class="small fw-semibold mb-1">Customising federation email templates</p>
    <p class="small">Federations can override any system template — for both manual sends and automated notifications (approved, rejected, expired, etc.).</p>
    <ol class="small">
        <li class="mb-2">Click <strong>Email Templates</strong> in the federation header. The list shows all system templates with <span class="badge bg-success">Custom</span> / <span class="badge bg-light text-secondary border">System default</span> status.</li>
        <li class="mb-2">Click <strong>Customise</strong> to open the edit form pre-filled with the current content. Edit and click <strong>Preview</strong> to verify the rendered output.</li>
        <li class="mb-2">Click <strong>Save Custom Template</strong>. The override is used immediately for all emails from this federation.</li>
        <li>To revert, click the reset icon on the templates list.</li>
    </ol>

    <p class="small text-muted mb-0">All sent emails — manual and automated — are recorded in the <strong>Mail Log</strong> (Send Email → View Mail Log). Auto-sent rows show no sender.</p>
</div>

{{-- 13 --}}
<div x-show="op === 13" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Export member contact list</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.view</span>
    </div>
    <ol class="small">
        <li class="mb-2">Open the federation's <strong>General</strong> tab and find the <strong>Download contacts</strong> row.</li>
        <li class="mb-2">Choose your filters:
            <ul class="mt-1">
                <li><strong>Entity type</strong> — All, IdPs only, or SPs only.</li>
                <li><strong>Contact type</strong> — All, Technical, Support, Security, or Administrative.</li>
                <li><strong>Format</strong> — <strong>CSV</strong> (spreadsheet-ready, with column headers) or <strong>Plain text</strong> (human-readable, grouped by entity).</li>
            </ul>
        </li>
        <li class="mb-2">Optionally tick <strong>Deduplicate emails</strong> — each email address appears only once even if the same person is contact for multiple entities.</li>
        <li>Click <strong>Download</strong>. The file is generated immediately.</li>
    </ol>
    <p class="small text-muted mb-0">CSV headers: <code>Entity Type, Entity Name, Entity ID, Contact Type, Contact Name, Contact Email</code>. Filename includes the active filters and date.</p>
</div>

{{-- 14 --}}
<div x-show="op === 14" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Deactivate / Reactivate a federation</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.edit</span>
    </div>

    <p class="small">Deactivation sets the federation status to <span class="badge bg-secondary">Inactive</span> and stops metadata publication. The federation record and all member entities remain in the registry — no data is deleted. A federation must be inactive before it can be deleted (op 15).</p>

    <p class="small fw-semibold mb-1">Deactivating a federation</p>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page. Click <strong>Deactivate</strong> in the header — this button is visible only when the federation is Active.</li>
        <li class="mb-2"><strong>Step 1 — Impact:</strong> Review the counts of active and pending member entities that will be affected.</li>
        <li class="mb-2"><strong>Step 2 — Active Entities:</strong> Choose what happens to currently active members:
            <ul class="mt-1">
                <li><strong>Leave in place</strong> — membership pivot records stay active; entities are simply excluded from future metadata generation while the federation is inactive.</li>
                <li><strong>Disable (Suspend)</strong> — all active member pivots are set to suspended.</li>
                <li><strong>Move to another federation</strong> — members are detached from this federation and re-attached to the selected target as Pending, requiring re-approval there.</li>
            </ul>
        </li>
        <li class="mb-2"><strong>Step 3 — Pending Entities:</strong> (Skipped if none.) Choose to keep open pending requests or auto-reject them all.</li>
        <li class="mb-2"><strong>Step 4 — Jobs:</strong> Informational — confirms that scheduled metadata generation will stop until the federation is reactivated.</li>
        <li class="mb-2"><strong>Step 5 — Notify:</strong> Optionally send a deactivation email to entity contacts. Toggle on/off and select contact types. If the federation has a custom email template for <code>federation_deactivated</code>, that template is used; otherwise the system default applies.</li>
        <li>Click <strong>Deactivate Federation</strong> on the final confirmation step. The status changes to Inactive immediately; the metadata cache is cleared; the action is written to the audit log.</li>
    </ol>

    <p class="small fw-semibold mb-1">Reactivating a federation</p>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page and go to the <strong>General</strong> tab.</li>
        <li class="mb-2">In the <strong>Edit Federation</strong> panel, change <strong>Status</strong> from Inactive to <strong>Active</strong> and click <strong>Save</strong>.</li>
        <li>The federation is active immediately and will be included in the next scheduled metadata generation cycle. All federation managers receive an in-app notification. The reactivation is written to the audit log.</li>
    </ol>
    <p class="small text-muted mb-0">Reactivation does not automatically re-enable suspended member entities — restore their membership status individually if needed.</p>
</div>

{{-- 15 --}}
<div x-show="op === 15" x-cloak>
    <div class="mb-3">
        <h5 class="fw-bold mb-1">Delete a federation</h5>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">federation.create</span>
    </div>

    <div class="alert alert-warning py-2 small mb-3">
        <strong>Prerequisite:</strong> A federation must be <span class="badge bg-secondary">Inactive</span> before it can be deleted. Deactivate it first (op 14). Active federations block deletion.
    </div>

    <p class="small fw-semibold mb-1">Soft-delete (move to trash)</p>
    <ol class="small">
        <li class="mb-2">Open the federation's detail page. The <strong>Delete</strong> button appears in the header only when status is Inactive.</li>
        <li class="mb-2">Click <strong>Delete</strong> and confirm the prompt. The federation is moved to the trash — it is no longer visible in the main Federations list but is not permanently removed.</li>
        <li>All federation managers receive an in-app notification. The action is recorded in the audit log.</li>
    </ol>

    <p class="small fw-semibold mb-1">Restore from trash</p>
    <ol class="small">
        <li class="mb-2">Go to <strong>Federations → Trashed</strong> (accessible via the trash icon or the count badge on the Federations index).</li>
        <li class="mb-2">Find the federation and click <strong>Restore</strong>. Confirm the prompt.</li>
        <li>The federation is returned to the main list with status <span class="badge bg-secondary">Inactive</span>. The restore is logged to the audit log. Reactivate it via op 14 when ready.</li>
    </ol>

    <p class="small fw-semibold mb-1">Permanent deletion (force-delete)</p>
    <div class="alert alert-danger py-2 small mb-2">
        <strong>Irreversible.</strong> Force-delete permanently removes the federation record, all metadata, and any uploaded signing key files from disk. This cannot be undone.
    </div>
    <ol class="small">
        <li class="mb-2">On the Trashed page, click <strong>Delete Permanently</strong> next to the federation.</li>
        <li class="mb-2">If the federation still has active member entities, the action is blocked — the error message will indicate this. Remove or suspend active members before proceeding.</li>
        <li>Confirm the prompt. The database record, all pivot rows, related mail templates, and signing key files are permanently removed. The action is recorded in the audit log.</li>
    </ol>
</div>
