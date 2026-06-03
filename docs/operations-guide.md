# Operations Guide

Each operation provides a step-by-step UI workflow for the operator.

---

## Operations Index

### Entity Operations
1. [Register a new entity (manual form)](#1-register-a-new-entity-manual-form)
2. [Import an entity from XML metadata](#2-import-an-entity-from-xml-metadata)
3. [Edit entity details](#3-edit-entity-details)
4. [Suspend an entity](#4-suspend-an-entity)
5. [Reactivate a suspended entity](#5-reactivate-a-suspended-entity)
6. [Delete / restore an entity](#6-delete--restore-an-entity)
7. [Validate entity compliance](#7-validate-entity-compliance)
8. [Preview entity XML (metadata.xml)](#8-preview-entity-xml-metadataxml)
9. [Manage SP requested attributes](#9-manage-sp-requested-attributes)
10. [Configure IdP attribute release policy (ARP)](#10-configure-idp-attribute-release-policy-arp)
11. [Invite a contact as co-manager](#11-invite-a-contact-as-co-manager)
12. [Send a direct invitation](#12-send-a-direct-invitation)

### Federation Operations
13. [Create a federation](#13-create-a-federation)
14. [Add an entity to a federation](#14-add-an-entity-to-a-federation)
15. [Approve entity membership](#15-approve-entity-membership)
16. [Reject entity membership](#16-reject-entity-membership)
17. [Remove an entity from a federation](#17-remove-an-entity-from-a-federation)
18. [Assign a federation manager](#18-assign-a-federation-manager)
19. [Remove a federation manager](#19-remove-a-federation-manager)
20. [Generate and publish federation metadata](#20-generate-and-publish-federation-metadata)
21. [Upload per-federation signing keys](#21-upload-per-federation-signing-keys)
22. [Add a registration policy](#22-add-a-registration-policy)
23. [Configure an external validator](#23-configure-an-external-validator)
24. [Send email to federation members](#24-send-email-to-federation-members)
25. [Export member contact list](#25-export-member-contact-list)
26. [Deactivate / Reactivate a federation](#26-deactivate--reactivate-a-federation)
27. [Delete a federation](#27-delete-a-federation)

### User & Access Operations
28. [Invite a new user](#28-invite-a-new-user)
29. [Approve / reject an access request](#29-approve--reject-an-access-request)
30. [Change a user's role](#30-change-a-users-role)
31. [Suspend / reactivate a user account](#31-suspend--reactivate-a-user-account)

### Monitoring & Reporting
32. [Monitor certificate expiry](#32-monitor-certificate-expiry)
33. [View the audit log](#33-view-the-audit-log)
34. [Export statistics as CSV](#34-export-statistics-as-csv)

### System Administration
35. [Import from Jagger](#35-import-from-jagger)
36. [Run a scheduled job immediately](#36-run-a-scheduled-job-immediately)
37. [Configure scheduler timings](#37-configure-scheduler-timings)
38. [Set system preferences](#38-set-system-preferences)
39. [Manage compliance rules](#39-manage-compliance-rules)
40. [Configure a webhook endpoint](#40-configure-a-webhook-endpoint)

---

## Operation Details

### 1. Register a new entity (manual form)

> **Permission required:** `entity.create`

1. Go to **Entities** → click **New Entity**.
2. On the **Basic Info** tab:
   - Enter the **Entity ID** (a globally unique HTTPS URI, e.g. `https://idp.example.org/idp/shibboleth`). The Entity ID cannot be changed after creation.
   - Select the entity **Type**: Service Provider (SP) or Identity Provider (IdP).
   - Tick **Export to eduGAIN** if the entity participates in eduGAIN interfederation.
   - Fill in the **Display Name** and **Description** (English, required).
   - Optionally enter the **Information URL**, **Privacy Statement URL**, and **Logo URL** with dimensions.
3. On the **Endpoints** tab, enter the SAML endpoints for the entity type:
   - *IdP*: at least one SingleSignOnService URL (HTTP-POST or HTTP-Redirect); optionally SOAP and SLO endpoints; tick supported NameID formats; enter the Shibboleth scope.
   - *SP*: HTTP-POST AssertionConsumerService URL (required); optionally HTTP-Redirect and PAOS ACS; SLO endpoints; tick SP security flags as needed.
4. On the **Certificates** tab, paste the entity's PEM certificate and select its use (signing / encryption / both). Use **Add Certificate** to add additional certificates.
5. On the **Organisation** tab:
   - Fill in the **Organisation Name**, **Display Name**, and **URL** (all required).
   - Optionally enter geographic coordinates or use the **Pick on map** button.
   - Add at least one **Contact Person** (type, name, email). Use **Add Contact** for additional contacts.
   - Optionally tick one or more **Federations** to request membership — the entity joins with status *pending* and awaits approval.
6. On the **REFEDS** tab, optionally select **Entity Categories** and tick **SIRTFI** compliance (a security contact with an email address is mandatory when SIRTFI is asserted).
7. On the **Languages** tab, optionally add translations of the display name, description, or URLs for additional languages.
8. Click **Validate** — compliance rules run against the entered data. Resolve any errors shown in the results table. If only warnings remain, tick the acknowledgement checkbox to continue.
9. Click **Register Entity** — the entity is created with status **Draft** and you are taken to its detail page. If federation membership was selected, an approval request is sent to the federation operator.

---

---

### 2. Import an entity from XML metadata

> **Permission required:** `entity.create`

1. Go to **Entities** → click **Import from XML**.
2. Paste the entity's `<md:EntityDescriptor>` XML document into the text area and click **Parse XML**. If the XML is invalid or cannot be parsed, an error is shown and you can correct and retry.
3. Review the **Preview Import** page — it shows what was extracted from the XML: entity ID, type, scope, display names and descriptions (all languages), organisation details, contacts, endpoints, certificates, and REFEDS entity categories. Verify the data is correct before proceeding.
4. Choose how to continue:
   - Click **Import Entity** to save the entity immediately as parsed. The entity is created with status **Draft** and you are taken to its detail page.
   - Click **Edit in Full Form** to open the pre-filled registration form if you need to adjust any field. Complete the form across the relevant tabs and click **Register Entity** — validation runs automatically on save.
5. Optionally, use **Import from JSON** (linked on the import page) as an alternative if the source data is available as a JSON object rather than XML.

---

---

### 3. Edit entity details

> **Permission required:** `entity.edit`

1. Open the entity's detail page and click **Edit**.
2. The registration form opens pre-filled with the entity's current data. The **Entity ID** and **Type** fields are locked and cannot be changed.
3. Update any fields across the tabs as needed:
   - **Basic Info** — display name, description, URLs, logo, eduGAIN flag.
   - **Endpoints** — SSO / ACS / SLO endpoint URLs, NameID formats, scope, SP security flags.
   - **Certificates** — add, remove, or change the use of certificates.
   - **Organisation** — organisation name, URL, coordinates, contact persons.
   - **REFEDS** — entity categories, SIRTFI flag.
   - **Languages** — multilingual variants for name, description, and URLs.
4. Click **Save Changes** — validation runs automatically; any errors are highlighted in the results table. Fix errors or acknowledge warnings, then save again.
5. On success you are returned to the entity detail page.

Federation membership is not managed here — use the dedicated federation operations ([ops 13](#13-create-a-federation)–[16](#16-reject-entity-membership)) to add or remove the entity from federations.

---

---

### 4. Suspend an entity

> **Permission required:** `entity.edit`

The **Suspend** action is available only for entities with status **Active**. Draft and Pending entities cannot be suspended — they can only be deleted.

1. Open the entity's detail page and click **Suspend**.
2. A four-step wizard opens:
   - **Step 1 — Impact**: review the entity's active federation memberships and any certificate warnings that will be affected by the suspension.
   - **Step 2 — Memberships**: choose what happens to the entity's active federation memberships:
     - *Disable memberships* — all active memberships are moved to Suspended status. The entity is removed from federation metadata until reactivated.
     - *Move to another federation* — the entity is detached from all current federations and re-submitted to a selected target federation as Pending, awaiting approval.
   - **Step 3 — Notify**: choose whether to send a suspension notification email to entity contacts. If enabled, select which contact types (technical, support, etc.) should receive the message. Use **Preview email** to review the message before confirming.
   - **Step 4 — Confirm**: review the suspension summary and click **Suspend Entity** to execute.
3. The entity status changes to **Suspended** and the federation metadata cache is cleared immediately. If notification was enabled, the email is sent to the selected contacts.

---

---

### 5. Reactivate a suspended entity

> **Permission required:** `entity.edit`

The **Reactivate** button is available only for entities with status **Suspended**.

1. Open the entity's detail page and click **Reactivate**.
2. A three-step wizard opens:
   - **Step 1 — Impact**: review the entity's current suspension state, existing federation membership status, and certificate health.
   - **Step 2 — Federation**: choose how to handle federation membership after reactivation:
     - *Re-apply to existing federation* — the suspended or rejected membership is re-submitted as Pending to the same federation it previously belonged to.
     - *Apply to a different federation* — the existing non-active membership is removed and a new Pending request is submitted to the selected federation.
     - *No federation* — the entity is reactivated without any federation membership change.
   - **Step 3 — Confirm**: optionally enable a reactivation notification email to selected contact types, review the summary, and click **Reactivate Entity**.
3. The entity status changes to **Active** immediately. However, if federation membership was re-applied, it is set to **Pending** — the entity will not appear in federation metadata until the federation operator approves the membership again.

---

---

### 6. Delete / restore an entity

> **Permission required:** `entity.delete` (delete and force-delete); `entity.edit` (restore)

**Delete (soft delete)**

Active entities cannot be deleted — suspend the entity first ([op 4](#4-suspend-an-entity)).

1. Open the entity's detail page. The **Delete** button is available for entities with status Draft, Pending, or Suspended.
2. Confirm the deletion prompt. The entity is moved to the trash and no longer appears in the main entity list. Its data is preserved and can be recovered.

**Restore**

1. From the **Entities** list, click **Deleted Entities** — a badge shows the count of trashed entities.
2. Find the entity in the list and click **Restore**.
3. The entity is recovered with status **Suspended**. Use **Reactivate** ([op 5](#5-reactivate-a-suspended-entity)) to return it to Active and re-apply for federation membership.

**Permanent delete**

1. From the **Deleted Entities** list, click **Delete permanently** on the entity.
2. Permanent deletion is blocked if the entity still has active federation memberships — remove the entity from all federations first.
3. Confirm the prompt. The entity and all its data are permanently removed and cannot be recovered.

---

---

### 7. Validate entity compliance

> **Permission required:** `entity.view`

Validation checks the entity's metadata against a set of structural, certificate, REFEDS, eduGAIN, and XSD rules. Results are cached and show the timestamp of the last check.

1. Open the entity's detail page and click **Validate**. The validation results page opens showing an overall status badge: *All checks passed*, *Passed with warnings*, or *Validation failed*.
2. The results table lists each rule by ID, its status (Pass / Fail / Warning), and a plain-language message describing what was found.
3. Click **Re-validate** to force a fresh check, bypassing the cached result.
4. A JSON export of the full result set is available for audit or external processing.

Validation also runs automatically when saving an entity through the registration or edit form. Errors must be resolved before the entity can be saved; warnings can be acknowledged and overridden.

Rules can be enabled, disabled, or have their severity changed at federation or entity level — see [op 39](#39-manage-compliance-rules) (compliance rules management).

---

---

### 8. Preview entity XML (metadata.xml)

> **Permission required:** `entity.view` (inline preview on the entity page); `metadata.view` (raw XML endpoint)

The XML preview shows exactly what the entity's SAML2 `<md:EntityDescriptor>` looks like at the moment of viewing — the same XML block that would be included in a published federation metadata aggregate. This is the **unsigned** XML; signing happens at federation metadata generation time and is not applied to the per-entity preview.

1. Open the entity's detail page. The **Generated Metadata XML** section near the bottom of the page shows the XML in a scrollable panel. This is a live rendering of the current data — changes made to the entity's endpoints, certificates, UI info, or other fields are reflected immediately on the next page load.
2. Scroll inside the panel to review the XML structure. The panel is limited to 400 px in height; the full document can be reviewed by scrolling or by opening it in a browser tab.
3. Click **Open raw** in the section header (or the **Metadata XML** button in the action bar at the top of the page) to open the full XML document in a new browser tab. The document is served as `application/xml` and the browser renders it as plain XML — use the browser's built-in viewer or copy the content from there.

The raw XML URL follows the pattern `/entities/{id}/metadata.xml`. This URL is stable and can be shared with others who have `metadata.view` access (e.g. the Guest role), though it is not an MDQ endpoint — it does not support the Metadata Query Protocol and is not suitable as a live discovery feed.

---

---

### 9. Manage SP requested attributes

> **Permission required:** `entity.view` (view list); `entity.edit` (add or remove)

Available on Service Provider entities only. The requested attributes list declares which user attributes the SP needs from the IdP, and is published in the generated metadata XML so that IdP operators and federation software can make informed attribute release decisions.

1. Open the SP's detail page and click **Requested Attributes** in the action bar.
2. The page shows two sections:
   - **Current attributes** — attributes already declared by this SP, each showing its full name, schema, whether it is required or optional, and any internal reason note.
   - **Available attributes** — the attribute catalogue, grouped by schema (the specification that defines the attribute, such as *eduPerson* or *SCHAC*). Attributes already added to the SP are excluded from this list.
3. To add an attribute: locate it in the available list (use the schema filter tabs to narrow the list), optionally tick **Required** and enter a **Reason** (an internal note for IdP operators explaining why the SP needs this attribute — not published in metadata), then click **Add**.
4. To remove an attribute: click **Remove** next to the attribute in the current list. This immediately removes it from the SP's metadata.

Added attributes are rendered in the generated metadata XML as `<md:RequestedAttribute>` elements inside an `<md:AttributeConsumingService>` block, with the `isRequired` flag set accordingly. The reason note is stored only in the registry and is not exported to XML.

---

---

### 10. Configure IdP attribute release policy (ARP)

> **Permission required:** `arp.view` (view); `arp.edit` (save rules)

Available on Identity Provider entities only. The ARP records which attributes the IdP operator intends to release to each SP that shares a federation with the IdP. This is a **documentation tool** — Jagger stores the policy for reference and transparency, but does not enforce it at runtime. The IdP software (Shibboleth, SimpleSAMLphp, etc.) governs what is actually released in SAML assertions. REFEDS and eduGAIN best practice is **default deny**: release only what is explicitly permitted, following the principle of data minimisation.

1. Open the IdP's detail page and click **Attribute Release Policy** in the action bar.
2. The page lists all SPs that share at least one federation with this IdP. For each SP its display name, entity ID, and declared requested attributes (from [op 9](#9-manage-sp-requested-attributes)) are shown.
3. For each SP–attribute combination, select **Permit** or **Deny**. Optionally add a **Note** to record the reasoning (e.g. a reference to a bilateral agreement or a review date).
4. Click **Save** — the rule is stored. Saving is per-SP: all attribute rules for the selected SP are submitted together.
5. Where no rule exists for an attribute, Jagger records no documented policy for that combination. What the IdP actually releases in that case depends entirely on the IdP software configuration.

---

---

### 11. Invite a contact as co-manager

> **Permission required:** `entity.requestContactInvitation`

An entity manager can request that a contact person listed on the entity is invited to become a co-manager. The request goes to the federation manager for approval; the co-manager, once confirmed, has the same access rights as the requesting entity manager.

1. Open the entity's detail page. The **Request Co-Manager** section lists the entity's registered contact persons. If the entity has no contacts the section is not shown — add a contact in the entity edit form first.
2. Find the contact you want to invite and click **Request Invitation** next to their email address.
3. A confirmation modal opens. Select the **federation** the contact will manage the entity within (only federations the entity currently belongs to are shown), then click **Send Request**.
4. The request is submitted to the federation manager for review. A notification is sent to the FM immediately.
5. To check the status of your request, go to **My Requests** in the sidebar. Each request shows its current status:
   - **Pending** — the FM has not yet reviewed it.
   - **Approved** — the FM accepted the request. If the contact already had an account they were added as co-manager immediately; otherwise an invitation email was sent to them.
   - **Rejected** — the FM declined the request and left a note explaining why.
6. The FM reviews pending requests under **Invitation Requests** in the sidebar and either approves or rejects each one.
7. Once registered and added, the co-manager can view, edit, and manage the entity with the same permissions as the requesting entity manager.

---

---

### 12. Send a direct invitation

> **Permission required:** `invitation.view`

An entity manager can send an invitation directly from the My Invitations page without going through the co-manager request workflow. The invitee must be listed as a contact on one of the entity manager's entities.

1. Go to **My Invitations** in the sidebar and click **New Invitation**.
2. Select the **Entity** — the dropdown shows only entities you manage.
3. Select the **Contact to invite** — only contacts already registered on the entity are available. Add the contact in the entity edit form first if they are not listed.
4. Click **Send Invitation** — an email is sent immediately. No FM approval required.
5. The invitation appears in the **Pending** tab. From there you can **Copy URL** (to share via another channel), **Resend** (if the email expired or was lost), or **Revoke** (to cancel).

---

---

### 13. Create a federation

> **Permission required:** `federation.create`

1. Go to **Federations** → click **New Federation**.
2. Enter the **Name** — the human-readable display name used in the UI and in published metadata.
3. Enter the **Registration Authority URI** — a globally unique HTTPS URI that identifies this federation as the `registrationAuthority` in the `mdrpi:RegistrationInfo` element published in each member entity's metadata. This value cannot be changed after creation without republishing all member metadata.
4. Optionally enter a **Description** describing the federation's scope and membership criteria.
5. Optionally enter the **Metadata URL** — the public URL where this federation publishes its aggregate metadata XML. This is informational; it does not configure the metadata generation endpoint (see [op 19](#19-remove-a-federation-manager)).
6. Click **Create Federation** — the federation is created with status **Active** and you are taken to its detail page.

After creation, configure the federation further from its detail page:
- Add yourself or other users as federation managers ([op 18](#18-assign-a-federation-manager)).
- Upload a signing key pair for metadata signing ([op 21](#21-upload-per-federation-signing-keys)).
- Add a registration policy ([op 22](#22-add-a-registration-policy)).
- Configure external validators ([op 23](#23-configure-an-external-validator)).

> **Signing driver field:** if the instance has more than one signing driver active (e.g. Local file + SoftHSM2), a **Signing Driver** selector appears on the create form. Choose the driver that matches your infrastructure. If only one driver is active the field is hidden and set automatically — no action needed.

---

---

### 14. Add an entity to a federation

> **Permission required:** `entity.addToFederation`

An entity can be added to a federation in two ways: by a federation manager adding it directly from the federation's membership page, or by an entity manager selecting a federation during entity registration ([op 1](#1-register-a-new-entity-manual-form)) or reactivation ([op 5](#5-reactivate-a-suspended-entity)).

**From the federation membership page (FM-initiated):**

1. Open the federation's detail page and go to the **Members** tab.
2. Click **Add IdP directly** or **Add SP directly** — this opens the entity registration form pre-filled with the selected type and federation, creating the entity in one step with a *Pending* membership.
3. Alternatively, click **Invite IdP** or **Invite SP** to send an invitation to an existing entity manager ([op 13](#13-create-a-federation) invitation flow — see [op 26](#26-deactivate--reactivate-a-federation)).

**From the entity registration form (EM-initiated):**

1. On the **Organisation** tab of the entity registration or edit form, tick one or more federations from the list.
2. The entity is submitted with membership status **Pending** for each selected federation. An approval request notification is sent to the federation manager.

A pending membership expires automatically after the number of days configured in **System Preferences → Federation → Pending membership expiry**. If the deadline passes without action, the membership is auto-rejected and the entity manager is notified.

---

---

### 15. Approve entity membership

> **Permission required:** `federation.approveRequest`

1. Open the federation's detail page and go to the **Members** tab. Pending requests are listed in the **Pending Approval** section at the top, with a count badge. Each row shows the entity's display name, type (IdP / SP), entity ID, and the deadline by which the request will auto-expire.
2. Click **View** (eye icon) to open the entity's detail page and review its metadata before deciding.
3. Click **Approve** — a confirmation prompt appears. Confirm to proceed.
4. The membership status changes to **Active** immediately. If the entity was previously in Draft or Pending status, it is promoted to Active at the same time. The entity manager and entity technical contacts receive an approval notification.
5. The entity will be included in the next metadata generation cycle ([op 19](#19-remove-a-federation-manager)).

---

---

### 16. Reject entity membership

> **Permission required:** `federation.rejectRequest`

1. Open the federation's detail page and go to the **Members** tab. Pending requests appear in the **Pending Approval** section.
2. Click **Reject** on the relevant row. A prompt opens asking for a **rejection reason** — this field is mandatory.
3. Enter the reason and confirm. The membership status changes to **Rejected**. The reason is stored on the membership record and included in the rejection notification sent to the entity manager and entity technical contacts.
4. The entity remains in the registry and can re-apply to this or another federation at any time (see [op 5](#5-reactivate-a-suspended-entity) — Reactivate).

---

---

### 17. Remove an entity from a federation

> **Permission required:** `entity.removeFromFederation`

The **Remove** action permanently detaches the entity from the federation — the membership record is deleted, not soft-deleted. Removing an entity from a federation does not delete the entity itself.

Only **Suspended** entities can be removed. If the entity is Active, suspend it first ([op 4](#4-suspend-an-entity)).

1. Open the federation's detail page and go to the **Members** tab. Find the entity in the IdP or SP list.
2. If the entity is Active, the trash icon is disabled and shows a tooltip: *"Suspend the entity first to remove it."* Suspend the entity ([op 4](#4-suspend-an-entity)) before proceeding.
3. Once the entity is Suspended, click the trash icon. A confirmation prompt appears.
4. Confirm — the membership row is deleted and the entity is excluded from the next metadata generation immediately.

The entity remains in the registry with its own status unchanged. It can be re-added to this or another federation at any time.

---

---

### 18. Assign a federation manager

> **Permission required:** `federation.create`

1. Open the federation's detail page and go to the **Managers** tab.
2. The **Add Manager** form lists all users with the Federation Manager role who are not yet assigned to this federation. Select a user from the dropdown and click **Assign**.
3. The user is added to the managers list immediately. Their new permissions are active on their next request — no re-login is required. An in-app notification is sent to their bell informing them of the assignment.

Admins can see a red **Admin** badge next to any manager who also holds the application Admin role.

---

---

### 19. Remove a federation manager

> **Permission required:** `federation.create`

A federation must always have at least one manager. Removing the last manager is blocked — assign a replacement first.

1. Open the federation's detail page and go to the **Managers** tab.
2. Click **Remove** next to the manager you want to remove. A confirmation prompt appears, noting that the user will be logged out immediately.
3. Confirm — the manager is detached from the federation. Their active session is invalidated and they are redirected to the login page with a notification explaining that their access has been revoked. Their Jagger account and entity manager roles (if any) are not affected.

---

---

### 20. Generate and publish federation metadata

> **Permission required:** `metadata.generate` (generate); `metadata.view` (download / view endpoints); no permission required for the public feed URLs.

1. Open the federation's detail page and go to the **Metadata** tab.
2. The **Metadata Endpoints** table lists three URLs:
   - **Full feed** — the live public aggregate XML for this federation, served at `/metadata/{slug}/feed`. This is the URL to give to eduGAIN and remote federation operators.
   - **eduGAIN feed** — a filtered subset containing only entities marked *Export to eduGAIN*, served at `/metadata/{slug}/edugain`.
   - **Download (cached)** — serves the most recently generated signed XML as a file download (authenticated only).
3. In the **Sign Metadata** card, click **Sign metadata** to trigger generation immediately. The job builds the `<md:EntitiesDescriptor>`, signs it with the federation's signing key (or the system-wide fallback), and caches the result. The *Last signed* timestamp updates on completion.
4. Metadata is also regenerated automatically on the configured scheduler interval (see [op 35](#35-import-from-jagger)).

**Jagger compatibility endpoint**

Federations migrated from Jagger can enable a legacy endpoint that serves signed metadata at the original Jagger URL pattern:

```
/signedmetadata/federation/{name}/metadata.xml
```

1. On the **Metadata** tab, find the **Jagger Compatibility Endpoint** card.
2. Toggle **Enable Jagger-compatible endpoint** on.
3. Set the **Jagger federation name** to match the name used in existing Jagger URLs (pre-filled from the federation slug). Only letters, numbers, hyphens, and underscores are accepted. The name must be unique across all federations.
4. Click **Save**. The active URL is shown immediately below the form.
5. The endpoint serves the same signed XML as the full feed, from the same cache. It returns 404 if the federation is inactive or the endpoint is disabled.

---

---

### 21. Upload per-federation signing keys

> **Permission required:** `federation.edit` (assigned FM or Admin)

Each federation signs its metadata aggregate with its own dedicated private key and certificate. There is no global fallback key — if no key pair is uploaded for a federation, metadata is generated **unsigned**. A warning is shown on the Metadata tab and in the Sign Metadata card until a pair is configured.

The exact UI depends on the federation's **signing driver** (set at creation or on the Signing Keys tab):

---

**File driver (Local file)**

Keys and certificates are stored as PEM files on the server filesystem at `storage/app/signing-keys/{federation-id}/`.

1. Open the federation's detail page and go to the **Signing Keys** tab.
2. Check the two status cards. Grey ("Not uploaded") means no credential is present. Amber/red on the certificate card means the certificate is near expiry or expired.
3. Under **Upload Key Pair**, select a file and click **Upload**. Auto-detected formats:

   | Format | What happens |
   |--------|-------------|
   | PKCS#12 bundle (`.p12`, `.pfx`) | Both key and certificate extracted — wizard completes in one step |
   | PEM containing both key and cert | Both extracted — wizard completes in one step |
   | PEM private key only | Key held in memory; Step 2 opens asking for the certificate |
   | PEM certificate only | Certificate held in memory; Step 2 opens asking for the key |

4. In **Step 2**, upload the companion credential. The app verifies the key and certificate match before storing either. If they do not match, an error is shown — the Step 1 credential is still held in memory and you can retry Step 2.
5. On match, both are stored atomically. Status cards turn green. The pair is used on the next metadata generation.

---

**SoftHSM2 driver (PKCS#11 token)**

Keys are stored inside a dedicated hardware security module token (one token per federation). The upload wizard is identical to the file driver — the app handles token initialisation internally.

Prerequisites on the server:
- `SOFTHSM_SIGNING_IS_ACTIVE=true` in `.env`
- `JAGGER_HSM_PIN` set to the token PIN
- `pkcs11-tool` and `softhsm2-util` binaries available

The Signing Keys tab shows additional SoftHSM2-specific cards:
- **PIN status** — confirms `JAGGER_HSM_PIN` is set in the environment
- **Token status** — whether the PKCS#11 token has been initialised for this federation

> **Security note:** Anyone with root access to the server and knowledge of `JAGGER_HSM_PIN` can sign arbitrary metadata directly via xmlsectool, bypassing this application entirely. There is no technical prevention. The only detection mechanism is the **audit log** — review it for signing entries that do not correspond to scheduled or manually triggered generations.

---

**Migrating from file driver to SoftHSM2**

When `SOFTHSM_SIGNING_IS_ACTIVE=true` is set in `.env` and a federation has a complete key pair under the file driver, a **Migrate to SoftHSM2** panel appears at the bottom of the Signing Keys tab. The migration imports the existing PEM credentials into a PKCS#11 token without changing the certificate seen by relying parties — no metadata republish is needed.

Pre-flight checks required before the wizard can run:

| Check | Requirement |
|-------|------------|
| Shell execution | `exec()` / `proc_open()` not in `disable_functions` |
| softhsm2-util | Binary found at a known path or via `which` |
| pkcs11-tool | Binary found at a known path or via `which` |
| JAGGER_HSM_PIN | Set in `.env` |
| SOFTHSM2_CONF | File readable by the web process |
| PKCS11_LIBRARY | `libsofthsm2.so` (or configured path) exists |

A **Manual CLI migration steps** block is always visible (collapsible) and contains the complete shell commands pre-filled for the specific federation — useful if `exec()` is unavailable or if you prefer to run the migration outside the app.

Wizard steps on success:
1. PEM files are read directly from `storage/app/signing-keys/{id}/`
2. Private key is imported into a new SoftHSM2 token (`jagger-fed-{id}`)
3. Certificate is imported as a PKCS#11 cert object via `pkcs11-tool`
4. Federation `signing_driver` is updated to `softhsm`
5. Audit log entry `federation_signing_driver_migrated` is written
6. (Optional) PEM files are deleted from disk if **Delete PEM files** was ticked

On failure the log up to the failure point is displayed and a **Try again** button is shown. Fix the underlying issue (check `storage/logs/laravel.log`) before retrying.

---

**Viewing credential details**

Click the **ⓘ** icon on either status card to open the info modal:
- **Key info** — key type (RSA / EC) and bit length
- **Certificate info** — subject, issuer, serial, validity dates with coloured expiry badge, full PEM text, and a **Download .crt** button

---

**Removing the key pair**

Click **Remove Key Pair** to delete both credentials in one operation. For the file driver this removes the PEM files from disk. For SoftHSM2 this destroys the PKCS#11 token. After removal the status cards return to "Not uploaded" and the next metadata generation will be unsigned.

---

---

### 22. Add a registration policy

> **Permission required:** `federation.edit` (assigned FM or Admin)

A registration policy is a per-language URL pointing to the federation's published policy document. It is included in every member entity's metadata as an `mdrpi:RegistrationPolicy` element inside the `mdrpi:RegistrationInfo` block. One policy per language is allowed.

1. Open the federation's detail page and go to the **Policies** tab, then click **Add Policy**.
2. Select the **Language** — the dropdown lists only languages that do not already have a policy for this federation. Once all nine supported languages are used the form shows a notice and the button is disabled.
3. Enter the **Display Name** — a short label for the policy (e.g. "Federation Registration Policy").
4. Enter the **Policy URL** — the public HTTPS URL where the policy document is hosted. The URL must use HTTPS.
5. Optionally enter an **Internal Note** — free text stored only in the registry; not published in metadata.
6. Leave **Enabled** checked (default) to include the policy in metadata immediately, or uncheck to save it in draft state without publishing.
7. Click **Save Policy**. You are returned to the Policies list.

To edit or delete a policy, use the pencil and trash icons on the Policies list. Deleting requires a confirmation prompt. If a policy is disabled, it is excluded from published metadata without being deleted.

---

---

### 23. Configure an external validator

> **Permission required:** `federation.edit` (assigned FM or Admin)

External validators are HTTP services that accept entity metadata XML and return a structured result. Jagger sends entity metadata to each configured validator and records the outcome. Validators never block an entity — they are informational and notification-driven.

**Adding a validator**

1. Open the federation's detail page and go to the **Validators** tab, then click **Add Validator**.
2. Fill in the **Connection Settings**:
   - **Name** — a label shown in the validators list and in notifications.
   - **URL** — the HTTPS endpoint of the validator service (e.g. `https://validator.example.org/validate`).
   - **HTTP Method** — `GET` or `POST`. Most validators use POST.
   - **Metadata Param Name** — the query/body parameter name the validator expects the XML to be sent as (default: `metadata`).
   - **Args Separator** — how parameters are joined. Use `&` for standard query-string style (`?a=1&b=2`) or `/` for path style (`/value1/value2`).
   - **Timeout** — maximum seconds to wait for a response (5–120 s). If the validator does not reply in time, the result is logged as a timeout error.
   - **Optional Additional Arguments** — extra key=value pairs appended to every request (e.g. `entityid=xxx&profile=saml2`).
3. Fill in the **Response Parsing** section — these tell Jagger how to read the validator's XML response. The expected format is:
   ```xml
   <?xml version="1.0"?>
   <validation>
     <returncode>0</returncode>
     <message>Validation passed</message>
   </validation>
   ```
   Set **Code Element** to the XML tag that holds the result code (default: `returncode`) and **Message Element** to the tag with the human-readable message (default: `message`). Set the four **Value** fields to the strings the validator returns for each severity level (default: `0`/`1`/`2`/`3`).
4. Configure the three behaviour toggles:
   - **Active** — when off, this validator is skipped everywhere. The manual test button still works.
   - **Run on Registration** — when on, Jagger runs this validator automatically whenever an entity is registered, updated, or approved for the federation. Results are written to the audit log. The entity is never blocked.
   - **Mandatory** — when on, if the validator reports an error or critical result during an automatic run, a notification is sent to all federation managers.
5. Click **Save Validator** to save. Click **Save & Test** to save and immediately open the test panel for this validator.

**Testing a validator**

Click the play icon on the Validators list to open the test panel. Select any federation entity from the dropdown and click **Run Validator**. The result is shown inline: success (green), warning (yellow), error/critical (red), or connection problem (grey with the specific reason — timeout, SSL error, or unreachable).

**Editing or deleting a validator**

Use the pencil and trash icons on the Validators list. Deleting requires a confirmation prompt.

---

---

### 24. Send email to federation members

> **Permission required:** `federation.edit` (assigned FM or Admin)

**Composing and sending**

1. Open the federation's detail page and click **Send Email** in the header.
2. Optionally select a **template** from the dropdown to pre-fill the subject and body. Templates marked ★ **Custom** use this federation's own customised version; unmarked templates use the system default.
3. Choose **Recipients**: All entities, Identity Providers only, or Service Providers only. Each option shows the entity count and an approximate email count (entities × their contacts).
4. Choose which **Contact Types** receive the email (Technical, Support, Security, Administrative). At least one must be selected.
5. Edit the **Subject** and **Body**. Use `[[placeholders]]` from the sidebar (e.g. `[[entity_name]]`, `[[federation_name]]`) — they are replaced per entity when the email is sent.
6. Click **Preview** to see the rendered email using a sample entity from the federation. The preview reflects your current subject and body, not the saved template.
7. Click **Send** — a confirmation prompt shows the entity count and approximate email count. Confirm to queue the job. Emails are sent asynchronously; track results in the **Mail Log**.

**Customising federation email templates**

Each federation can override any system email template — both for manually sent emails and for automated notifications (entity approved, rejected, membership expired, etc.).

1. Click **Email Templates** in the federation header, or go to **Send Email → Manage federation templates** link.
2. The list shows all system templates. Rows marked **Custom** already have a federation override; others show **System default**.
3. Click **Customise** (or **Edit** for existing overrides) to open the edit form. The form is pre-filled with the current content — federation copy if it exists, system template otherwise.
4. Edit subject and body. Click **Preview** to see rendered output with a sample entity.
5. Click **Save Custom Template**. The custom template is used immediately for all emails from this federation — both manual sends and automated notifications.
6. To revert, click the reset icon on the templates list. The system default is restored.

**Mail log**

All sent emails are recorded — both manually triggered and automated notifications. Open **Send Email → View Mail Log** or navigate to `/federations/{slug}/mail/log`. Each row shows: recipient, entity, contact type, subject, send status, and timestamp.

---

---

### 25. Export member contact list

> **Permission required:** `federation.view`

1. Open the federation's detail page. In the **General** tab, find the **Download contacts** row.
2. Choose your filters:
   - **Entity type** — All entities, IdPs only, or SPs only.
   - **Contact type** — All contact types, or one of: Technical, Support, Security, Administrative.
   - **Format** — CSV (spreadsheet-ready, with column headers) or Plain text (human-readable).
3. Optionally tick **Deduplicate emails** — when checked, each email address appears only once in the export even if the same person is a contact for multiple entities. The first occurrence is kept.
4. Click **Download**. The file is generated immediately and saved to your browser's download folder.

**CSV format** includes headers: `Entity Type`, `Entity Name`, `Entity ID`, `Contact Type`, `Contact Name`, `Contact Email`. One row per contact.

**Plain text format** groups contacts under each entity with a separator line. The file header shows the federation name, export timestamp, and the filters applied.

Filename pattern: `contacts-{entity-type}-{contact-type}-{date}.csv` / `.txt`

---

---

### 26. Deactivate / Reactivate a federation

> **Permission required:** `federation.edit` (assigned FM or Admin)

**Deactivating**

1. Open the federation's detail page. In the header, open the **Status** dropdown and select **Inactive**, then confirm the prompt.
2. A six-step wizard opens:
   - **Step 1 — Impact**: review entities and active memberships that will be affected.
   - **Step 2 — Memberships**: choose what happens to active entity memberships (suspend all, or move to another federation).
   - **Step 3 — Notify entities**: optionally send a deactivation notice to entity contacts.
   - **Step 4 — Notify managers**: optionally send a notice to federation managers.
   - **Step 5 — Review**: confirm the summary of changes.
   - **Step 6 — Execute**: click **Deactivate** to apply. The federation status is set to Inactive, memberships are updated, notifications are queued, and an audit log entry is written.
3. An inactive federation is excluded from metadata generation and no new entities can be added to it.

**Reactivating**

1. Open the federation's detail page. In the header, open the **Status** dropdown and select **Active** and save.
2. The status returns to Active immediately. Federation managers receive an in-app notification. An audit log entry is written.
3. Entity memberships are not automatically restored — re-add entities manually if needed ([op 14](#14-add-an-entity-to-a-federation)).

---

---

### 27. Delete a federation

> **Permission required:** `federation.delete`

**Soft delete (move to trash)**

1. Open the federation's detail page and click **Delete**. Confirm the prompt.
2. The federation is soft-deleted — it no longer appears in the main list but is preserved in the database. Active metadata feeds return 404. An AuditLog entry is written and each federation manager receives an in-app notification.

**Restore from trash**

1. On the Federations list, click **Deleted federations**. Find the federation and click **Restore**.
2. The federation is restored to its previous status. Metadata generation resumes on the next cycle.

**Permanent delete (force-delete)**

1. From the Deleted federations list, click **Delete permanently** and confirm.
2. The federation record and all related data are permanently removed. Before deletion, the app calls `$driver->deleteAll($federation)` on the federation's active signing driver — this removes PEM files from disk for the file driver, or destroys the PKCS#11 token for SoftHSM2. If the driver cleanup fails (e.g. token already gone), the error is logged but force-delete continues.

---

---

### 28. Invite a new user

> **Permission required:** `invitation.manage`

1. Go to **Invitations** → click **New Invitation**.
2. Enter the recipient's **email address**, select the **federation**, and optionally select an **entity** they will co-manage.
3. Click **Send** — an invitation email is sent to the recipient immediately.
4. The recipient opens the link from the email, fills in their **name** and **password**, and is logged in immediately.
5. If the link expires before the recipient registers, open the Invitations list, find the invitation under the **Expired** tab, and click **Reissue** to generate and send a new link.

When a recipient accesses an expired, revoked, or already-used link they see an error page titled **"Invitation Link Unavailable"** with the specific reason and the contact details of the person who sent the invitation, so they know who to reach out to for a new link.

---

---

### 29. Approve / reject an access request

> **Permission required:** `invitation.manage` (FM review); `entity.requestContactInvitation` (EM submit)

**Reviewing requests (FM)**

1. Go to **Invitation Requests** in the sidebar.
2. Click **Approve** — if the contact already has an account they are added as co-manager immediately; otherwise an invitation email is sent. If an active invitation to that address already exists for the entity, a duplicate is not created.
3. Click **Reject**, enter a reason, and click **Confirm Reject**. The requester sees the reason in their My Requests view.

**Submitting and tracking a request (EM)**

1. Open the entity's detail page. Click **Request Invitation** next to a contact in the **Request Co-Manager** section. Select the federation and click **Send Request**.
2. Track status under **My Requests** in the sidebar: Pending / Approved / Rejected. Rejected requests show the FM's reason.
3. To withdraw a Pending request, click **Cancel** on the row and confirm.

---

---

### 30. Change a user's role

> **Permission required:** Admin role (enforced in controller in addition to `user.edit`)

1. Go to **Users** and open the user's detail page.
2. Click **Edit**.
3. In the **Role** card, select the new role from the dropdown and click **Apply**.
4. The change takes effect on the user's next request. The user receives an in-app notification.

Available roles: **Admin**, **Federation Manager**, **Entity Manager**, **Guest**.

Note: downgrading a Federation Manager does not automatically remove them from their federation assignments — review and remove manually if appropriate ([op 19](#19-remove-a-federation-manager)).

---

---

### 31. Suspend / reactivate a user account

> **Permission required:** Admin role (enforced in controller in addition to `user.edit`)

**Suspend**

1. Go to **Users**, open the user's detail page, and click **Edit**.
2. In the **Suspend Account** card, click **Suspend Account** and confirm.
3. The account status changes to Suspended immediately. The user cannot log in; any active session is rejected on the next request. An in-app notification is created on their account.

**Reinstate**

1. Open the suspended user's edit page.
2. The card shows **Account Suspended** with a **Reinstate Account** button. Click it — no confirmation prompt.
3. The status returns to Active immediately. The user can log in again. An in-app notification is created on their account.

You cannot suspend your own account. The suspend/reinstate card is hidden when editing your own profile.

---

### 32. Monitor certificate expiry

> **Permission required:** `entity.view`

1. Go to **Certificates** in the sidebar. The summary row shows counts for each severity level.
2. Certificates are grouped by severity: **Expired** (past expiry), **Critical** (<14 days), **Warning** (<30 days), **Advisory** (<60 days), **Info** (<90 days).
3. Each row shows the entity, certificate use, subject CN, and exact expiry date. Click the entity name to go to its detail page and update the certificate.
4. Use the **Show certificates expiring within** filter to narrow the view (14, 30, 60, or 90 days).

The scheduler sends a daily expiry digest email to Admin users when any certificate falls within the 90-day window. Notifications are rate-limited to one per certificate per calendar day.

Federation managers see only certificates for entities in their managed federations.

---

### 33. View the audit log

> **Permission required:** `user.view` (full log); all authenticated users (own entries only)

1. Go to **Audit Log** in the sidebar. Entries are shown newest-first, 50 per page.
2. Click any row to expand the before/after diff — changed fields are highlighted.
3. Filter by entity, user, action (partial match), and date range. Filters combine with AND logic.

Non-admin users see only their own entries. Federation managers and admins see the full log.

---

---

### 34. Export statistics as CSV

> **Permission required:** `compliance.view`

1. Go to **Statistics** in the sidebar and scroll to the **Data Exports** section.
2. Three exports are available:
   - **Entities** — entity_id, type, status, display_name, source, created_at
   - **Certificates** — entity_id, use, not_before, not_after, subject, issuer (ordered by expiry)
   - **Memberships** — entity_id, federation_name, membership_status, approved_at
3. Click the relevant **Export … (CSV)** button. The file downloads immediately.

---

---

### 35. Import from Jagger

> **Permission required:** `federation.create` (Admin only)

1. Go to **Import → From Jagger** in the sidebar.
2. Enter the Jagger database connection details: Host, Port, Database, Username, Password.
3. Click **Test Connection** to verify connectivity and preview entity/federation counts.
4. Choose import options:
   - **Only local entities** — skip entities sourced from external feeds (recommended).
   - **Skip existing entities** — entities already present are not overwritten.
   - **Clear registry first** — deletes all existing entities and federations before import. Irreversible.
5. Click **Run Import**. The results page shows import counts and any errors.

The import is recorded in the audit log with host, database, and import options.

---

---

### 36. Run a scheduled job immediately

> **Permission required:** `federation.create` (Admin only)

1. Go to **Scheduler** in the sidebar.
2. Find the job card for the job you want to trigger.
3. Click **Run Now**. The job is dispatched to the queue immediately.
4. The **Last run** timestamp updates once the job completes.

Available jobs: Auto-generate metadata, Validate all metadata, Certificate expiry check, Sync eduGAIN metadata, Cleanup.

Each manual trigger is recorded in the audit log.

---

---

### 37. Configure scheduler timings

> **Permission required:** `federation.create` (Admin only)

1. Go to **Scheduler** in the sidebar.
2. In the **Settings** panel, adjust interval or enabled state for each job group.
3. Click **Save Settings**. Changes take effect at the next scheduler tick.

---

---

### 38. Set system preferences

> **Permission required:** `federation.create` (Admin only)

1. Go to **Preferences** in the sidebar.
2. Edit values in the relevant category section (General, Page, Mail, Authentication, Federation, eduGAIN).
3. Click **Save Preferences**. Changes take effect immediately and are recorded in the audit log.

---

---

### 39. Manage compliance rules

> **Permission required:** `federation.create` (Admin only)

1. Go to **Compliance Rules** in the sidebar to see all registered rules with enabled/disabled state.
2. Click **Enable** or **Disable** to toggle a rule immediately. Disabled rules are excluded from compliance score calculations.
3. Click **Sync Rules from Code** after deploying new rules to register them in the database.

Each toggle is recorded in the audit log (action: `rule_toggled`).

---

---

### 40. Configure a webhook endpoint

> **Permission required:** `federation.create` (Admin only)

**Create**

1. Go to **Webhooks** in the sidebar and click **Add Endpoint**.
2. Enter the URL, select event types to subscribe to, and optionally add a description.
3. Click **Create Endpoint**. The endpoint is active immediately; copy the signing secret now.

**Verify deliveries**

1. Click an endpoint name to open its detail page.
2. The **Recent Deliveries** table shows each attempt and its HTTP status.
3. Click **Retry** on a failed delivery to re-queue it.

**Delete**

Click **Delete** on the Webhooks list. Deletion is immediate and removes all delivery history.

Webhook payloads are signed with HMAC-SHA256; the signature is in the `X-Webhook-Signature` header.

---