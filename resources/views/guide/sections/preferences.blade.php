<h2 class="h4 fw-bold mb-1">System Preferences</h2>
<p class="text-muted small mb-4">URL: <code>/preferences</code> &nbsp;|&nbsp; Permission: <code>federation.create</code> (Admin only)</p>

<p>Application-wide configuration stored in the database. Every change is written to the audit log. All values are validated server-side before saving.</p>

{{-- ── General ── --}}
<h5 class="fw-semibold mt-4 mb-2"><i class="bi bi-sliders me-1 text-muted"></i> General</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr><th style="width:38%;">Setting</th><th style="width:20%;">Default</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>app_name</code><br><span class="text-muted small">Application name</span></td>
            <td>Federation Manager</td>
            <td>Name of this instance. Shown in the sidebar, login page, browser tab title, emails, and notifications. Use your organisation's branding, e.g. <em>NREN Federation Registry</em>.</td>
        </tr>
        <tr>
            <td><code>app_url</code><br><span class="text-muted small">Application URL</span></td>
            <td><em>from APP_URL</em></td>
            <td>Used as the <code>[[app_url]]</code> placeholder in email templates. Does not affect application routing — actual URL handling is controlled by <code>APP_URL</code> in <code>.env</code> and the web server configuration.</td>
        </tr>
        <tr>
            <td><code>support_email</code><br><span class="text-muted small">Support email</span></td>
            <td><em>empty</em></td>
            <td>Contact address for the registry operator. When set, a <em>Need help or access?</em> mailto link is shown at the bottom of the login page. Leave blank to hide it.</td>
        </tr>
        <tr>
            <td><code>cookie_consent_enabled</code><br><span class="text-muted small">Show cookie consent banner</span></td>
            <td>Off</td>
            <td>When enabled, an informational banner is shown to unauthenticated visitors on their first visit. Required by GDPR in most EU deployments. The banner disappears after the user dismisses it (stored in a browser cookie).</td>
        </tr>
        <tr>
            <td><code>cookie_consent_text</code><br><span class="text-muted small">Cookie consent text</span></td>
            <td><em>session cookies text</em></td>
            <td>The message shown in the cookie consent banner. Should mention what cookies are used and for what purpose. This registry uses session cookies only — no tracking or analytics cookies.</td>
        </tr>
        <tr>
            <td><code>supported_languages</code><br><span class="text-muted small">Supported UI languages</span></td>
            <td>en,ro</td>
            <td>Comma-separated list of language codes available in the language switcher (e.g. <code>en,ro,de</code>). Each code must have a corresponding translation file in <code>lang/{code}/</code>. Removing a code hides it from the switcher immediately. <strong>Only shown when <code>I18N_ENABLED=true</code> in <code>.env</code>.</strong></td>
        </tr>
        <tr>
            <td><code>default_language</code><br><span class="text-muted small">Default UI language</span></td>
            <td>en</td>
            <td>Language applied when a user has no saved preference (e.g. on first login or in emails). Must be one of the codes in <em>Supported UI languages</em>. <strong>Only shown when <code>I18N_ENABLED=true</code> in <code>.env</code>.</strong></td>
        </tr>
    </tbody>
</table>

{{-- ── Page ── --}}
<h5 class="fw-semibold mt-4 mb-2"><i class="bi bi-layout-text-window me-1 text-muted"></i> Page</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr><th style="width:38%;">Setting</th><th style="width:20%;">Default</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>header_title_prefix</code><br><span class="text-muted small">Header title prefix</span></td>
            <td><em>empty</em></td>
            <td>Text prepended to the browser tab title on every page. For example, setting <code>NREN</code> turns "Dashboard" into "NREN Dashboard". Useful when running multiple instances so users can distinguish tabs.</td>
        </tr>
        <tr>
            <td><code>footer_text</code><br><span class="text-muted small">Footer custom text</span></td>
            <td><em>empty</em></td>
            <td>Additional text displayed in the footer on every page. Can include the organisation name, copyright notice, or a link to your privacy policy.</td>
        </tr>
    </tbody>
</table>

{{-- ── Mail ── --}}
<h5 class="fw-semibold mt-4 mb-2"><i class="bi bi-envelope me-1 text-muted"></i> Mail</h5>
<p class="small text-muted">These settings control the <em>From</em> header and signature of all outbound emails. The underlying SMTP/mailer configuration (host, port, credentials) is set in the server's <code>.env</code> file.</p>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr><th style="width:38%;">Setting</th><th style="width:20%;">Default</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>mail_from_name</code><br><span class="text-muted small">Mail sender name</span></td>
            <td>Federation Manager</td>
            <td>The display name in the <code>From:</code> header of every outbound email, e.g. <em>NREN Federation Registry</em>. This is what recipients see in their mail client before opening the message.</td>
        </tr>
        <tr>
            <td><code>mail_from_address</code><br><span class="text-muted small">Mail sender address</span></td>
            <td><em>from MAIL_FROM_ADDRESS</em></td>
            <td>The email address in the <code>From:</code> header. Must be a valid address that your mail server is authorised to send as (SPF/DKIM). Replies will go here unless a <code>Reply-To</code> is also configured.</td>
        </tr>
        <tr>
            <td><code>mail_signature</code><br><span class="text-muted small">Mail signature</span></td>
            <td><em>app name + URL</em></td>
            <td>Block of text appended to all notification and federation emails. Injected via the <code>[[mail_signature]]</code> placeholder in mail templates. Typically contains the registry name and URL so recipients know who sent the email.</td>
        </tr>
    </tbody>
</table>

{{-- ── Authentication ── --}}
<h5 class="fw-semibold mt-4 mb-2"><i class="bi bi-shield-lock me-1 text-muted"></i> Authentication</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr><th style="width:38%;">Setting</th><th style="width:20%;">Default</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>default_saml_role</code><br><span class="text-muted small">Default role for new SAML users</span></td>
            <td>Guest</td>
            <td>Role automatically assigned when a user logs in via SAML2 for the first time. <strong>Guest</strong> is the safest default — the user can view metadata but cannot create or edit entities until an Admin promotes them. Set to <strong>Entity Manager</strong> if your federation allows open self-service registration.</td>
        </tr>
        <tr>
            <td><code>session_timeout_minutes</code><br><span class="text-muted small">Session timeout (minutes)</span></td>
            <td>120</td>
            <td>Idle session lifetime in minutes. A user who does not interact with the application for this long is automatically logged out on their next request. Minimum: 5 minutes. Maximum: 1440 minutes (24 hours). Balance security against user convenience — 120 minutes is typical for admin tools.</td>
        </tr>
        <tr>
            <td><code>max_login_attempts</code><br><span class="text-muted small">Max login attempts before lockout</span></td>
            <td>5</td>
            <td>Number of consecutive failed password attempts before the account is temporarily locked. The lockout lasts 1 minute (Laravel's default throttle). Range: 3–20. Lower values protect against brute-force attacks; higher values reduce accidental lockouts for legitimate users.</td>
        </tr>
    </tbody>
</table>

{{-- ── eduGAIN ── --}}
<h5 class="fw-semibold mt-4 mb-2"><i class="bi bi-globe me-1 text-muted"></i> eduGAIN</h5>
<p class="small text-muted">Controls whether and how eduGAIN status data is fetched and shown in the UI. Requires outbound HTTPS access to the eduGAIN APIs.</p>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr><th style="width:38%;">Setting</th><th style="width:20%;">Default</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>edugain_checks_enabled</code><br><span class="text-muted small">Enable eduGAIN integration</span></td>
            <td>Off</td>
            <td>Master switch for all eduGAIN status checks. When enabled, entity show pages display an <strong>eduGAIN Status</strong> card showing whether the entity appears in the eduGAIN central metadata. Disable if your federation is not part of eduGAIN.</td>
        </tr>
        <tr>
            <td><code>edugain_federation_code</code><br><span class="text-muted small">Your eduGAIN federation code</span></td>
            <td>LEAF</td>
            <td>Your federation's code as registered in the eduGAIN MDS (e.g. <code>HAKA</code>, <code>IDEM</code>, <code>AAF</code>). Used to query the eduGAIN API for per-federation stats shown on the federation Metadata tab. Contact eduGAIN operations if you are unsure of your code.</td>
        </tr>
        <tr>
            <td><code>eccs_check_enabled</code><br><span class="text-muted small">Show ECCS connectivity status (IdP only)</span></td>
            <td>On</td>
            <td>When enabled, the entity status card for IdPs includes the result of the <strong>eduGAIN Connectivity Check Service (ECCS)</strong> — a test that verifies whether the IdP correctly consumes eduGAIN SP metadata. Only relevant for IdPs that participate in eduGAIN.</td>
        </tr>
        <tr>
            <td><code>edugain_entity_check_enabled</code><br><span class="text-muted small">Show entity presence in eduGAIN database</span></td>
            <td>On</td>
            <td>When enabled, the entity status card shows whether the entity is present in the eduGAIN central registry. Useful to confirm that a newly registered entity has been picked up by the eduGAIN MDS after the next sync cycle.</td>
        </tr>
    </tbody>
</table>
