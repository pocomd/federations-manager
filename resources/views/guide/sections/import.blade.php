<h2 class="h4 fw-bold mb-1">Import from Jagger</h2>
<p class="text-muted small mb-4">URL: <code>/import/jagger</code> &nbsp;|&nbsp; Permission: Admin only &nbsp;|&nbsp; Requires <code>JAGGER_IMPORT_ENABLED=true</code></p>

<p>Migrates federations, entities and all related data from a legacy <strong>Jagger (ResourceRegistry 3)</strong> MySQL database via a live connection. The import is fully transactional — if any record fails the entire operation is rolled back and the database is left unchanged.</p>

<div class="alert alert-warning small mb-4">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Must be enabled with <code>JAGGER_IMPORT_ENABLED=true</code> in <code>.env</code>. Disabled by default.
</div>

{{-- ── Options ── --}}
<h5 class="fw-semibold mt-4 mb-2">Options</h5>
<table class="table table-sm table-bordered small">
    <thead class="table-light"><tr><th>Option</th><th>Effect</th></tr></thead>
    <tbody>
        <tr><td><strong>Local entities only</strong></td><td>Skips providers where <code>is_local = 0</code> in Jagger (external/remote entities)</td></tr>
        <tr><td><strong>Skip existing records</strong></td><td>Entities matched by <code>entityID</code> and federations matched by URN are skipped instead of re-imported</td></tr>
        <tr><td><strong>Clear existing data before import</strong></td><td>Deletes all entities tagged <code>source = imported</code> and all federations before running — use for a clean re-import. Requires confirmation.</td></tr>
    </tbody>
</table>

{{-- ── Import phases ── --}}
<h5 class="fw-semibold mt-4 mb-2">Import phases (in order)</h5>
<ol class="small">
    <li>Federations</li>
    <li>Entities + UI info (display names, org names, descriptions, URLs in all languages)</li>
    <li>Federation memberships</li>
    <li>Certificates (PEM parsed from bare base64 via OpenSSL)</li>
    <li>Contacts</li>
    <li>Endpoints (SSO, ACS, SLO, Artifact)</li>
    <li>Attribute definitions</li>
    <li>Attribute requirements (per-SP and per-federation)</li>
</ol>
<p class="small text-muted">All phases run inside one transaction. If any phase collects errors the transaction rolls back — no partial data is written. Warnings (bad certificates, duplicate membership triggers) are non-fatal and shown after a successful import.</p>

{{-- ── Field mapping ── --}}
<h4 class="fw-bold mt-5 mb-3">Field mapping — Jagger → Federation Registry</h4>

{{-- Federations --}}
<h5 class="fw-semibold mt-4 mb-2">1. Federations &nbsp;<small class="text-muted fw-normal">jagger.<code>federation</code> → <code>federations</code></small></h5>
<div class="table-responsive">
<table class="table table-sm table-bordered small">
    <thead class="table-light">
        <tr><th>Source column</th><th>Source type</th><th>Destination column</th><th>Destination type</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td><code>name</code></td><td>varchar(255)</td><td><code>name</code></td><td>varchar(255)</td><td>direct</td></tr>
        <tr><td class="text-muted">—</td><td class="text-muted">—</td><td><code>slug</code></td><td>varchar(255) UNIQUE</td><td>generated: <code>Str::slug(name)</code>, deduped with -2, -3…</td></tr>
        <tr><td><code>urn</code></td><td>varchar(255)</td><td><code>uri</code></td><td>varchar(512) UNIQUE</td><td>direct; used as match key for skip-existing</td></tr>
        <tr><td><code>description</code></td><td>text</td><td><code>description</code></td><td>text NULL</td><td>direct</td></tr>
        <tr><td><code>is_active</code></td><td>tinyint</td><td><code>status</code></td><td>enum(active, inactive)</td><td>1 → active, 0 → inactive</td></tr>
        <tr class="table-secondary"><td><code>id</code></td><td>int</td><td class="text-muted">not stored</td><td>—</td><td>used internally as fedMap key</td></tr>
        <tr class="table-secondary"><td><code>metadata_url</code> etc.</td><td>—</td><td class="text-muted">not imported</td><td>—</td><td>public flag, metadata URL not in Jagger schema</td></tr>
    </tbody>
</table>
</div>

{{-- Entities --}}
<h5 class="fw-semibold mt-4 mb-2">2. Entities &nbsp;<small class="text-muted fw-normal">jagger.<code>provider</code> → <code>entities</code></small></h5>
<div class="table-responsive">
<table class="table table-sm table-bordered small">
    <thead class="table-light">
        <tr><th>Source column</th><th>Source type</th><th>Destination column</th><th>Destination type</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td><code>entityid</code></td><td>varchar(255)</td><td><code>entity_id</code></td><td>varchar(255) UNIQUE</td><td>match key for skip-existing</td></tr>
        <tr><td><code>type</code></td><td>enum(IDP, SP, BOTH)</td><td><code>type</code></td><td>enum(idp, sp)</td><td>lowercase; BOTH → idp (counted separately)</td></tr>
        <tr><td><code>is_approved</code> + <code>is_active</code></td><td>tinyint + tinyint</td><td><code>status</code></td><td>enum(draft, pending, active, suspended)</td><td>approved+active → active · !approved+active → pending · approved+!active → suspended · else → draft</td></tr>
        <tr><td><code>scope</code></td><td>text (PHP-serialized)</td><td><code>scope</code></td><td>varchar(255) NULL</td><td>unserialize → first string value; nested arrays unwrapped one level</td></tr>
        <tr><td><code>nameids</code></td><td>text (PHP-serialized)</td><td><code>nameid_formats</code></td><td>json NULL</td><td>unserialize → flatten one level → json_encode array of strings</td></tr>
        <tr><td><code>wantauthnreqsigned</code></td><td>tinyint</td><td><code>sp_want_authn_requests_signed</code></td><td>boolean</td><td>cast to bool</td></tr>
        <tr><td><code>wantassertsigned</code></td><td>tinyint</td><td><code>sp_want_assertions_signed</code></td><td>boolean</td><td>cast to bool</td></tr>
        <tr><td><code>registrar</code></td><td>varchar</td><td><code>registration_authority</code></td><td>varchar(255) DEFAULT ''</td><td>fallback chain: provider.registrar → federation.urn → config FEDERATION_REGISTRATION_AUTHORITY → ''</td></tr>
        <tr><td><code>created_at</code> / <code>updated_at</code></td><td>timestamp</td><td><code>created_at</code> / <code>updated_at</code></td><td>timestamp</td><td>direct; Jagger timestamps preserved</td></tr>
        <tr><td class="text-muted">—</td><td class="text-muted">—</td><td><code>source</code></td><td>enum</td><td>always <code>imported</code></td></tr>
        <tr><td class="text-muted">—</td><td class="text-muted">—</td><td><code>edugain</code></td><td>boolean</td><td>always false</td></tr>
        <tr><td class="text-muted">—</td><td class="text-muted">—</td><td><code>sha1_entity_id</code></td><td>varchar(40)</td><td>sha1(entityid)</td></tr>
        <tr class="table-secondary"><td><code>is_local</code></td><td>tinyint</td><td class="text-muted">filter only</td><td>—</td><td>rows with is_local=0 skipped when "Local entities only" is on</td></tr>
        <tr class="table-secondary"><td><code>federation_id</code></td><td>int</td><td class="text-muted">not stored</td><td>—</td><td>used only as registration_authority fallback lookup</td></tr>
    </tbody>
</table>
</div>

{{-- Entity UI info --}}
<h5 class="fw-semibold mt-4 mb-2">2b. Entity UI Info &nbsp;<small class="text-muted fw-normal">jagger.<code>provider</code> → <code>entity_ui_info</code></small></h5>
<p class="small text-muted">One row per field per language. Localized columns are PHP-serialized <code>lang → value</code> maps; the plain column is stored as <code>lang = 'en'</code> if no English entry exists in the serialized map.</p>
<div class="table-responsive">
<table class="table table-sm table-bordered small">
    <thead class="table-light">
        <tr><th>Source column(s)</th><th>Destination field</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td><code>displayname</code> + <code>ldisplayname</code></td><td><code>display_name</code></td><td>localized</td></tr>
        <tr><td><code>name</code> + <code>lname</code></td><td><code>org_name</code></td><td>localized</td></tr>
        <tr><td><code>name</code> + <code>lname</code></td><td><code>org_display_name</code></td><td>localized; Jagger 3 has no separate org display name field — same source as <code>org_name</code></td></tr>
        <tr><td><code>url</code> + <code>lurl</code></td><td><code>org_url</code></td><td>localized organisation website URL</td></tr>
        <tr><td><code>description</code></td><td><code>description</code></td><td>English only (Jagger has no localized description)</td></tr>
        <tr><td><code>helpdeskurl</code> + <code>lhelpdeskurl</code></td><td><code>information_url</code></td><td>localized</td></tr>
        <tr><td><code>privacyurl</code> + <code>lprivacyurl</code></td><td><code>privacy_url</code></td><td>localized</td></tr>
    </tbody>
</table>
</div>

{{-- Memberships --}}
<h5 class="fw-semibold mt-4 mb-2">3. Memberships &nbsp;<small class="text-muted fw-normal">jagger.<code>federation_members</code> → <code>entity_federation</code></small></h5>
<div class="table-responsive">
<table class="table table-sm table-bordered small">
    <thead class="table-light">
        <tr><th>Source column</th><th>Source type</th><th>Destination column</th><th>Destination type</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td><code>federation_id</code></td><td>int</td><td><code>federation_id</code></td><td>uuid</td><td>resolved via fedMap</td></tr>
        <tr><td><code>provider_id</code></td><td>int</td><td><code>entity_id</code></td><td>uuid</td><td>resolved via entityMap</td></tr>
        <tr><td><code>joinstate</code> + <code>isdisabled</code> + <code>isbanned</code></td><td>int + tinyint + tinyint</td><td><code>status</code></td><td>enum(pending, active, rejected, suspended)</td><td>banned → rejected · disabled → suspended · joinstate=2 → suspended · else → active</td></tr>
        <tr><td class="text-muted">—</td><td class="text-muted">—</td><td><code>approved_at</code></td><td>timestamp NULL</td><td>now() if active, else null</td></tr>
        <tr><td class="text-muted">—</td><td class="text-muted">—</td><td><code>approved_by</code></td><td>uuid NULL</td><td>always null (no user mapping)</td></tr>
    </tbody>
</table>
</div>
<p class="small text-muted"><i class="bi bi-info-circle me-1"></i>Skipped if federation or entity was not imported. DB trigger 1644 (entity already active in another federation) is treated as a warning/skip, not a fatal error.</p>

{{-- Certificates --}}
<h5 class="fw-semibold mt-4 mb-2">4. Certificates &nbsp;<small class="text-muted fw-normal">jagger.<code>certificate</code> → <code>entity_certificates</code></small></h5>
<div class="table-responsive">
<table class="table table-sm table-bordered small">
    <thead class="table-light">
        <tr><th>Source column</th><th>Source type</th><th>Destination column</th><th>Destination type</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td><code>provider_id</code></td><td>int</td><td><code>entity_id</code></td><td>uuid</td><td>resolved via entityMap</td></tr>
        <tr><td><code>certusage</code></td><td>varchar</td><td><code>use</code></td><td>enum(signing, encryption, both)</td><td>'encryption' → encryption; anything else → signing</td></tr>
        <tr><td><code>certdata</code></td><td>text (base64, may be chunked)</td><td><code>pem</code></td><td>text</td><td>strip whitespace + PEM headers → chunk_split(64) → PEM-wrap</td></tr>
        <tr><td colspan="2" class="text-muted fst-italic">parsed by OpenSSL</td><td><code>subject</code></td><td>varchar(512)</td><td><code>$parsed['name']</code></td></tr>
        <tr><td colspan="2" class="text-muted fst-italic">parsed by OpenSSL</td><td><code>issuer</code></td><td>varchar(512)</td><td>DN string built from issuer array</td></tr>
        <tr><td colspan="2" class="text-muted fst-italic">parsed by OpenSSL</td><td><code>serial</code></td><td>varchar(128)</td><td><code>serialNumberHex</code></td></tr>
        <tr><td colspan="2" class="text-muted fst-italic">parsed by OpenSSL</td><td><code>not_before</code> / <code>not_after</code></td><td>timestamp NULL</td><td><code>validFrom_time_t</code> / <code>validTo_time_t</code></td></tr>
        <tr><td colspan="2" class="text-muted fst-italic">parsed by OpenSSL</td><td><code>key_bits</code></td><td>smallint</td><td><code>bits</code></td></tr>
        <tr><td colspan="2" class="text-muted fst-italic">parsed by OpenSSL</td><td><code>key_algorithm</code></td><td>varchar(50)</td><td>RSA / DSA / EC / unknown</td></tr>
        <tr><td colspan="2" class="text-muted fst-italic">computed</td><td><code>fingerprint</code></td><td>varchar(128)</td><td>SHA-256 fingerprint via <code>openssl_x509_fingerprint()</code></td></tr>
        <tr><td colspan="2" class="text-muted fst-italic">parsed by OpenSSL</td><td><code>signature_algorithm</code></td><td>varchar(100)</td><td><code>signatureTypeLN</code></td></tr>
        <tr class="table-secondary"><td><code>name</code></td><td>varchar</td><td class="text-muted">not imported</td><td>—</td><td>certificate label; no equivalent column</td></tr>
    </tbody>
</table>
</div>

{{-- Contacts --}}
<h5 class="fw-semibold mt-4 mb-2">5. Contacts &nbsp;<small class="text-muted fw-normal">jagger.<code>contact</code> → <code>entity_contacts</code></small></h5>
<div class="table-responsive">
<table class="table table-sm table-bordered small">
    <thead class="table-light">
        <tr><th>Source column</th><th>Source type</th><th>Destination column</th><th>Destination type</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td><code>provider_id</code></td><td>int</td><td><code>entity_id</code></td><td>uuid</td><td>resolved via entityMap</td></tr>
        <tr><td><code>type</code></td><td>varchar</td><td><code>type</code></td><td>enum(technical, support, security, administrative, billing)</td><td>invalid value → 'technical'</td></tr>
        <tr><td><code>givenname</code></td><td>varchar</td><td><code>given_name</code></td><td>varchar(255) NULL</td><td>direct</td></tr>
        <tr><td><code>surname</code></td><td>varchar</td><td><code>sur_name</code></td><td>varchar(255) NULL</td><td>direct</td></tr>
        <tr><td><code>email</code></td><td>varchar</td><td><code>email</code></td><td>varchar(255)</td><td>direct</td></tr>
        <tr><td><code>phone</code></td><td>varchar</td><td><code>phone</code></td><td>varchar(50) NULL</td><td>direct</td></tr>
    </tbody>
</table>
</div>

{{-- Endpoints --}}
<h5 class="fw-semibold mt-4 mb-2">6. Endpoints &nbsp;<small class="text-muted fw-normal">jagger.<code>endpoint</code> → <code>entity_endpoints</code></small></h5>
<div class="table-responsive">
<table class="table table-sm table-bordered small">
    <thead class="table-light">
        <tr><th>Source column</th><th>Source type</th><th>Destination column</th><th>Destination type</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td><code>provider_id</code></td><td>int</td><td><code>entity_id</code></td><td>uuid</td><td>resolved via entityMap</td></tr>
        <tr><td><code>type</code></td><td>varchar</td><td><code>type</code></td><td>enum(sso,acs,slo,artifact)</td><td>SingleSignOnService→sso, AssertionConsumerService→acs, SingleLogoutService→slo; unknown types are skipped</td></tr>
        <tr><td><code>binding</code></td><td>varchar</td><td><code>binding</code></td><td>varchar</td><td>full binding URI stored as-is (e.g. urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST)</td></tr>
        <tr><td><code>url</code></td><td>varchar</td><td><code>location</code></td><td>text</td><td>also tries <code>location</code> column as fallback</td></tr>
        <tr><td><code>response_url</code></td><td>varchar NULL</td><td><code>response_location</code></td><td>text NULL</td><td>also tries <code>response_location</code></td></tr>
        <tr><td><code>index</code></td><td>int NULL</td><td><code>index</code></td><td>smallint NULL</td><td>mainly used for ACS indexed endpoints</td></tr>
        <tr><td><code>is_default</code></td><td>tinyint</td><td><code>is_default</code></td><td>boolean</td><td>direct</td></tr>
    </tbody>
</table>
</div>

{{-- Attributes --}}
<h5 class="fw-semibold mt-4 mb-2">7. Attribute Definitions &nbsp;<small class="text-muted fw-normal">jagger.<code>attribute</code> → <code>attribute_definitions</code></small></h5>
<div class="table-responsive">
<table class="table table-sm table-bordered small">
    <thead class="table-light">
        <tr><th>Source column</th><th>Source type</th><th>Destination column</th><th>Destination type</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td><code>name</code></td><td>varchar</td><td><code>name</code></td><td>varchar(100) UNIQUE</td><td>dedupe check: skip if name or OID already exists</td></tr>
        <tr><td><code>fullname</code></td><td>varchar</td><td><code>full_name</code></td><td>varchar(255)</td><td>direct</td></tr>
        <tr><td><code>oid</code></td><td>varchar</td><td><code>saml2_oid</code></td><td>varchar(255) UNIQUE NULL</td><td>direct</td></tr>
        <tr><td><code>urn</code></td><td>varchar</td><td><code>saml1_urn</code></td><td>varchar(255) NULL</td><td>direct</td></tr>
        <tr><td><code>description</code></td><td>text</td><td><code>description</code></td><td>text NULL</td><td>direct</td></tr>
        <tr><td><code>inmetadata</code></td><td>tinyint</td><td><code>is_active</code></td><td>boolean</td><td>cast to bool</td></tr>
        <tr><td class="text-muted">—</td><td class="text-muted">—</td><td><code>is_required</code></td><td>boolean</td><td>always false</td></tr>
    </tbody>
</table>
</div>

{{-- Attribute requirements --}}
<h5 class="fw-semibold mt-4 mb-2">8a. SP Attribute Requirements &nbsp;<small class="text-muted fw-normal">jagger.<code>attribute_requirement</code> (type='SP') → <code>entity_requested_attributes</code></small></h5>
<div class="table-responsive">
<table class="table table-sm table-bordered small">
    <thead class="table-light">
        <tr><th>Source column</th><th>Destination column</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td><code>attribute_id</code></td><td><code>attribute_definition_id</code></td><td>resolved via attrMap</td></tr>
        <tr><td><code>sp_id</code></td><td><code>entity_id</code></td><td>resolved via entityMap</td></tr>
        <tr><td><code>status</code> === 'required'</td><td><code>is_required</code></td><td>boolean</td></tr>
        <tr><td><code>reason</code></td><td><code>reason</code></td><td>text NULL</td></tr>
    </tbody>
</table>
</div>

<h5 class="fw-semibold mt-4 mb-2">8b. Federation Attribute Requirements &nbsp;<small class="text-muted fw-normal">jagger.<code>attribute_requirement</code> (type='FED') → <code>federation_required_attributes</code></small></h5>
<div class="table-responsive">
<table class="table table-sm table-bordered small">
    <thead class="table-light">
        <tr><th>Source column</th><th>Destination column</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td><code>attribute_id</code></td><td><code>attribute_definition_id</code></td><td>resolved via attrMap</td></tr>
        <tr><td><code>fed_id</code></td><td><code>federation_id</code></td><td>resolved via fedMap</td></tr>
        <tr><td><code>status</code> === 'required'</td><td><code>is_required</code></td><td>boolean</td></tr>
        <tr><td><code>reason</code></td><td><code>notes</code></td><td>text NULL</td></tr>
    </tbody>
</table>
</div>

<div class="alert alert-secondary small mt-4">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Not imported:</strong> users, roles, ACL entries, notification subscriptions, entity logos, Jagger workflow history, <code>provider.ser</code> signing cert path field.
</div>
