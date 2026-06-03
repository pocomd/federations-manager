# SimpleSAMLphp Integration — Deployment Manual

## Overview

Federation Manager uses SimpleSAMLphp (SSP) as a SAML2 Service Provider for institutional authentication. Laravel never processes SAML XML directly — SSP handles the entire protocol (AuthnRequest, SAMLResponse validation, SLO) and writes attributes to a PHP session that Laravel reads via the SSP PHP API.

There are two deployment variants. Choose one before starting:

| | [Option A — Standalone](#option-a--standalone-installation) | [Option B — Composer package](#option-b--composer-package) |
|---|---|---|
| **SSP lives at** | `/var/www/simplesaml/` | `vendor/simplesamlphp/simplesamlphp/` |
| **Config survives `composer update`** | Yes | Yes (with external config dir) |
| **Nginx** | `alias /var/www/simplesaml/public/` | `alias vendor/.../public/` |
| **Update SSP independently** | Yes | No — tied to Composer |
| **Setup complexity** | Two installs | One install |
| **Recommended for** | Production | Dev / smaller deployments |

### Authentication options

1. **Institutional login** — SAML2 SSO via SimpleSAMLphp (primary)
2. **Local login** — email + password via Laravel Auth (fallback for admin access)

---

## Architecture

Both variants produce the same runtime architecture — the only difference is where SSP files live on disk.

```
Browser
  │
  ▼
Nginx (registry.example.com)
  │
  ├─ /             → Laravel (Federation Manager)
  │                  reads SSP session via \SimpleSAML\Auth\Simple
  │
  └─ /simplesaml/  → SimpleSAMLphp (SAML2 SP)
                     handles AuthnRequest, SAMLResponse, SLO
                     writes attributes to PHP session
```

### Login flow

```
1.  User visits /
2.  EnsureAuthenticated middleware → not logged in → redirect /login
3.  User clicks "Login with institutional account"
4.  Laravel /saml/login → SamlService::getLoginUrl()
5.  Browser redirected to SSP → SSP constructs AuthnRequest
6.  Browser → IdP SSO endpoint (user authenticates)
7.  IdP → POST SAMLResponse → /simplesaml/ ACS endpoint
8.  SSP validates response → stores attributes in PHP session
9.  SSP → redirect → Laravel /saml/acs
10. SamlAuthController::acs() → reads attributes via SamlService
11. findOrCreateUser() → Auth::login($user)
12. Redirect → /dashboard
```

---

## Prerequisites

- PHP 8.4, Composer 2.x, Nginx, OpenSSL
- Your IdP's SSO URL, SLO URL, and public certificate

---

## Option A — Standalone installation

SSP runs as a fully separate application alongside Laravel. The SSP PHP library is also required in Laravel to read the session.

### A1 — Deploy SimpleSAMLphp

```bash
cd /var/www
composer create-project simplesamlphp/simplesamlphp simplesaml --no-dev
```

Result:
```
/var/www/
  federation/        ← Laravel (Federation Manager)
  simplesaml/        ← SimpleSAMLphp
    public/
    config/
    metadata/
    cert/
```

### A2 — Generate SP certificate

```bash
cd /var/www/simplesaml/cert

openssl req -x509 -newkey rsa:4096 \
  -keyout sp.key -out sp.crt \
  -days 3650 -nodes \
  -subj "/CN=registry.example.com"

chmod 600 sp.key
chown www-data:www-data sp.key sp.crt
```

### A3 — Configure SimpleSAMLphp

Edit `/var/www/simplesaml/config/config.php`:

```php
$config = [
    'baseurlpath'    => 'https://registry.example.com/simplesaml/',
    'certdir'        => '/var/www/simplesaml/cert/',
    'loggingdir'     => '/var/www/simplesaml/log/',
    'datadir'        => '/var/www/simplesaml/data/',

    // CRITICAL — must share session with Laravel
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
];
```

> **Important:** `session.cookie.name` must match `SESSION_COOKIE` in Laravel `.env`.

### A4 — Configure SP authsource

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

### A5 — Add IdP metadata

Edit `/var/www/simplesaml/metadata/saml20-idp-remote.php`:

```php
$metadata['https://your-idp.example.org/idp'] = [
    'SingleSignOnService' => [
        [
            'Binding'  => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://your-idp.example.org/idp/SSO/Redirect',
        ],
    ],
    'SingleLogoutService' => [
        [
            'Binding'  => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://your-idp.example.org/idp/SLO/Redirect',
        ],
    ],
    'certData' => 'BASE64_ENCODED_IDP_CERTIFICATE_HERE',
];
```

> Get the IdP certificate from your federation's metadata or IdP administrator.

### A6 — Configure Nginx

This block extends the base Laravel Nginx configuration from [install.md § 5](install.md#5-web-server). Replace the server block from that file with the combined block below, which adds the `/simplesaml/` alias.

```nginx
server {
    listen 443 ssl;
    http2 on;
    server_name registry.example.com;

    ssl_certificate     /etc/nginx/certs/fullchain.pem;
    ssl_certificate_key /etc/nginx/certs/privkey.pem;

    # Laravel — main application
    root /var/www/federation/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass  unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include       fastcgi_params;
    }

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

    location = /health { return 200 "ok"; }
}

server {
    listen 80;
    server_name registry.example.com;
    return 301 https://$host$request_uri;
}
```

### A7 — Install SSP PHP library in Laravel

Laravel uses the SSP PHP API to read the session. The library must also be present in the Laravel project:

```bash
cd /var/www/federation
composer require simplesamlphp/simplesamlphp
```

Add to `.env`:

```ini
SIMPLESAMLPHP_CONFIG_DIR=/var/www/simplesaml/config
```

### A8 — Set permissions

```bash
chown -R www-data:www-data /var/www/simplesaml
chmod -R 775 /var/www/simplesaml/log
chmod -R 775 /var/www/simplesaml/data
chmod    600 /var/www/simplesaml/cert/sp.key

# Laravel session directory (if using file driver)
chmod -R 775 /var/www/federation/storage/framework/sessions
```

---

## Option B — Composer package

SSP is installed as a Composer dependency inside the Laravel project. There is no separate SSP directory. Configuration is kept in a dedicated directory outside `vendor/` so it survives `composer update`.

### B1 — Install SSP as a Composer package

```bash
cd /var/www/federation
composer require simplesamlphp/simplesamlphp
```

SSP web files will be at `vendor/simplesamlphp/simplesamlphp/public/`.

### B2 — Create an external config directory

Do **not** edit files inside `vendor/` — they are overwritten on every `composer update`. Instead, create a dedicated config directory:

```bash
mkdir -p /var/www/federation/ssp-config/{cert,log,data,metadata}

# Copy config templates from the installed package
cp vendor/simplesamlphp/simplesamlphp/config/config.php.dist \
   /var/www/federation/ssp-config/config.php

cp vendor/simplesamlphp/simplesamlphp/config/authsources.php.dist \
   /var/www/federation/ssp-config/authsources.php
```

### B3 — Generate SP certificate

```bash
cd /var/www/federation/ssp-config/cert

openssl req -x509 -newkey rsa:4096 \
  -keyout sp.key -out sp.crt \
  -days 3650 -nodes \
  -subj "/CN=registry.example.com"

chmod 600 sp.key
chown www-data:www-data sp.key sp.crt
```

### B4 — Configure SimpleSAMLphp

Edit `/var/www/federation/ssp-config/config.php`:

```php
$config = [
    'baseurlpath'    => 'https://registry.example.com/simplesaml/',
    'certdir'        => '/var/www/federation/ssp-config/cert/',
    'loggingdir'     => '/var/www/federation/ssp-config/log/',
    'datadir'        => '/var/www/federation/ssp-config/data/',
    'metadatadir'    => '/var/www/federation/ssp-config/metadata/',

    // CRITICAL — must share session with Laravel
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
];
```

> **Important:** `session.cookie.name` must match `SESSION_COOKIE` in Laravel `.env`.

### B5 — Configure SP authsource

Edit `/var/www/federation/ssp-config/authsources.php`:

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

### B6 — Add IdP metadata

Create `/var/www/federation/ssp-config/metadata/saml20-idp-remote.php`:

```php
$metadata['https://your-idp.example.org/idp'] = [
    'SingleSignOnService' => [
        [
            'Binding'  => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://your-idp.example.org/idp/SSO/Redirect',
        ],
    ],
    'SingleLogoutService' => [
        [
            'Binding'  => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://your-idp.example.org/idp/SLO/Redirect',
        ],
    ],
    'certData' => 'BASE64_ENCODED_IDP_CERTIFICATE_HERE',
];
```

### B7 — Configure Nginx

This block extends the base Laravel Nginx configuration from [install.md § 5](install.md#5-web-server). The `/simplesaml/` alias points inside `vendor/`.

```nginx
server {
    listen 443 ssl;
    http2 on;
    server_name registry.example.com;

    ssl_certificate     /etc/nginx/certs/fullchain.pem;
    ssl_certificate_key /etc/nginx/certs/privkey.pem;

    # Laravel — main application
    root /var/www/federation/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass  unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include       fastcgi_params;
    }

    # SimpleSAMLphp — served from vendor/
    location ^~ /simplesaml/ {
        alias /var/www/federation/vendor/simplesamlphp/simplesamlphp/public/;

        location ~ \.php(/|$) {
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            fastcgi_pass  unix:/run/php/php8.4-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/federation/vendor/simplesamlphp/simplesamlphp/public$fastcgi_script_name;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            include       fastcgi_params;
        }

        location ~* \.(js|css|png|jpg|gif|ico|woff2?)$ {
            expires 30d;
            add_header Cache-Control "public";
        }
    }

    location = /health { return 200 "ok"; }
}

server {
    listen 80;
    server_name registry.example.com;
    return 301 https://$host$request_uri;
}
```

### B8 — Set permissions

```bash
chown -R www-data:www-data /var/www/federation/ssp-config
chmod -R 775 /var/www/federation/ssp-config/log
chmod -R 775 /var/www/federation/ssp-config/data
chmod    600 /var/www/federation/ssp-config/cert/sp.key

# Laravel session directory (if using file driver)
chmod -R 775 /var/www/federation/storage/framework/sessions
```

---

## Common steps (both options)

### Configure Laravel session

Edit `.env`:

```ini
SESSION_DRIVER=file
SESSION_COOKIE=laravel_session
SESSION_DOMAIN=registry.example.com
SESSION_SECURE_COOKIE=true
```

> `SESSION_COOKIE` must match `session.cookie.name` in SSP `config.php`.

### Configure Laravel .env

```ini
# SAML2 / SimpleSAMLphp
SAML2_SP_ENTITY_ID=https://registry.example.com/saml2/metadata
SAML2_AUTH_SOURCE=federation-sp
SAML2_BASEURLPATH=/simplesaml/

SAML2_IDP_ENTITY_ID=https://your-idp.example.org/idp
SAML2_IDP_SSO_URL=https://your-idp.example.org/idp/SSO/Redirect
SAML2_IDP_SLS_URL=https://your-idp.example.org/idp/SLO/Redirect
SAML2_IDP_CERT=BASE64_IDP_CERT

# Option A: point to the standalone SSP config directory
SIMPLESAMLPHP_CONFIG_DIR=/var/www/simplesaml/config

# Option B: point to the external config directory inside the Laravel project
# SIMPLESAMLPHP_CONFIG_DIR=/var/www/federation/ssp-config

# Optional — override SAML attribute names if your IdP uses non-standard ones
# SAML2_ATTR_MAIL=mail
# SAML2_ATTR_DISPLAY_NAME=displayName
# SAML2_ATTR_GIVEN_NAME=givenName
# SAML2_ATTR_SURNAME=sn
# SAML2_ATTR_EPPN=eduPersonPrincipalName
```

### Register SP metadata with your IdP

Retrieve your SP metadata from SSP:

```bash
curl https://registry.example.com/simplesaml/module.php/saml/sp/metadata/federation-sp
```

Send this XML to your IdP administrator or federation operator. They register it in their system — after that, SAML login will work.

### Verify the integration

```bash
# 1. Check SSP is reachable
curl -I https://registry.example.com/simplesaml/

# 2. Check SP metadata is generated
curl https://registry.example.com/simplesaml/module.php/saml/sp/metadata/federation-sp

# 3. Test Laravel can read SSP session
php artisan tinker
>>> app(\App\Services\Auth\SamlServiceInterface::class)->isAuthenticated()
# Returns false (not logged in) — no error means SSP API is working

# 4. Attempt login via browser
# Visit https://registry.example.com/login
# Click "Login with institutional account"
# Should redirect to IdP login page
```

---

## Laravel routes summary

| Method | URL | Controller | Purpose |
|---|---|---|---|
| GET | `/login` | `SamlAuthController@showLogin` | Login page (both options) |
| POST | `/login` | `SamlAuthController@localLogin` | Local email/password login |
| GET | `/saml/login` | `SamlAuthController@login` | Redirect to SSP / IdP |
| POST | `/saml/acs` | `SamlAuthController@acs` | Handle SSP callback |
| GET | `/logout` | `SamlAuthController@logout` | Logout from both sessions |

---

## User provisioning

### Required and recommended attributes

| Attribute | SAML name | Required | Purpose |
|---|---|---|---|
| `mail` | `urn:oid:0.9.2342.19200300.100.1.3` or `mail` | **Yes** | User email address — login aborted (HTTP 422) if missing |
| `displayName` | `urn:oid:2.16.840.1.113730.3.1.241` or `displayName` | **Yes** (or givenName+sn) | Display name shown in the UI |
| `givenName` + `sn` | `urn:oid:2.5.4.42` + `urn:oid:2.5.4.4` | **Yes** (if no displayName) | Combined as fallback display name — login aborted if neither present |
| `eduPersonPrincipalName` | `urn:oid:1.3.6.1.4.1.5923.1.1.1.7` or `eppn` | Recommended | Persistent identity identifier; stored as `saml_id` — falls back to `mail` when not released |

> **Note:** The application is the SAML SP. Configure your IdP to release at minimum `mail` and one of `displayName` / `givenName`+`sn`. Releasing `eduPersonPrincipalName` is strongly recommended because it survives email address changes.

### User lookup and provisioning logic

On every SAML login the controller performs a three-step lookup:

1. **saml_id match** — looks up the user by `saml_id` (set from ePPN or mail). This is the primary path for returning users.
2. **email match + link** — if no `saml_id` match, looks up by email. On a hit the `saml_id` is stored on the existing account so future logins use step 1. Enables local users to log in via SAML without losing their account.
3. **Create new user** — if neither matches, a new account is created with a random password. The role defined in **Settings → Default SAML role** (default: `Guest`) is assigned. An admin-notification email is dispatched.

### Role and attribute notes

- **New users** → assigned the configured default SAML role (default: `Guest`)
- **Existing local users** → role preserved; `saml_id` linked on first SAML login
- **Local-only users** → created manually by Admin, any role assignable; can also authenticate via SAML if their email matches

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Login redirects to SSP then back to /login | Session cookie mismatch | Verify `session.cookie.name` in SSP = `SESSION_COOKIE` in `.env` |
| `RuntimeException: SimpleSAMLphp is not installed` | SSP library missing (Option A) | Run `composer require simplesamlphp/simplesamlphp` in the Laravel project |
| `SIMPLESAMLPHP_CONFIG_DIR not set` | Env variable missing | Add `SIMPLESAMLPHP_CONFIG_DIR=...` to `.env` |
| Blank page at `/simplesaml/` | Nginx alias misconfigured | Check `alias` path ends with `/`; verify SSP public path is correct for your option |
| `Certificate error` during SSO | Wrong IdP cert in metadata | Update `certData` in `saml20-idp-remote.php` |
| Config changes ignored (Option B) | Edited files inside `vendor/` | Edit `ssp-config/config.php` instead; check `SIMPLESAMLPHP_CONFIG_DIR` points there |
| SSP config reset after `composer update` (Option B) | Config was in `vendor/` | Ensure `SIMPLESAMLPHP_CONFIG_DIR` points to `ssp-config/`, not inside `vendor/` |
| User created but role is wrong | Default role not set | Check `RolesAndPermissionsSeeder` ran — `Guest` role must exist |
| `tempnam(): file created in system temp` | Storage permission issue | `chmod 775 storage/framework/sessions` |
| SSP log empty / no errors visible | Wrong log directory | Verify `loggingdir` in `config.php` is writable by `www-data` |

---

## Security checklist

- [ ] `sp.key` permissions set to `600` — never world-readable
- [ ] `APP_DEBUG=false` in production `.env`
- [ ] SSP `secretsalt` changed from default
- [ ] SSP admin password changed from default
- [ ] IdP certificate verified out-of-band before adding to metadata
- [ ] SSL certificate valid and HTTPS enforced
- [ ] `SESSION_SECURE_COOKIE=true` in `.env`
- [ ] `/simplesaml/` not accessible without HTTPS
- [ ] Option B: `ssp-config/` directory is outside `vendor/` and backed up separately
