<h2 class="h4 fw-bold mb-1">SimpleSAMLphp Authentication</h2>
<p class="text-muted small mb-4">Install and configure SimpleSAMLphp 2.4+ so users can log in with their institutional account via SAML2.</p>

<div class="alert alert-info small mb-4">
    <i class="bi bi-info-circle me-1"></i>
    The local email + password login always remains available as a fallback. SAML authentication is an addition, not a replacement.
</div>

{{-- ── How It Works ────────────────────────────────────────────────────────── --}}
<h5 class="fw-semibold mt-2 mb-2">How It Works</h5>
<p class="small text-muted mb-3">
    SimpleSAMLphp handles the entire SAML2 protocol — AuthnRequest, SAMLResponse validation, and SLO. The application never processes SAML XML directly. When a user clicks <em>Login with institutional account</em> they are redirected to the IdP via SimpleSAMLphp. After the assertion is returned, the app reads the SP session through <code>\SimpleSAML\Auth\Simple</code>, maps attributes to a user record, and creates a standard Laravel session. New users are automatically provisioned with the <strong>Guest</strong> role.
</p>

{{-- ── Choose deployment option ───────────────────────────────────────────── --}}
<h5 class="fw-semibold mt-3 mb-3">Choose a Deployment Option</h5>

<div class="table-responsive mb-4">
    <table class="table table-sm table-bordered small">
        <thead class="table-light">
            <tr>
                <th></th>
                <th>Option A — Standalone <span class="badge bg-success ms-1">Recommended</span></th>
                <th>Option B — Composer package</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>SSP location</td><td><code>/var/www/simplesaml/</code></td><td><code>vendor/simplesamlphp/simplesamlphp/</code></td></tr>
            <tr><td>Config survives <code>composer update</code></td><td>Yes</td><td>Yes (kept outside <code>vendor/</code>)</td></tr>
            <tr><td>Update SSP independently</td><td>Yes</td><td>No — tied to Composer</td></tr>
            <tr><td>Best for</td><td>Production</td><td>Dev / smaller deployments</td></tr>
        </tbody>
    </table>
</div>

<ul class="nav nav-tabs mb-4" id="sspOptionTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-standalone" data-bs-toggle="tab" data-bs-target="#pane-standalone" type="button" role="tab">Option A — Standalone</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-composer" data-bs-toggle="tab" data-bs-target="#pane-composer" type="button" role="tab">Option B — Composer package</button>
    </li>
</ul>

<div class="tab-content">

{{-- ══════════════════════════════════════════════════════════════════════════
     OPTION A — STANDALONE
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="tab-pane fade show active" id="pane-standalone" role="tabpanel">

    <p class="small text-muted mb-3">
        SSP runs as a fully separate application alongside Laravel. The SSP PHP library is also installed inside the Laravel project so it can read the SSP session.
    </p>

    {{-- A1 --}}
    <h5 class="fw-semibold mt-3 mb-2">A1. Install SimpleSAMLphp</h5>
    <pre class="bg-dark text-light p-3 rounded small">cd /var/www
composer create-project simplesamlphp/simplesamlphp simplesaml --no-dev</pre>
    <p class="small text-muted mt-2">Result: SSP lives at <code>/var/www/simplesaml/</code> alongside the Federation Manager at <code>/var/www/federations-manager/</code>.</p>

    {{-- A2 --}}
    <h5 class="fw-semibold mt-4 mb-2">A2. Generate SP Certificate</h5>
    <pre class="bg-dark text-light p-3 rounded small">cd /var/www/simplesaml/cert

openssl req -x509 -newkey rsa:4096 \
  -keyout sp.key -out sp.crt \
  -days 3650 -nodes \
  -subj "/CN=registry.example.com"

chmod 600 sp.key
chown www-data:www-data sp.key sp.crt</pre>

    {{-- A3 --}}
    <h5 class="fw-semibold mt-4 mb-2">A3. Configure SimpleSAMLphp</h5>
    <p class="small text-muted mb-2">Edit <code>/var/www/simplesaml/config/config.php</code>:</p>
    <pre class="bg-dark text-light p-3 rounded small">$config = [
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
];</pre>

    {{-- A4 --}}
    <h5 class="fw-semibold mt-4 mb-2">A4. Configure SP Authsource</h5>
    <p class="small text-muted mb-2">Edit <code>/var/www/simplesaml/config/authsources.php</code>:</p>
    <pre class="bg-dark text-light p-3 rounded small">$config = [
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
];</pre>
    <p class="small text-muted mt-2">The auth source name <code>federation-sp</code> must match <code>SAML2_AUTH_SOURCE</code> in <code>.env</code>.</p>

    {{-- A5 --}}
    <h5 class="fw-semibold mt-4 mb-2">A5. Add IdP Metadata</h5>
    <p class="small text-muted mb-2">Edit <code>/var/www/simplesaml/metadata/saml20-idp-remote.php</code>:</p>
    <pre class="bg-dark text-light p-3 rounded small">$metadata['https://your-idp.example.org/idp'] = [
    'SingleSignOnService' => [[
        'Binding'  => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        'Location' => 'https://your-idp.example.org/idp/SSO/Redirect',
    ]],
    'SingleLogoutService' => [[
        'Binding'  => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        'Location' => 'https://your-idp.example.org/idp/SLO/Redirect',
    ]],
    'certData' => 'BASE64_ENCODED_IDP_CERTIFICATE_NO_HEADERS',
];</pre>
    <p class="small text-muted mt-2">Strip the <code>-----BEGIN CERTIFICATE-----</code> / <code>-----END CERTIFICATE-----</code> headers and pass the bare Base64 string.</p>

    {{-- A6 --}}
    <h5 class="fw-semibold mt-4 mb-2">A6. Configure Nginx</h5>
    <p class="small text-muted mb-2">Add the <code>/simplesaml/</code> location block inside your existing Federation Manager server block:</p>
    <pre class="bg-dark text-light p-3 rounded small">location ^~ /simplesaml/ {
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
}</pre>
    <pre class="bg-dark text-light p-3 rounded small">sudo nginx -t && sudo systemctl reload nginx</pre>

    {{-- A7 --}}
    <h5 class="fw-semibold mt-4 mb-2">A7. Install SSP PHP Library in Federation Manager</h5>
    <p class="small text-muted mb-2">Laravel uses the SSP PHP API to read the session. The library must also be present in the Laravel project:</p>
    <pre class="bg-dark text-light p-3 rounded small">cd /var/www/federations-manager
composer require simplesamlphp/simplesamlphp</pre>

    {{-- A8 --}}
    <h5 class="fw-semibold mt-4 mb-2">A8. Configure Federation Manager <code>.env</code></h5>
    <pre class="bg-dark text-light p-3 rounded small"># Session — must match session.cookie.name in SSP config.php
SESSION_DRIVER=file
SESSION_COOKIE=laravel_session
SESSION_DOMAIN=registry.example.com
SESSION_SECURE_COOKIE=true

# Point to the standalone SSP config directory
SIMPLESAMLPHP_CONFIG_DIR=/var/www/simplesaml/config

# SAML2 SP
SAML2_SP_ENTITY_ID=https://registry.example.com/saml2/metadata
SAML2_AUTH_SOURCE=federation-sp
SAML2_BASEURLPATH=/simplesaml/

# IdP
SAML2_IDP_ENTITY_ID=https://your-idp.example.org/idp
SAML2_IDP_SSO_URL=https://your-idp.example.org/idp/SSO/Redirect
SAML2_IDP_SLS_URL=https://your-idp.example.org/idp/SLO/Redirect
SAML2_IDP_CERT=BASE64_IDP_CERT_NO_HEADERS</pre>

    {{-- A9 --}}
    <h5 class="fw-semibold mt-4 mb-2">A9. Set Permissions</h5>
    <pre class="bg-dark text-light p-3 rounded small">chown -R www-data:www-data /var/www/simplesaml
chmod -R 775 /var/www/simplesaml/log
chmod -R 775 /var/www/simplesaml/data
chmod    600 /var/www/simplesaml/cert/sp.key

chmod -R 775 /var/www/federations-manager/storage/framework/sessions</pre>

    {{-- A10 --}}
    <h5 class="fw-semibold mt-4 mb-2">A10. Register SP Metadata with the IdP</h5>
    <pre class="bg-dark text-light p-3 rounded small">curl https://registry.example.com/simplesaml/module.php/saml/sp/metadata/federation-sp</pre>
    <p class="small text-muted mt-2">Send the returned XML to your IdP administrator. SAML login will not work until the IdP has registered the SP.</p>

    {{-- A11 --}}
    <h5 class="fw-semibold mt-4 mb-2">A11. Verify</h5>
    <pre class="bg-dark text-light p-3 rounded small"># SSP reachable
curl -I https://registry.example.com/simplesaml/

# Laravel can read the SSP session (returns false = no error = working)
cd /var/www/federations-manager
php artisan tinker
>>> app(\App\Services\Auth\SamlServiceInterface::class)->isAuthenticated()</pre>
    <p class="small text-muted mt-2">Then open the app login page and click <strong>Login with institutional account</strong> — you should be redirected to the IdP.</p>

</div>{{-- /pane-standalone --}}

{{-- ══════════════════════════════════════════════════════════════════════════
     OPTION B — COMPOSER PACKAGE
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="tab-pane fade" id="pane-composer" role="tabpanel">

    <p class="small text-muted mb-3">
        SSP is installed as a Composer dependency inside the Laravel project. Configuration is kept in a dedicated directory outside <code>vendor/</code> so it survives <code>composer update</code>.
    </p>

    {{-- B1 --}}
    <h5 class="fw-semibold mt-3 mb-2">B1. Install SSP via Composer</h5>
    <pre class="bg-dark text-light p-3 rounded small">cd /var/www/federations-manager
composer require simplesamlphp/simplesamlphp</pre>

    {{-- B2 --}}
    <h5 class="fw-semibold mt-4 mb-2">B2. Create External Config Directory</h5>
    <pre class="bg-dark text-light p-3 rounded small">cp -r vendor/simplesamlphp/simplesamlphp/config ssp-config
cp -r vendor/simplesamlphp/simplesamlphp/metadata ssp-metadata</pre>
    <p class="small text-muted mt-2">Add to <code>.env</code>:</p>
    <pre class="bg-dark text-light p-3 rounded small">SIMPLESAMLPHP_CONFIG_DIR=/var/www/federations-manager/ssp-config</pre>

    {{-- B3 --}}
    <h5 class="fw-semibold mt-4 mb-2">B3. Generate SP Certificate</h5>
    <pre class="bg-dark text-light p-3 rounded small">mkdir -p ssp-config/cert
cd ssp-config/cert

openssl req -x509 -newkey rsa:4096 \
  -keyout sp.key -out sp.crt \
  -days 3650 -nodes \
  -subj "/CN=registry.example.com"

chmod 600 sp.key</pre>

    {{-- B4 --}}
    <h5 class="fw-semibold mt-4 mb-2">B4. Configure SimpleSAMLphp</h5>
    <p class="small text-muted mb-2">Edit <code>ssp-config/config.php</code>:</p>
    <pre class="bg-dark text-light p-3 rounded small">$config = [
    'baseurlpath'    => 'https://registry.example.com/simplesaml/',
    'certdir'        => '/var/www/federations-manager/ssp-config/cert/',

    // Must share session with Laravel
    'store.type'              => 'phpsession',
    'session.cookie.name'     => 'laravel_session',
    'session.cookie.path'     => '/',
    'session.cookie.domain'   => 'registry.example.com',
    'session.cookie.secure'   => true,
    'session.cookie.httponly' => true,
    'session.cookie.samesite' => 'Lax',

    'secretsalt'         => 'CHANGE_THIS_TO_RANDOM_STRING',
    'auth.adminpassword' => 'CHANGE_THIS_ADMIN_PASSWORD',

    'logging.level'   => SimpleSAML\Logger::NOTICE,
    'logging.handler' => 'file',
];</pre>

    {{-- B5 --}}
    <h5 class="fw-semibold mt-4 mb-2">B5. Configure SP Authsource</h5>
    <p class="small text-muted mb-2">Edit <code>ssp-config/authsources.php</code>:</p>
    <pre class="bg-dark text-light p-3 rounded small">$config = [
    'federation-sp' => [
        'saml:SP',
        'entityID'             => 'https://registry.example.com/saml2/metadata',
        'idp'                  => 'https://your-idp.example.org/idp',
        'privatekey'           => 'sp.key',
        'certificate'          => 'sp.crt',
        'sign.authnrequest'    => true,
        'WantAssertionsSigned' => true,
        'attributes' => [
            'urn:oid:1.3.6.1.4.1.5923.1.1.1.6',
            'urn:oid:0.9.2342.19200300.100.1.3',
            'urn:oid:2.5.4.42',
            'urn:oid:2.5.4.4',
            'urn:oid:2.16.840.1.113730.3.1.241',
        ],
    ],
];</pre>

    {{-- B6 --}}
    <h5 class="fw-semibold mt-4 mb-2">B6. Add IdP Metadata</h5>
    <p class="small text-muted mb-2">Edit <code>ssp-metadata/saml20-idp-remote.php</code> — same format as Option A step A5.</p>

    {{-- B7 --}}
    <h5 class="fw-semibold mt-4 mb-2">B7. Configure Nginx</h5>
    <pre class="bg-dark text-light p-3 rounded small">location ^~ /simplesaml/ {
    alias /var/www/federations-manager/vendor/simplesamlphp/simplesamlphp/public/;

    location ~ \.php(/|$) {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass  unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/federations-manager/vendor/simplesamlphp/simplesamlphp/public$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        include       fastcgi_params;
    }
}</pre>

    {{-- B8 --}}
    <h5 class="fw-semibold mt-4 mb-2">B8. Configure Federation Manager <code>.env</code></h5>
    <pre class="bg-dark text-light p-3 rounded small">SESSION_DRIVER=file
SESSION_COOKIE=laravel_session
SESSION_DOMAIN=registry.example.com
SESSION_SECURE_COOKIE=true

SIMPLESAMLPHP_CONFIG_DIR=/var/www/federations-manager/ssp-config

SAML2_SP_ENTITY_ID=https://registry.example.com/saml2/metadata
SAML2_AUTH_SOURCE=federation-sp
SAML2_BASEURLPATH=/simplesaml/

SAML2_IDP_ENTITY_ID=https://your-idp.example.org/idp
SAML2_IDP_SSO_URL=https://your-idp.example.org/idp/SSO/Redirect
SAML2_IDP_SLS_URL=https://your-idp.example.org/idp/SLO/Redirect
SAML2_IDP_CERT=BASE64_IDP_CERT_NO_HEADERS</pre>

</div>{{-- /pane-composer --}}

</div>{{-- /tab-content --}}

{{-- ── Required IdP Attributes (shared) ──────────────────────────────────── --}}
<h5 class="fw-semibold mt-5 mb-2">Required IdP Attributes</h5>
<p class="small text-muted mb-2">Configure your IdP to release these attributes to the SP:</p>
<table class="table table-sm table-bordered small mb-4">
    <thead class="table-light">
        <tr><th>Attribute</th><th>SAML name / OID</th><th>Required</th><th><code>.env</code> override</th></tr>
    </thead>
    <tbody>
        <tr>
            <td>Email</td>
            <td><code>mail</code> / <code>urn:oid:0.9.2342.19200300.100.1.3</code></td>
            <td><span class="text-danger fw-semibold">Yes</span> — login rejected if missing</td>
            <td><code>SAML2_ATTR_MAIL</code></td>
        </tr>
        <tr>
            <td>Display name</td>
            <td><code>displayName</code> / <code>urn:oid:2.16.840.1.113730.3.1.241</code></td>
            <td><span class="text-danger fw-semibold">Yes</span> (or givenName + sn)</td>
            <td><code>SAML2_ATTR_DISPLAY_NAME</code></td>
        </tr>
        <tr>
            <td>First + last name</td>
            <td><code>givenName</code> + <code>sn</code></td>
            <td>Yes if no <code>displayName</code></td>
            <td><code>SAML2_ATTR_GIVEN_NAME</code> / <code>SAML2_ATTR_SURNAME</code></td>
        </tr>
        <tr>
            <td>ePPN</td>
            <td><code>eduPersonPrincipalName</code> / <code>urn:oid:1.3.6.1.4.1.5923.1.1.1.6</code></td>
            <td>Recommended — persistent identity that survives email changes</td>
            <td><code>SAML2_ATTR_EPPN</code></td>
        </tr>
    </tbody>
</table>

{{-- ── Troubleshooting (shared) ────────────────────────────────────────────── --}}
<h5 class="fw-semibold mt-4 mb-2">Troubleshooting</h5>
<table class="table table-sm table-bordered small mb-2">
    <thead class="table-light">
        <tr><th style="width:38%">Symptom</th><th>Likely cause</th></tr>
    </thead>
    <tbody>
        <tr>
            <td>Login button does nothing / stays on login page</td>
            <td>SSP not reachable at <code>/simplesaml/</code> — check Nginx alias and <code>SAML2_BASEURLPATH</code></td>
        </tr>
        <tr>
            <td>Session cookie mismatch / redirect loop</td>
            <td><code>session.cookie.name</code> in SSP <code>config.php</code> does not match <code>SESSION_COOKIE</code> in <code>.env</code></td>
        </tr>
        <tr>
            <td>422 error after successful IdP login</td>
            <td>IdP is not releasing <code>mail</code> or display name — check attribute release policy on the IdP</td>
        </tr>
        <tr>
            <td>User created but has Guest role and cannot access anything</td>
            <td>Expected — promote the user to a higher role in <strong>Users</strong></td>
        </tr>
        <tr>
            <td><code>SimpleSAMLphp is not installed</code> error</td>
            <td><code>composer require simplesamlphp/simplesamlphp</code> was not run in the Laravel project</td>
        </tr>
        <tr>
            <td>Config changes ignored after <code>composer update</code></td>
            <td>Config was inside <code>vendor/</code> — ensure <code>SIMPLESAMLPHP_CONFIG_DIR</code> points to <code>ssp-config/</code>, not <code>vendor/</code></td>
        </tr>
    </tbody>
</table>
<p class="small text-muted">SSP writes detailed logs to the <code>log/</code> directory configured in <code>config.php</code>. Check that file first when debugging assertion errors.</p>
