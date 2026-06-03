# Federation Manager — User Guide

**Version:** 1.0 — 2026-05-08
**Stack:** Laravel 13 · PHP 8.4 · Bootstrap 5 · Livewire 4

---

## Table of Contents

1. [Overview](#1-overview)
2. [Roles and Permissions](#2-roles-and-permissions)
3. [Logging In](#3-logging-in)
4. [Dashboard](#4-dashboard)
5. [Entities](#5-entities)
   - 5.1 [Browse Entities](#51-browse-entities)
   - 5.2 [Create an Entity](#52-create-an-entity)
   - 5.3 [Import an Entity](#53-import-an-entity)
   - 5.4 [Edit an Entity](#54-edit-an-entity)
   - 5.5 [Validate Entity Metadata](#55-validate-entity-metadata)
   - 5.6 [View Raw XML](#56-view-raw-xml)
   - 5.7 [Suspend and Reactivate](#57-suspend-and-reactivate)
   - 5.8 [Delete and Restore](#58-delete-and-restore)
   - 5.9 [Requested Attributes (SP)](#59-requested-attributes-sp)
   - 5.10 [Attribute Release Policy (IdP)](#510-attribute-release-policy-idp)
   - 5.11 [Compliance Rule Overrides](#511-compliance-rule-overrides)
6. [Federations](#6-federations)
   - 6.1 [Browse Federations](#61-browse-federations)
   - 6.2 [Create a Federation](#62-create-a-federation)
   - 6.3 [Federation Show Page Tabs](#63-federation-show-page-tabs)
   - 6.4 [Membership Management](#64-membership-management)
   - 6.5 [Generate and Publish Metadata](#65-generate-and-publish-metadata)
   - 6.6 [Registration Policies](#66-registration-policies)
   - 6.7 [Required Attributes](#67-required-attributes)
   - 6.8 [External Validators](#68-external-validators)
   - 6.9 [Federation Compliance Rules](#69-federation-compliance-rules)
   - 6.10 [Send Email to Members](#610-send-email-to-members)
   - 6.11 [Download Contacts](#611-download-contacts)
   - 6.12 [Federation Managers](#612-federation-managers)
   - 6.13 [Delete and Restore](#613-delete-and-restore)
   - 6.14 [Signing Keys](#614-signing-keys)
7. [Certificates](#7-certificates)
8. [Invitations](#8-invitations)
   - 8.1 [Send an Invitation](#81-send-an-invitation)
   - 8.2 [Resend, Revoke, Reissue](#82-resend-revoke-reissue)
   - 8.3 [Contact Invitation Requests](#83-contact-invitation-requests)
   - 8.4 [Accepting an Invitation](#84-accepting-an-invitation)
9. [Notifications](#9-notifications)
   - 9.1 [Notification Bell](#91-notification-bell)
   - 9.2 [Notification Preferences](#92-notification-preferences)
10. [Statistics and Reports](#10-statistics-and-reports)
11. [Webhooks](#11-webhooks)
12. [Discovery Endpoints](#12-discovery-endpoints)
13. [Import from Jagger](#13-import-from-jagger)
14. [Mail Templates](#14-mail-templates)
15. [Compliance Rules](#15-compliance-rules)
16. [Attribute Definitions](#16-attribute-definitions)
17. [eduGAIN Integration](#17-edugain-integration)
18. [Users](#18-users)
19. [Audit Log](#19-audit-log)
20. [Scheduler](#20-scheduler)
21. [System Preferences](#21-system-preferences)
22. [Language Switching](#22-language-switching)
23. [SimpleSAMLphp — Standalone Installation](#23-simplesamlphp--standalone-installation)

---

## 1. Overview

The Federation Manager is a modern SAML2 federation registry. It manages Identity Providers (IdP) and Service Providers (SP), organises them into federations, generates signed aggregate metadata XML, monitors TLS certificates, and enforces REFEDS/eduGAIN compliance rules.

It replaces Jagger (ResourceRegistry3) and adds OIDC support, real-time certificate monitoring, a webhook event system, and a self-service invitation flow.

---

## 2. Roles and Permissions

| Role | What they can do |
|------|-----------------|
| **Admin** | Full access to every feature including user management, system preferences, scheduler, and audit log |
| **Federation Manager (FM)** | Manages their assigned federations — approves/rejects entity membership, generates metadata, sends invitations, reviews co-manager requests. Cannot access system preferences or user list |
| **Entity Manager (EM)** | Creates and edits their own entities and co-managed entities. Can submit entities to federations and request co-manager access for contacts |
| **Guest** | Can create a draft entity and submit it for review. Limited to their own submissions |

**Ownership scoping:** Entity Managers can only edit entities where they appear in the entity's co-manager list. Ownership is assigned automatically on creation.

**Federation scoping:** Federation Managers see only the federations they have been explicitly assigned to by an Admin.

---

## 3. Logging In

Navigate to `/login`. Two options are presented:

### Institutional Login (SAML2)
Click **Login with institutional account**. You are redirected to your organisation's Identity Provider via SimpleSAMLphp. On first login your account is created automatically with the **Guest** role. An Admin can promote you to a higher role afterwards.

### Local Login
Enter your email address and password directly. Local accounts are created manually by Admins and are intended for federation operators who do not have an institutional SAML2 account.

### Logging Out
Click your name in the top-right corner, then **Sign out**. This terminates both the Laravel session and the SimpleSAMLphp session (triggering Single Log-Out at the IdP if supported).

---

## 4. Dashboard

URL: `/dashboard`

The dashboard gives a real-time overview of the registry:

| Card | What it shows |
|------|---------------|
| Total Entities | Count of all entities in the registry |
| Active Entities | Entities with status = active |
| Federations | Total number of federations |
| Critical Certs | Certificates expiring within 14 days |

Below the cards:
- **Recent Entities** — last 5 entities registered, with type badge and status
- **Certificate Expiry Summary** — counts by severity (expired / critical / warning / advisory)
- **Recent Audit Log** — last 5 actions taken by any user

---

## 5. Entities

### 5.1 Browse Entities

URL: `/entities`

The entity list supports live search and filtering via the **EntitySearch** Livewire component:

- **Search box** — searches entity ID, display name, description
- **Type filter** — All / IdP / SP / OIDC
- **Status filter** — All / Active / Draft / Pending / Suspended
- **Federation filter** — shows only members of a selected federation
- **Sort** — by name, type, status, or created date

Clicking a row expands an inline preview showing the entity's display name, certificates, and federation memberships. Click the entity ID to go to the full show page.

### 5.2 Create an Entity

URL: `/entities/create`

Permission required: `entity.create`

The entity form has six tabs:

| Tab | Fields |
|-----|--------|
| **General** | Entity ID (URI), type (IdP / SP / OIDC), status, registration authority, registration policies (`mdrpi:RegistrationPolicy` — per-language policy URLs), scope (IdP only), NameID formats (IdP only), SP signature options |
| **UI Info** | Display name (EN), description (EN), logo URL, information URL, privacy URL, organisation name/URL |
| **Endpoints** | SSO endpoints (IdP), ACS endpoints (SP), SLO endpoints, binding and location |
| **Contacts** | Technical, support, security, administrative contacts (name, email, phone) |
| **REFEDS** | Entity categories (R&S, CoCo v2, HFD, Anonymous, Pseudonymous, Personalized), SIRTFI (shows a green badge when a security contact is already registered), MFA profile |
| **Languages** | Additional language variants for display name, description, and other UI fields |

**OIDC tab** (visible when type = OIDC):
- Redirect URIs (one per line)
- Grant types (authorization_code, client_credentials, refresh_token)
- Scopes (space-separated, must include `openid`)
- Application type (web / native)
- Token endpoint auth method

Click **Save** at the bottom of any tab to persist changes. Each tab saves independently.

**Guests and Entity Managers** must click **Validate** before saving. The validation gate checks compliance rules and blocks submission if hard errors are present. Warnings can be acknowledged with a checkbox.

### 5.3 Import an Entity

URL: `/entities/import/xml` or `/entities/import/array`

Three import methods are available from the **Register Entity** dropdown on the entity list:

| Method | How it works |
|--------|-------------|
| **Fill form manually** | Opens the standard entity creation form |
| **Import from XML** | Paste or upload a SAML2 `<EntityDescriptor>` XML document. The system parses all fields automatically |
| **Import from JSON** | Paste a JSON object with entity fields (entity_id, type, name_en, endpoints, certificates, etc.) |

After pasting, click **Preview Import** to review the parsed data before confirming. Errors in the XML/JSON are shown inline. On confirm, the entity is created as a draft.

### 5.4 Edit an Entity

URL: `/entities/{id}/edit`

Same six-tab form as creation. Changes are saved per-tab. The breadcrumb shows the entity's display name and a link back to the show page.

**Suspend** and **Delete** buttons appear in the top-right depending on the entity's current status (see sections 5.7 and 5.8).

**Federation Invitations panel** — if any federations have sent a pending invitation for this entity to join, a blue panel appears at the top of the edit page listing each invitation with the federation name, inviting manager, and timestamp. Click **Accept** to approve the membership or **Decline** to reject it without leaving the edit page.

**Reload from XML/JSON** — a collapsible panel at the bottom of the edit page. Paste a SAML2 `<EntityDescriptor>` XML or a JSON export (or upload a file) and click **Apply**. The system parses the content and **pre-fills the form fields** for review — nothing is saved until you click Save on each tab. Federation memberships, entity status, and access settings are not affected by a reload.

### 5.5 Validate Entity Metadata

URL: `/entities/{id}/validate`

Runs the full compliance rule engine against the entity's current data. Results are grouped into three categories:

| Category | Meaning |
|----------|---------|
| **Errors** | Hard failures — entity should not be published until resolved |
| **Warnings** | Best-practice issues — entity can be published but should be reviewed |
| **Passed** | Rules that the entity satisfies |

Each rule has a rule ID badge (S01–S10 structural, C01–C05 certificate, R01–R15 REFEDS/eduGAIN, X01 XSD schema, O01–O03 OIDC). Click the **ⓘ** icon next to any rule to see its description and a link to the relevant specification.

**Re-validate** button forces a fresh run (bypassing any cached result).

**Download JSON** button returns the full validation result as a machine-readable JSON object — useful for CI pipelines.

**API access:** Send `Accept: application/json` to the same URL to get the JSON response directly.

### 5.6 View Raw XML

URL: `/entities/{id}/metadata.xml`

Returns the rendered SAML2 `<EntityDescriptor>` XML for this entity. Useful for debugging or copy-pasting into an IdP/SP configuration.

### 5.7 Suspend and Reactivate

On the entity edit page, when status = **active**:
- Click **Suspend** — opens a confirmation modal. Entity status becomes `suspended`. The entity is excluded from federation metadata until reactivated.

When status = **suspended**:
- Click **Reactivate** — restores status to `active`.
- The **Delete** button also becomes available for suspended entities.

### 5.8 Delete and Restore

Deleting an entity soft-deletes it (it is hidden from normal views but not removed from the database).

**To delete:** entity must be `suspended` or `draft`. Active entities cannot be deleted directly — suspend first.

**Trash page** (`/entities/trashed`): lists all soft-deleted entities. Actions:
- **Restore** — returns entity to its previous status
- **Delete permanently** — irreversible hard delete. Blocked if the entity has active federation memberships.

### 5.9 Requested Attributes (SP)

URL: `/entities/{id}/requested-attributes`

Visible only for SP entities. Lists the SAML attributes this SP requests from IdPs (`<md:RequestedAttribute>`).

Click **Add Attribute** to select from the global attribute definition library. Set whether the attribute is required or optional. Remove attributes with the trash button.

### 5.10 Attribute Release Policy (IdP)

URL: `/entities/{id}/arp`

Visible only for IdP entities. Defines which attributes this IdP releases to each SP it is connected to. Each row shows an SP, an attribute, and whether release is permitted or denied.

Toggle the **Release** switch per row and optionally add a note. Click **Save** per row.

### 5.11 Compliance Rule Overrides

URL: `/entities/{id}/rules`

Allows per-entity overrides of the global compliance rule configuration. You can set a rule to a different severity (error / warning / info) or disable it entirely for this specific entity.

Useful when a rule is globally required but a particular entity has a documented exception.

---

## 6. Federations

### 6.1 Browse Federations

URL: `/federations`

Lists all federations with member counts. **Federation Managers** see only their assigned federations. **Admins** see all.

Soft-deleted federation count shown at the top — click to view the trash page.

### 6.2 Create a Federation

URL: `/federations/create`

Permission required: `federation.create` (Admin only)

Fields: **Name**, **URI** (registration authority URI, must be unique), **Description**, **Status** (active / inactive).

**Signing Driver** — when more than one signing driver is enabled on this instance (e.g. both Local file and SoftHSM2), a selector appears so you can choose which driver this federation will use. When only one driver is active the field is hidden and set automatically. The driver can be changed later from the federation's **Signing Keys** tab.

### 6.3 Federation Show Page Tabs

URL: `/federations/{id}`

The federation show page is organised into tabs:

| Tab | Contents |
|-----|----------|
| **General** | Name, URI, description, status, pie chart of IdP/SP/pending counts |
| **Membership** | Pending approval table, active IdPs table, active SPs table, add-entity form |
| **Metadata** | Generate / download metadata actions, public feed URL, eduGAIN panel |
| **Signing Keys** | Upload/manage the private key and certificate used to sign this federation's metadata |
| **Attributes** | Required attribute list for this federation, add/remove |
| **Validators** | External validator list with test buttons |
| **Rules** | Per-federation compliance rule severity overrides |
| **Managers** | Assigned Federation Managers, add/remove (Admin only) |

### 6.4 Membership Management

**Adding an entity:**
On the **Membership** tab, use the **Add Entity** form. Select an entity from the dropdown and click Add. The entity is added with status `pending`.

**Approving a membership:**
In the **Pending** table, click **Approve**. The pivot status changes to `active`. If the entity was previously `draft`, it is automatically promoted to `active` as well. The entity owner is notified.

**Rejecting a membership:**
Click **Reject** and enter a rejection reason. The pivot status becomes `rejected`. The entity is not included in published metadata. The entity owner is notified with the reason.

**Removing a member:**
In the active IdP or SP table, click **Remove**. The pivot row is deleted entirely. The entity itself is not affected.

### 6.5 Generate and Publish Metadata

On the **Metadata** tab:

**Generate** — Dispatches `GenerateMetadataJob` which:
1. Collects all entities with pivot status = `active`
2. Builds an `<md:EntitiesDescriptor>` XML document
3. Includes `validUntil` attribute (configurable via Scheduler settings)
4. Signs the document using the federation's configured signing driver (see §6.14). If no key pair has been uploaded for this federation, metadata is generated **unsigned**
5. After signing, runs a structural integrity check — verifies entity count, all entityIDs are present, and `validUntil` is in the future and no more than 14 days away. If the check fails the signing job aborts and the previous cache is not replaced (see below)
6. Caches the result for 6 hours

**Post-signing integrity check** — After xmlsectool signs the document, the app validates the output before accepting it. This guard protects against silent corruption or a man-in-the-middle attack on the signing pipeline. The checks are:

| Check | What it verifies |
|-------|-----------------|
| Root element | Output is `<md:EntitiesDescriptor>`, not a different document |
| Entity count | Signed output contains the same number of `<md:EntityDescriptor>` elements as the input |
| EntityID set | Every entityID present in the input also appears in the signed output (no additions or removals) |
| validUntil | Timestamp is in the future and at most 14 days from now |

If any check fails, `GenerateMetadataJob` throws an exception and logs it to the audit log. The previously cached metadata is preserved unchanged — the federation continues to serve the last known-good signed aggregate until the issue is resolved. The error is visible in the audit log (action: `metadata_sign_failed`) and in the health check endpoint.

**Automatic cache invalidation** — The federation metadata cache is cleared automatically whenever an entity belonging to the federation is saved or when a membership is approved, rejected, or removed. The Generate action runs synchronously from the UI so the published feed reflects changes immediately without waiting for a queue worker.

**Download** — Downloads the cached XML as `{federation-name}-metadata.xml`.

**Public feed URLs** (no login required):
- `/metadata/{federation}/feed` — standard aggregate for remote federations
- `/metadata/{federation}/edugain` — eduGAIN-filtered feed (active + edugain-flagged entities only)

### 6.6 Registration Policies

URL: `/federations/{id}/policies`

Adds `<mdrpi:RegistrationPolicy>` elements to published metadata. Each policy is a language + URL pair pointing to the federation's legal registration document.

- Multiple languages supported (EN, RO, DE, FR, RU, PL, LT, LV, ET)
- Each language can appear only once per federation
- Toggle **Enabled** to include/exclude from metadata without deleting

### 6.7 Required Attributes

On the **Attributes** tab, define which SAML attributes member SPs are expected to request. This is informational — it appears in the federation show page and can be used to guide SP operators.

Select an attribute from the global library, set whether it is required or recommended, and optionally add a note.

### 6.8 External Validators

URL: `/federations/{id}/validators`

Configure external compliance services (e.g. Jagger validator, REFEDS validators) that can check entity metadata via HTTP.

| Field | Description |
|-------|-------------|
| Name | Display name |
| URL | Endpoint URL (must use HTTPS) |
| HTTP Method | GET or POST |
| Metadata argument name | Query/form parameter that receives the XML |
| Response code element | XML element in the response containing the result code |
| Response message element | XML element containing the human-readable message |
| Success / Warning / Error / Critical values | Numeric codes to map to severity |
| Enabled on registration | Run this validator automatically when an entity is submitted |

**Test button** — opens a modal, enter an entity ID, click Run. The result (success / warning / error / critical / unreachable) is shown immediately.

### 6.9 Federation Compliance Rules

URL: `/federations/{id}/rules`

Override the global compliance rule severity for all entities in this federation. For example, you can elevate R01 (DisplayName) from warning to error for your federation while keeping it as a warning globally.

### 6.10 Send Email to Members

URL: `/federations/{id}/mail`

Send a templated email to all technical/support/security/administrative contacts of member entities.

1. Select a **Mail Template** or compose a custom subject and body
2. Choose entity type filter (All / IdP / SP)
3. Choose contact types to include (technical, support, security, administrative)
4. Click **Send** — email delivery is queued and processed asynchronously

**Mail Log** (`/federations/{id}/mail/log`) — shows all sent messages with status (sent / failed / pending), recipient, and timestamp.

### 6.11 Download Contacts

On the **General** tab, click **Download Contacts**. A plain-text contact sheet is generated with one block per entity, listing all contacts by type. Filter by entity type (All / IdP / SP).

### 6.12 Federation Managers

On the **Managers** tab (Admin only):

**Add Manager** — select a user who has the Federation Manager role. They are assigned to this federation and will see it in their federation list.

**Remove** — removes the assignment. The user's role is not changed, but they will no longer see this federation.

A user can be manager of multiple federations.

### 6.13 Delete and Restore

Federations with `status = active` cannot be deleted. Set status to `inactive` first.

**Trash page** (`/federations/trashed`):
- **Restore** — returns the federation to active state
- **Delete permanently** — blocked if the federation still has entity members

### 6.14 Signing Keys

URL: `/federations/{slug}` → **Signing Keys** tab

Permission required: `federation.edit` (FM or Admin)

Each federation signs its metadata aggregate with its own dedicated private key and certificate. The **Signing Keys** tab is where the key pair is managed. If no key pair is uploaded, metadata is generated unsigned and a warning is shown on the Metadata tab.

#### Status cards

Two cards show the current state of the key and certificate:

| State | Card colour | Badge |
|-------|-------------|-------|
| Not uploaded | Grey border | Not uploaded |
| Uploaded | Green border | Stored |
| Certificate expiring (≤30 days) | Amber border | Expires in N days |
| Certificate expired | Red border | Expired |

Click the **ⓘ** info button on either card to open a modal with full credential details:
- **Key info modal** — key type (RSA / EC) and bit length
- **Certificate info modal** — subject, issuer, serial number, validity period with coloured expiry badge, full PEM text, and a **Download .crt** button

#### Upload wizard (file driver)

Uploading follows a two-step wizard. The first file uploaded is held in memory — not stored until the pair is validated.

**Step 1 — Upload first credential**

Select and upload a file. The app auto-detects the format:

| Format | What happens |
|--------|-------------|
| PKCS#12 bundle (`.p12`, `.pfx`) | Both key and certificate extracted and stored in one step |
| PEM with both key and cert | Both extracted and stored in one step |
| PEM private key only | Key is held in memory; wizard advances to Step 2 asking for the certificate |
| PEM certificate only | Certificate is held in memory; wizard advances to Step 2 asking for the private key |

**Step 2 — Upload companion credential**

Upload the matching companion (key or certificate, whichever is missing). The app verifies the key and certificate form a matching pair before storing either. If they do not match, an error is shown and you can retry Step 2 without losing the Step 1 credential. On success both are stored atomically.

#### SoftHSM2 driver (when enabled)

When the federation's driver is **SoftHSM2**, the Signing Keys tab shows a different panel:

- **Token status card** — shows whether a PKCS#11 token has been initialised for this federation, with the token label
- **PIN status** — confirms whether `JAGGER_HSM_PIN` is set in the server environment (required for signing)
- **Security warning** — reminds operators that anyone with root access and the PIN value can sign metadata directly via xmlsectool, bypassing application audit controls. Monitor the audit log for unexpected signing entries.
- The upload wizard is identical to the file driver — the app stores the key inside the PKCS#11 token rather than on disk

#### Migrating from file driver to SoftHSM2

When `SOFTHSM_SIGNING_IS_ACTIVE=true` is set on the server and the federation has a complete key pair under the file driver (`signing_driver = file`), a **Migrate to SoftHSM2** panel appears at the bottom of the Signing Keys tab.

**Pre-flight checks**

Before the wizard can run, six conditions must be satisfied:

| Check | What is verified |
|-------|-----------------|
| Shell execution | `exec()` and `proc_open()` are not blocked in `php.ini` |
| softhsm2-util | Binary is installed and on the PATH |
| pkcs11-tool | Binary is installed and on the PATH |
| JAGGER_HSM_PIN | The token PIN is set in the server environment |
| SOFTHSM2_CONF | The SoftHSM2 configuration file is readable |
| PKCS11_LIBRARY | The PKCS#11 shared library (`libsofthsm2.so`) exists |

Each check shows a green tick or a red cross. All six must be green before the wizard button is enabled.

**Manual CLI steps**

Regardless of the pre-flight result, a collapsible **Manual CLI migration steps** block is always visible. It contains the complete shell commands pre-filled with the correct token label, file paths, and library path for this specific federation. Use this if you prefer to run the migration yourself or if `exec()` is not available to the web process.

**Running the wizard**

1. Optionally tick **Delete PEM files from disk after migration** if you want the source PEM files removed on success.
2. Click **Migrate to SoftHSM2** and confirm the prompt.
3. The wizard reads the PEM files from disk, imports the private key into a new SoftHSM2 token, imports the certificate as a PKCS#11 cert object, and switches the federation's signing driver to `softhsm`.
4. A step-by-step log is shown as each action completes.
5. On success a green banner appears and the panel switches to the SoftHSM2 view. The migration is recorded in the audit log.
6. If any step fails, the log up to the failure point is shown with a **Try again** button. Correct the underlying issue (check `storage/logs/laravel.log`) and retry, or use the manual CLI steps.

> The existing certificate is reused after migration — no change to published metadata or relying party configuration is needed.

#### Removing credentials

Click **Remove Key Pair** at the bottom of the tab to delete both the private key and certificate in a single operation. For file driver federations this removes the files from disk. For SoftHSM2 federations this destroys the PKCS#11 token. After removal, the status cards return to the "Not uploaded" state.

---

## 7. Certificates

URL: `/certificates/monitor`

The certificate monitoring dashboard shows all entity certificates grouped by expiry severity:

| Severity | Threshold |
|----------|-----------|
| Expired | `not_after` < now |
| Critical | ≤ 14 days remaining |
| Warning | ≤ 30 days remaining |
| Advisory | ≤ 60 days remaining |
| Info | ≤ 90 days remaining |
| Healthy | > 90 days remaining |

Click a severity card to filter the table to that group. The table shows entity name, certificate use (signing / encryption), subject, expiry date, and days remaining. Rows are colour-coded by severity.

**Filters:** severity, federation, entity type.

**Send Notifications** button (Admin only) — manually triggers expiry notification emails to entity technical contacts. The scheduler runs this automatically each day.

**API:** Send `Accept: application/json` to get a structured expiry report.

---

## 8. Invitations

### 8.1 Send an Invitation

URL: `/invitations`

Permission required: `invitation.manage` (Admin or Federation Manager)

Click **New Invitation**. Fill in:
- **Email** — recipient's email address
- **Federation** — which federation this invitation is for
- **Entity** *(optional)* — if set, the registrant will be added as co-manager of this entity upon registration

The invitation email is sent immediately. A unique token-based registration link is included. The default expiry is 72 hours (configurable in System Preferences under `invitation_expiry_hours`).

**Federation Managers** see only invitations for their assigned federations.

### 8.2 Resend, Revoke, Reissue

On the Invitations page, the **Pending** tab shows active invitations with actions:

| Action | When available | What it does |
|--------|---------------|-------------|
| **Copy URL** | Always | Copies the registration link to clipboard |
| **Resend** | Pending only | Re-sends the invitation email with the same token |
| **Revoke** | Pending only | Marks invitation as revoked — link becomes invalid |
| **Reissue** | Expired or Revoked | Creates a new token; revoked invitations require a comment |

**Tabs:** Pending · Accepted · Expired · Revoked

### 8.3 Contact Invitation Requests

Entity Managers can request that federation managers send invitations to people listed in an entity's contact records.

**As Entity Manager:**
1. Open the entity show page
2. In the **Request Co-Manager** section, click **Request Invitation** next to a contact
3. The request is sent to the Federation Manager for review

**As Federation Manager:**
1. Open `/invitation-requests`
2. Review each pending request
3. Two approval paths:
   - **Approve** — if the email belongs to an existing app user, they are added directly as co-manager. If not, an invitation is created and sent.
   - **Add Directly** — shortcut when the user already has an account
   - **Reject** — requires a rejection reason, which is shown to the Entity Manager

### 8.4 Accepting an Invitation

The invitation link (`/register/{token}`) is public and requires no prior login.

The page shows:
- Which federation you are joining
- Which entity you will co-manage (if applicable)
- Your email address (pre-filled, read-only)

Fill in your name and choose a password. On submit:
- Your account is created with the **Guest** role (or **Entity Manager** if an entity was attached to the invitation)
- You are logged in immediately and redirected to the dashboard
- An entity co-manager record is created if applicable

Expired, revoked, or already-used links show a clear error page with the federation name for contact.

---

## 9. Notifications

### 9.1 Notification Bell

The bell icon in the top navigation bar shows your unread notification count. Click it to see the last 5 unread notifications. Click **View all** to go to the full notifications page.

**Notifications page** (`/notifications`):

| Column | Description |
|--------|-------------|
| Type | Notification category (entity approved, cert expiring, etc.) |
| Title | Short summary |
| Body | Detail (truncated) |
| Received | Timestamp |
| Status | Read / Unread badge |

Actions per notification:
- **Mark read** — clears the unread indicator
- **Archive** — moves to the archive (removed from main view)
- **Go to** — navigates to the related entity or federation (if applicable)

**Mark All Read** button at the top clears all unread indicators at once.

**Archive page** (`/notifications/archive`) — read-only list of archived notifications.

Events that generate notifications:

| Event | Who is notified |
|-------|----------------|
| Entity pending approval | Federation Managers |
| Entity approved | Entity owner |
| Entity rejected | Entity owner (with reason) |
| Entity suspended | Entity owner |
| Certificate expiring | Entity owner |
| Certificate expired | Entity owner |
| User registered | Admins |
| Invitation request created | Federation Managers |
| Invitation request approved | Entity Manager who requested |
| Invitation request rejected | Entity Manager who requested |
| Metadata generated | Federation Managers |
| Federation deactivated | Admins |

### 9.2 Notification Preferences

URL: `/profile/notifications`

Each user can configure how they receive each notification type:

| Toggle | Effect |
|--------|--------|
| **In-app** | Show in the notification bell and `/notifications` page |
| **Email** | Send an email (only available for types that support email delivery) |

Changes take effect immediately for future notifications.

---

## 10. Statistics and Reports

URL: `/statistics`

Permission required: `compliance.view` (Admin and Federation Manager)

Four charts:

| Chart | Type | Description |
|-------|------|-------------|
| Entity registration trend | Bar | Monthly registration count over 12 months |
| Compliance score | Line | Average validation pass-rate per month |
| Certificate expiry forecast | Bar | Certs expiring per 30-day window, next 6 months |
| Federation active members | Horizontal bar | Active member count per federation |

Four stat cards: Total Entities · Active · Pending · Critical Certs.

**CSV Exports:**

| Export | URL | Contents |
|--------|-----|----------|
| Entities | `/statistics/export/entities` | entity_id, type, status, display_name, source, created_at |
| Certificates | `/statistics/export/certificates` | entity_id, entity_name, use, subject, valid_from, valid_until, days_remaining |
| Memberships | `/statistics/export/memberships` | federation, entity_id, entity_type, pivot_status, approved_at, approved_by |

---

## 11. Webhooks

URL: `/webhooks`

Permission required: `federation.edit` (Admin and Federation Manager)

Webhooks deliver HTTP POST notifications to external systems when key events occur.

### Creating a Webhook Endpoint

Click **New Webhook**. Fill in:

| Field | Description |
|-------|-------------|
| URL | HTTPS endpoint that will receive events |
| Events | Checkboxes for which event types to subscribe to |
| Active | Enable/disable without deleting |

A **secret** is auto-generated on creation and shown once. Store it securely — it cannot be retrieved again. Use the **Regenerate** button to rotate it.

### Event Types

- `entity.created`
- `entity.approved`
- `entity.rejected`
- `entity.suspended`
- `federation.entity_added`
- `federation.entity_removed`
- `metadata.generated`

### Payload and Signature

Each delivery sends a JSON body with event name and relevant data. The request includes the header:

```
X-Hub-Signature-256: sha256=<hmac>
```

Verify with HMAC-SHA256 of the raw request body using your endpoint secret. This follows the GitHub webhook signature convention.

### Delivery History

The webhook show page lists all delivery attempts with status (delivered / failed / pending), HTTP response code, and timestamp.

**Retry** button re-dispatches a failed delivery immediately. Failed deliveries are automatically retried with exponential backoff (1 min, 2 min, 5 min, 10 min, 30 min).

---

## 12. Discovery Endpoints

These public endpoints require no authentication and are consumed by SeamlessAccess, DiscoJuice, WAYF, and other discovery services.

### WebFinger (RFC 7033)

```
GET /.well-known/webfinger?resource={entityID}
```

Returns JSON with a link to the SAML2 metadata for the given entity. Returns 404 if the entity is not found or not active.

Example response:
```json
{
  "subject": "https://idp.example.com/shibboleth",
  "links": [{
    "rel": "urn:oasis:names:tc:SAML:2.0:metadata",
    "href": "https://registry.example.com/metadata/1/feed"
  }]
}
```

### JEDI JSON Discovery

```
GET /api/discovery/entities?type=idp&federation={uri}&q={search}&per_page=100
```

Returns a JSON array of entities in JEDI format (entityID, displayNames, descriptions, logos, informationURLs, registrationAuthority). Used by SeamlessAccess for rendering the institution picker.

Query parameters:
- `type` — `idp`, `sp`, or omit for all
- `federation` — filter by federation URI
- `q` — search display names
- `per_page` — max 100, default 100

### OIDC Configuration

```
GET /api/entities/{entity}/oidc-configuration
```

Returns RFC 7591 client registration JSON for an OIDC entity. Returns 404 for SAML entities. Used by OIDC federation operators to auto-configure Relying Party clients.

---

## 13. Import from Jagger

URL: `/import/jagger`

Permission required: Admin only. Requires `JAGGER_IMPORT_ENABLED=true` in `.env`.

This tool migrates federations and entities from a legacy **Jagger (ResourceRegistry3)** database directly into this system via a live database connection. The import is fully idempotent — safe to re-run.

### Connection Fields

| Field | Description |
|-------|-------------|
| Host | Hostname or IP of the Jagger MySQL server |
| Port | MySQL port (default: 3306) |
| Database | Jagger database name |
| Username / Password | MySQL credentials with read access |

### Options

- **Only local entities** — imports only entities flagged as local in Jagger (excludes externally sourced entities)
- **Skip existing** — skips entities whose `entityID` already exists in this system

### Import Phases

1. Federations
2. Entities + UI info (display name, description, logo, URLs)
3. Certificates
4. Endpoints (SSO, ACS, SLO)
5. Contacts
6. Attributes
7. Entity–federation relationships

Each phase is fault-tolerant — missing tables or unmapped values produce null results, not exceptions. The results page shows a per-phase summary of created / updated / skipped / errors.

### CLI Alternative

```
php artisan jagger:import --host=old-db.example.com --database=jagger
php artisan jagger:import --dry-run
```

---

## 14. Mail Templates

URL: `/mail/templates`

Permission required: `federation.edit`

Mail templates define reusable email subjects and bodies with `[[placeholder]]` substitution.

### Placeholders

| Placeholder | Replaced with |
|-------------|--------------|
| `[[entity_name]]` | Entity display name |
| `[[entity_id]]` | Entity ID URI |
| `[[contact_name]]` | Contact's full name |
| `[[contact_email]]` | Contact's email address |
| `[[federation_name]]` | Federation name |
| `[[federation_uri]]` | Federation URI |
| `[[mail_signature]]` | Value of `mail_signature` system preference |
| `[[expiry_date]]` | Certificate expiry date |
| `[[days_remaining]]` | Days until certificate expires |

On the create/edit form, click any placeholder badge to insert it at the cursor position in the subject or body field.

**Preview** button renders the template with sample data as plain text.

### Groups

Templates are organised into groups: `general`, `certificate`, `invitation`, `approval`.

---

## 15. Compliance Rules

URL: `/rules`

Lists all validation rules in the system with their IDs, names, current severity, and enabled status.

**Toggle** — enable or disable a rule globally.
**Sync** — re-registers all rules from the PHP codebase (run after deploying a code update that adds new rules).

Rule severity can be overridden at three levels:
1. **Global** — `/rules` — applies everywhere
2. **Federation** — `/federations/{id}/rules` — applies to all entities in this federation
3. **Entity** — `/entities/{id}/rules` — applies to this entity only

The most specific override wins.

### Rule Groups

| Group | IDs | What they check |
|-------|-----|-----------------|
| Structural | S01–S10 | entityID URI validity, uniqueness, role descriptors, endpoints, HTTPS, bindings, ACS index uniqueness, protocol support |
| Certificate | C01–C05 | Certificate present, key size ≥2048 bits, not expired, not Debian weak key, SHA-256 or stronger |
| REFEDS/eduGAIN | R01–R15 | DisplayName, Description, Organisation, contacts, SIRTFI security contact, CoCo privacy URL, R&S URI canonical form, scope, RegistrationInfo, WantAssertionsSigned, WantAuthnRequestsSigned |
| XSD Schema | X01 | Validates XML against the official SAML2 metadata XSD schema |
| OIDC | O01–O03 | Redirect URI HTTPS scheme, grant type subset, openid scope present |

### Rule Reference

| ID | Name | Default severity | Applies to |
|----|------|:----------------:|:----------:|
| **S01** | EntityID is a valid URI | error | IdP, SP |
| **S02** | EntityID is unique across the registry | error | IdP, SP |
| **S03** | At least one role descriptor with endpoint | error | IdP, SP |
| **S04** | protocolSupportEnumeration maps to SAML2 | error | IdP, SP |
| **S05** | At least one X.509 certificate present | error | IdP, SP |
| **S06** | IdP has at least one SSO endpoint | error | IdP |
| **S07** | SP has at least one ACS endpoint | error | SP |
| **S08** | ACS endpoint index values are unique | error | SP |
| **S09** | All endpoint binding URIs are valid SAML2 identifiers | error | IdP, SP |
| **S10** | All endpoint Location values use HTTPS | error | IdP, SP |
| **C01** | At least one valid X.509 PEM certificate present | error | IdP, SP |
| **C02** | Certificate key size meets minimum requirements (≥2048 bit) | error | IdP, SP |
| **C03** | Certificate is not expired | error | IdP, SP |
| **C04** | Certificate is not a Debian weak key (CVE-2008-0166) | error | IdP, SP |
| **C05** | Certificate uses SHA-256 or stronger signature algorithm | warning | IdP, SP |
| **R01** | mdui:DisplayName in English present | warning | IdP, SP |
| **R02** | mdui:Description in English present | warning | IdP, SP |
| **R03** | md:OrganizationName in English present | warning | IdP, SP |
| **R04** | md:OrganizationDisplayName in English present | warning | IdP, SP |
| **R05** | md:OrganizationURL in English present | warning | IdP, SP |
| **R06** | At least one md:ContactPerson (technical or support) | warning | IdP, SP |
| **R07** | SIRTFI: security contact present when SIRTFI asserted | error | IdP, SP |
| **R08** | CoCo v2: privacy statement URL present when CoCo asserted | error | IdP, SP |
| **R09** | R&S entity category URI is the canonical REFEDS URI | error | IdP, SP |
| **R10** | shibmd:Scope present for IdP | warning | IdP |
| **R11** | Scope matches entityID domain | warning | IdP |
| **R12** | mdrpi:RegistrationInfo with registrationAuthority present | error | IdP, SP |
| **R13** | SP WantAssertionsSigned=true | warning | SP |
| **R14** | SP AuthnRequestsSigned=true | warning | SP |
| **R15** | NameIDFormat does not include unspecified without reason | warning | IdP, SP |
| **X01** | Metadata passes SAML2 schema validation | error | IdP, SP |
| **O01** | OIDC redirect URI uses HTTPS | error | OIDC |
| **O02** | OIDC grant type is a valid subset | error | OIDC |
| **O03** | OIDC scope contains openid | error | OIDC |

---

## 16. Attribute Definitions

URL: `/attributes`

The global library of SAML attributes. Used across the system for SP requested attributes and IdP ARP configuration.

Each definition has:
- **Name** — machine-readable identifier (e.g. `eduPersonPrincipalName`)
- **Friendly name** — human label (e.g. `eduPerson Principal Name`)
- **OID** — URN OID (e.g. `urn:oid:1.3.6.1.4.1.5923.1.1.1.7`)
- **URN** — MACE URN (e.g. `urn:mace:dir:attribute-def:eduPersonEntitlement`)
- **Description** — what the attribute represents
- **Active** — inactive attributes are hidden from selection dropdowns but not deleted

**Deactivate vs Delete:** Deleting an attribute that is in use is blocked — the system deactivates it instead.

---

## 17. eduGAIN Integration

### Entity eduGAIN Status

On any entity show page, an **eduGAIN Status** card (when enabled) shows:

- **Presence** — whether the entity appears in the eduGAIN central metadata
- **ECCS Status** (IdP only) — result of the eduGAIN Connectivity Check Service
- **External tool links** — links to REFEDS Metadata Explorer, ECCS report, etc.

Enable via System Preferences: `edugain_checks_enabled = true`.

### Federation eduGAIN Status

On the federation show page **Metadata** tab, an eduGAIN panel shows entity counts (total / IdP / SP) as reported by the eduGAIN central registry for your federation code.

Configure `edugain_federation_code` in System Preferences.

### Certificate requirement for eduGAIN feed

Entities without at least one registered X.509 certificate are automatically excluded from the `/metadata/{federation}/edugain` feed, regardless of their `edugain` flag setting. A red **No certificates — excluded from feed** badge is shown on the entity show page, and an inline warning appears in the entity form when the eduGAIN flag is enabled. Add a certificate to the entity to restore inclusion.

### eduGAIN Upstream Sync

The scheduler runs `SyncEduGainMetadataJob` on a configurable interval. It:
1. Fetches the full eduGAIN aggregate XML (`edugain_metadata_url` in Scheduler settings)
2. Parses each `<EntityDescriptor>` element
3. Creates or updates entity records with `source = edugain`
4. Marks entities no longer present in the feed as `status = inactive`

---

## 18. Users

URL: `/users`

Permission required: `user.view` (Admin only)

The user list shows name, email, role badge, status, last login date, and join date. Search and filter by role and status.

### Change Role

Click the role dropdown on the user list or the user edit page to promote or demote a user. You cannot change your own role.

### Suspend / Reinstate

Clicking **Suspend** prevents a user from logging in. Their sessions are not terminated immediately but new logins are blocked. Click **Reinstate** to restore access. You cannot suspend yourself.

### User Show Page

Shows the user's profile, role, status, last login, and their last 10 audit log entries with a link to the full audit log filtered to that user.

---

## 19. Audit Log

URL: `/audit`

Permission required: `user.view` (Admin only)

Every significant action in the system is recorded: entity create/update/delete, federation changes, membership approvals, user role changes, preference updates, and more.

**Filters:** entity, user, action type, date range.

**Expandable diff rows** — click any row to expand and see the before/after JSON diff of what changed.

---

## 20. Scheduler

URL: `/scheduler`

Permission required: `federation.create` (Admin only)

All scheduled job timings and thresholds are configurable here — no code changes required. The scheduler requires a system cron entry: `* * * * * php artisan schedule:run`. Each job group shows its last run time and has a **Run Now** button for immediate manual execution.

### Metadata

| Setting | Default | Purpose |
|---------|---------|---------|
| `metadata_auto_generate_enabled` | Off | Automatically regenerate metadata for all active federations on the configured interval |
| `metadata_auto_generate_interval` | 60 min | How often to regenerate. Lower = fresher metadata but higher CPU/signing load |
| `metadata_valid_until_hours` | 6 h | Sets `validUntil` on the `<md:EntitiesDescriptor>`. Must be greater than the generation interval (recommend 2–4×) |
| `metadata_cache_duration_hours` | 6 h | How long generated XML is kept in Redis. On cache miss, metadata is regenerated on-the-fly |

### Validation

| Setting | Default | Purpose |
|---------|---------|---------|
| `validation_auto_enabled` | Off | Run full bulk re-validation of all entities on the configured schedule |
| `validation_schedule_day` | 0 (Sunday) | Day of week to run validation (0=Sunday … 6=Saturday) |
| `validation_schedule_time` | 03:00 | Time of day (24 h, server timezone) — schedule during off-peak hours |

### Certificates

| Setting | Default | Purpose |
|---------|---------|---------|
| `cert_check_enabled` | On | Run daily check and send expiry notification emails |
| `cert_check_time` | 08:00 | Time to run the check — choose working hours so recipients see alerts promptly |
| `cert_notify_days_critical` | 14 | ≤ N days → Critical badge + urgent email |
| `cert_notify_days_warning` | 30 | ≤ N days → Warning badge + renewal reminder email |
| `cert_notify_days_advisory` | 60 | ≤ N days → Advisory badge + informational notice email |
| `cert_notify_days_info` | 90 | ≤ N days → shown in Info bucket on monitor page (no email) |

### eduGAIN

| Setting | Default | Purpose |
|---------|---------|---------|
| `edugain_sync_enabled` | Off | Periodically fetch the eduGAIN aggregate XML and sync entities |
| `edugain_sync_interval_hours` | 24 | Fetch frequency. eduGAIN MDS updates ~every 2 h; daily is sufficient |
| `edugain_metadata_url` | `https://mds.edugain.org/edugain-v2.xml` | URL of the upstream eduGAIN aggregate feed |

### Cleanup

| Setting | Default | Purpose |
|---------|---------|---------|
| `cleanup_enabled` | On | Master switch — when off, nothing is pruned |
| `cleanup_metadata_days` | 7 | Remove cached metadata XML files older than N days |
| `cleanup_validation_days` | 90 | Delete entity validation results older than N days |
| `cleanup_audit_days` | 365 | Remove audit log entries older than N days (one year satisfies most compliance requirements) |

---

## 21. System Preferences

URL: `/preferences`

Permission required: `federation.create` (Admin only)

Application-wide configuration stored in the database. Every change is logged to the audit log.

### General

| Setting | Default | Purpose |
|---------|---------|---------|
| `app_name` | Federation Manager | Instance name used in emails and the browser tab title |
| `app_url` | from APP_URL | Canonical base URL — used in absolute links in invitation emails and notifications |
| `federation_name` | My Federation | Short name of the primary federation; used in email templates via `[[federation_name]]` |
| `support_email` | *(empty)* | Contact address shown to users on the login page and in error messages |
| `cookie_consent_enabled` | Off | Show a GDPR consent banner on first visit |
| `cookie_consent_text` | session cookies text | Message shown in the consent banner |
| `supported_languages` | en,ro | Comma-separated language codes in the language switcher; each must have a `lang/{code}/` file |
| `default_language` | en | Language used when no user preference is saved |

### Page

| Setting | Default | Purpose |
|---------|---------|---------|
| `header_title_prefix` | *(empty)* | Prepended to every browser tab title (e.g. `NREN` → "NREN Dashboard") |
| `footer_text` | *(empty)* | Additional text in the page footer — organisation name, copyright, privacy policy link |

### Mail

| Setting | Default | Purpose |
|---------|---------|---------|
| `mail_from_name` | Federation Manager | Display name in the `From:` header of all outbound emails |
| `mail_from_address` | from MAIL_FROM_ADDRESS | Sender address — must be authorised by SPF/DKIM on your mail server |
| `mail_signature` | app name + URL | Appended to all notification and federation emails via `[[mail_signature]]` placeholder |

### Authentication

| Setting | Default | Purpose |
|---------|---------|---------|
| `default_saml_role` | Guest | Role assigned on first SAML2 login. Guest is safest; set to Entity Manager for open self-service registries |
| `allow_self_registration` | Off | Allow local (email/password) account creation without Admin intervention |
| `session_timeout_minutes` | 120 | Idle session lifetime in minutes (5–1440). Balance security vs. convenience |
| `max_login_attempts` | 5 | Failed password attempts before temporary lockout (3–20). Lower = better brute-force protection |

### eduGAIN

| Setting | Default | Purpose |
|---------|---------|---------|
| `edugain_checks_enabled` | Off | Master switch for eduGAIN status cards on entity and federation pages |
| `edugain_federation_code` | LEAF | Your federation's code in the eduGAIN MDS (e.g. HAKA, IDEM, AAF) |
| `eccs_check_enabled` | On | Show ECCS connectivity check result for IdPs — verifies they consume eduGAIN SP metadata |
| `edugain_entity_check_enabled` | On | Show whether each entity is present in the eduGAIN central registry |

---

## 22. Language Switching

The language switcher appears in the top navigation bar. Click the current language code (e.g. **EN**) to open a dropdown showing all supported languages. Click a language to switch — the change is immediate and persisted to your user profile for future sessions.

Currently supported: English (EN), Romanian (RO).

Adding a new language requires adding a translation file (`lang/{code}/app.php`) and listing the code in the `supported_languages` system preference.

---

## 23. SimpleSAMLphp — Standalone Installation

This section covers deploying SimpleSAMLphp (SSP) as a separate application alongside Federation Manager and wiring the two together. Use this when you want SSP managed independently from the Laravel project (recommended for production).

> For the full technical reference (Option B — Composer package, troubleshooting, security checklist) see [simplesamlphp-deployment.md](simplesamlphp-deployment.md).

### Overview

SimpleSAMLphp handles the entire SAML2 protocol. Federation Manager never processes SAML XML — it reads the authenticated session that SSP writes after a successful IdP login.

```
Browser → Nginx → /simplesaml/  → SimpleSAMLphp (validates SAMLResponse, writes session)
                → /             → Federation Manager (reads session, logs user in)
```

### Prerequisites

- PHP 8.4, Composer 2.x, Nginx, OpenSSL already installed
- Your IdP's SSO URL, SLO URL, and public X.509 certificate
- Federation Manager already running (install.md steps 1–7 complete)

---

### Step 1 — Install SimpleSAMLphp

```bash
cd /var/www
composer create-project simplesamlphp/simplesamlphp simplesaml --no-dev
```

Directory layout after install:

```
/var/www/
  federations-manager/   ← Federation Manager (Laravel)
  simplesaml/            ← SimpleSAMLphp
    public/
    config/
    metadata/
    cert/
```

---

### Step 2 — Generate the SP certificate

```bash
cd /var/www/simplesaml/cert

openssl req -x509 -newkey rsa:4096 \
  -keyout sp.key -out sp.crt \
  -days 3650 -nodes \
  -subj "/CN=registry.example.com"

chmod 600 sp.key
chown www-data:www-data sp.key sp.crt
```

---

### Step 3 — Configure SimpleSAMLphp

Edit `/var/www/simplesaml/config/config.php`:

```php
$config = [
    'baseurlpath'    => 'https://registry.example.com/simplesaml/',
    'certdir'        => '/var/www/simplesaml/cert/',
    'loggingdir'     => '/var/www/simplesaml/log/',
    'datadir'        => '/var/www/simplesaml/data/',

    // Must share session with Laravel
    'store.type'              => 'phpsession',
    'session.cookie.name'     => 'laravel_session',   // must match SESSION_COOKIE in .env
    'session.cookie.path'     => '/',
    'session.cookie.domain'   => 'registry.example.com',
    'session.cookie.secure'   => true,
    'session.cookie.httponly' => true,
    'session.cookie.samesite' => 'Lax',

    'secretsalt'         => 'CHANGE_THIS_TO_RANDOM_STRING',
    'auth.adminpassword' => 'CHANGE_THIS_ADMIN_PASSWORD',

    'logging.level'   => SimpleSAML\Logger::NOTICE,
    'logging.handler' => 'file',
];
```

---

### Step 4 — Configure the SP authsource

Edit `/var/www/simplesaml/config/authsources.php`:

```php
$config = [
    'federation-sp' => [
        'saml:SP',
        'entityID'             => 'https://registry.example.com/saml2/metadata',
        'idp'                  => 'https://your-idp.example.org/idp',
        'privatekey'           => 'sp.key',
        'certificate'          => 'sp.crt',
        'sign.authnrequest'    => true,
        'WantAssertionsSigned' => true,
        'NameIDPolicy'         => [
            'Format'      => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
            'AllowCreate' => true,
        ],
        'attributes' => [
            'urn:oid:1.3.6.1.4.1.5923.1.1.1.6',  // eduPersonPrincipalName
            'urn:oid:0.9.2342.19200300.100.1.3',   // mail
            'urn:oid:2.5.4.42',                     // givenName
            'urn:oid:2.5.4.4',                      // sn
            'urn:oid:2.16.840.1.113730.3.1.241',    // displayName
        ],
    ],
];
```

---

### Step 5 — Add IdP metadata

Edit `/var/www/simplesaml/metadata/saml20-idp-remote.php`:

```php
$metadata['https://your-idp.example.org/idp'] = [
    'SingleSignOnService' => [[
        'Binding'  => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        'Location' => 'https://your-idp.example.org/idp/SSO/Redirect',
    ]],
    'SingleLogoutService' => [[
        'Binding'  => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        'Location' => 'https://your-idp.example.org/idp/SLO/Redirect',
    ]],
    'certData' => 'BASE64_ENCODED_IDP_CERTIFICATE_NO_HEADERS',
];
```

Get the IdP certificate from your federation's metadata or IdP administrator. Strip the `-----BEGIN CERTIFICATE-----` / `-----END CERTIFICATE-----` headers and pass the bare Base64 string.

---

### Step 6 — Configure Nginx

Add the `/simplesaml/` location block to your existing Federation Manager server block:

```nginx
# SimpleSAMLphp — standalone at /var/www/simplesaml/
location ^~ /simplesaml/ {
    alias /var/www/simplesaml/public/;

    location ~ \.php(/|$) {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass  unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/simplesaml/public$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        include       fastcgi_params;
    }

    location ~* \.(js|css|png|jpg|gif|ico|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public";
    }
}
```

Reload Nginx: `sudo nginx -t && sudo systemctl reload nginx`

---

### Step 7 — Install SSP PHP library in Federation Manager

Laravel uses the SSP PHP API to read the session. The library must be present inside the Laravel project even though SSP itself runs separately:

```bash
cd /var/www/federations-manager
composer require simplesamlphp/simplesamlphp
```

---

### Step 8 — Configure Federation Manager .env

Add to `/var/www/federations-manager/.env`:

```ini
# Session — must match SSP config.php session.cookie.name
SESSION_DRIVER=file
SESSION_COOKIE=laravel_session
SESSION_DOMAIN=registry.example.com
SESSION_SECURE_COOKIE=true

# SimpleSAMLphp — point to the standalone config directory
SIMPLESAMLPHP_CONFIG_DIR=/var/www/simplesaml/config

# SAML2 SP / IdP
SAML2_SP_ENTITY_ID=https://registry.example.com/saml2/metadata
SAML2_AUTH_SOURCE=federation-sp
SAML2_BASEURLPATH=/simplesaml/

SAML2_IDP_ENTITY_ID=https://your-idp.example.org/idp
SAML2_IDP_SSO_URL=https://your-idp.example.org/idp/SSO/Redirect
SAML2_IDP_SLS_URL=https://your-idp.example.org/idp/SLO/Redirect
SAML2_IDP_CERT=BASE64_IDP_CERT_NO_HEADERS
```

---

### Step 9 — Set permissions

```bash
chown -R www-data:www-data /var/www/simplesaml
chmod -R 775 /var/www/simplesaml/log
chmod -R 775 /var/www/simplesaml/data
chmod    600 /var/www/simplesaml/cert/sp.key

# Laravel session directory
chmod -R 775 /var/www/federations-manager/storage/framework/sessions
```

---

### Step 10 — Register SP metadata with your IdP

Retrieve your SP metadata and send it to your IdP administrator:

```bash
curl https://registry.example.com/simplesaml/module.php/saml/sp/metadata/federation-sp
```

The IdP administrator registers this XML in their system. SAML login will not work until registration is complete.

---

### Step 11 — Verify

```bash
# SSP reachable
curl -I https://registry.example.com/simplesaml/

# SP metadata generated
curl https://registry.example.com/simplesaml/module.php/saml/sp/metadata/federation-sp

# Laravel can read the SSP session (returns false, no error = working)
cd /var/www/federations-manager
php artisan tinker
>>> app(\App\Services\Auth\SamlServiceInterface::class)->isAuthenticated()
```

Then open `https://registry.example.com/login` in a browser and click **Login with institutional account** — you should be redirected to the IdP.

---

### Required IdP attributes

Your IdP must release these attributes to the SP:

| Attribute | SAML OID / name | Required |
|---|---|---|
| Email | `urn:oid:0.9.2342.19200300.100.1.3` (`mail`) | **Yes** |
| Display name | `urn:oid:2.16.840.1.113730.3.1.241` (`displayName`) | **Yes** (or givenName + sn) |
| First + last name | `urn:oid:2.5.4.42` + `urn:oid:2.5.4.4` (`givenName` + `sn`) | **Yes** if no displayName |
| ePPN | `urn:oid:1.3.6.1.4.1.5923.1.1.1.6` (`eduPersonPrincipalName`) | Recommended |

`mail` is mandatory — login is rejected if missing. `eduPersonPrincipalName` is strongly recommended; without it the user identity is tied to their email address and breaks if the email changes.

---

## Appendix — Quick Reference

### URL Map

| Feature | URL |
|---------|-----|
| Dashboard | `/dashboard` |
| Entities | `/entities` |
| Create entity | `/entities/create` |
| Import from XML | `/entities/import/xml` |
| Federations | `/federations` |
| Certificates | `/certificates/monitor` |
| Invitations | `/invitations` |
| Invitation requests | `/invitation-requests` |
| Notifications | `/notifications` |
| Statistics | `/statistics` |
| Webhooks | `/webhooks` |
| Compliance rules | `/rules` |
| Attribute definitions | `/attributes` |
| Mail templates | `/mail/templates` |
| Users | `/users` |
| Audit log | `/audit` |
| Scheduler | `/scheduler` |
| System preferences | `/preferences` |
| Notification preferences | `/profile/notifications` |

### Public API Endpoints (No Auth)

| Endpoint | Description |
|----------|-------------|
| `GET /.well-known/webfinger?resource={entityID}` | WebFinger lookup |
| `GET /api/discovery/entities` | JEDI JSON discovery feed |
| `GET /api/entities/{id}/oidc-configuration` | OIDC client registration JSON |
| `GET /metadata/{federation}/feed` | Signed aggregate metadata XML |
| `GET /metadata/{federation}/edugain` | eduGAIN-filtered metadata XML |
| `GET /signedmetadata/federation/{name}/metadata.xml` | Jagger-compat endpoint — serves the eduGAIN-filtered subset (federations with `jagger_compat_enabled = true` only) |

### Keyboard Shortcuts

The application does not define custom keyboard shortcuts. Standard browser shortcuts apply (Tab to navigate fields, Enter to submit forms).

---

*For technical issues, contact your federation administrator or open an issue in the project repository.*
