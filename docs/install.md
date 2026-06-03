# Installation Guide

Federation Registry — Laravel 13 application.

---

## Table of Contents

1. [System Requirements](#1-system-requirements)
2. [Ubuntu 22.04 / 24.04](#2-ubuntu-2204--2404)
3. [CentOS / RHEL 9](#3-centos--rhel-9)
4. [Application Installation](#4-application-installation)
5. [Web Server](#5-web-server)
6. [Queue Worker](#6-queue-worker)
7. [Scheduler (Cron)](#7-scheduler-cron)
8. [SAML Authentication](#8-saml-authentication)
9. [Metadata Signing (xmlsectool)](#9-metadata-signing-xmlsectool)
10. [Verification](#10-verification)

---

## 1. System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| PHP | 8.3 | 8.4 |
| MySQL | 8.0 | 8.0+ |
| Redis | 7.0 | 7.2+ |
| Node.js | 20 LTS | 22 LTS |
| Composer | 2.x | latest |
| Java | 11 (xmlsectool only) | 17+ |

**Required PHP extensions:**
`pdo_mysql`, `redis` (phpredis), `openssl`, `mbstring`, `xml`, `dom`,
`simplexml`, `curl`, `zip`, `bcmath`, `tokenizer`, `ctype`, `fileinfo`, `json`

---

## 2. Ubuntu 22.04 / 24.04

### System packages

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl git unzip zip software-properties-common
```

### PHP 8.4

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y \
  php8.4 php8.4-fpm php8.4-cli \
  php8.4-mysql php8.4-redis php8.4-xml php8.4-curl \
  php8.4-mbstring php8.4-zip php8.4-bcmath \
  php8.4-tokenizer php8.4-ctype php8.4-dom
```

Verify:
```bash
php -v          # PHP 8.4.x
php -m | grep -E "pdo_mysql|redis|openssl|mbstring|xml|curl"
```

### MySQL 8

```bash
sudo apt install -y mysql-server
sudo systemctl enable --now mysql
sudo mysql_secure_installation
```

Create database and user:
```sql
CREATE DATABASE federation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'federation'@'localhost' IDENTIFIED BY 'strong-password-here';
GRANT ALL PRIVILEGES ON federation.* TO 'federation'@'localhost';
FLUSH PRIVILEGES;
```

### Redis 7

```bash
sudo apt install -y redis-server
sudo systemctl enable --now redis-server
redis-cli ping    # should return PONG
```

### Node.js 22

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v
```

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable --now nginx
```

### Java (xmlsectool only)

```bash
sudo apt install -y openjdk-17-jre-headless
java -version
```

---

## 3. CentOS / RHEL 9

### System packages

```bash
sudo dnf update -y
sudo dnf install -y curl git unzip zip
sudo dnf install -y epel-release
```

### PHP 8.4 (Remi repository)

```bash
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.4 -y
sudo dnf install -y \
  php php-fpm php-cli \
  php-mysqlnd php-redis php-xml php-curl \
  php-mbstring php-zip php-bcmath \
  php-tokenizer php-ctype php-dom
```

Verify:
```bash
php -v
php -m | grep -E "pdo_mysql|redis|openssl|mbstring|xml|curl"
```

Configure PHP-FPM to run as the web user:
```bash
# Edit /etc/php-fpm.d/www.conf
sudo sed -i 's/^user = apache/user = nginx/' /etc/php-fpm.d/www.conf
sudo sed -i 's/^group = apache/group = nginx/' /etc/php-fpm.d/www.conf
sudo systemctl enable --now php-fpm
```

### MySQL 8

```bash
sudo dnf install -y https://dev.mysql.com/get/mysql80-community-release-el9-1.noarch.rpm
sudo dnf install -y mysql-community-server
sudo systemctl enable --now mysqld

# Get temporary root password
sudo grep 'temporary password' /var/log/mysqld.log
mysql_secure_installation
```

Create database and user:
```sql
CREATE DATABASE federation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'federation'@'localhost' IDENTIFIED BY 'strong-password-here';
GRANT ALL PRIVILEGES ON federation.* TO 'federation'@'localhost';
FLUSH PRIVILEGES;
```

### Redis 7

```bash
sudo dnf install -y redis
sudo systemctl enable --now redis
redis-cli ping    # should return PONG
```

### Node.js 22

```bash
curl -fsSL https://rpm.nodesource.com/setup_22.x | sudo bash -
sudo dnf install -y nodejs
node -v && npm -v
```

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### Nginx

```bash
sudo dnf install -y nginx
sudo systemctl enable --now nginx
```

### SELinux (if enabled)

Allow nginx to connect to PHP-FPM and network:
```bash
sudo setsebool -P httpd_can_network_connect 1
sudo setsebool -P httpd_execmem 1
```

### Java (xmlsectool only)

```bash
sudo dnf install -y java-17-openjdk-headless
java -version
```

---

## 4. Application Installation

### Clone the repository

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone https://github.com/pocomd/federations-manager.git federations-manager
sudo chown -R www-data:www-data /var/www/federations-manager   # Ubuntu
# sudo chown -R nginx:nginx /var/www/federations-manager       # CentOS
```

### PHP dependencies

```bash
cd /var/www/federations-manager
composer install --no-dev --optimize-autoloader
```

### Environment file

```bash
cp .env.example .env
```

Edit `.env` and set at minimum:

```dotenv
APP_NAME="Federation Registry"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://registry.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=federation
DB_USERNAME=federation
DB_PASSWORD=strong-password-here

CACHE_STORE=redis
QUEUE_CONNECTION=database

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

SESSION_DRIVER=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=registry@example.com
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=registry@example.com
MAIL_FROM_NAME="Federation Registry"

# Federation-specific
FEDERATION_REGISTRATION_AUTHORITY=https://registry.example.com
FEDERATION_SIGNING_KEY=/etc/federation/signing.key
FEDERATION_SIGNING_CERT=/etc/federation/signing.crt
XMLSECTOOL_PATH=/usr/local/bin/xmlsectool
```

### Generate application key

```bash
php artisan key:generate
```

### Run database migrations

```bash
php artisan migrate --force
```

### Seed initial data

Seeds roles, permissions, scheduler defaults, system preferences, attribute definitions, mail templates, and notification types:

```bash
php artisan db:seed --force
```

### Sync metadata compliance rules

Registers all compliance rule definitions (REFEDS, eduGAIN validation) into the database:

```bash
php artisan rules:sync
```

### Build frontend assets

```bash
npm ci
npm run build
```

### Storage symlink

```bash
php artisan storage:link
```

### File permissions

```bash
# Ubuntu
sudo chown -R www-data:www-data /var/www/federations-manager/storage
sudo chown -R www-data:www-data /var/www/federations-manager/bootstrap/cache
sudo chmod -R 775 /var/www/federations-manager/storage
sudo chmod -R 775 /var/www/federations-manager/bootstrap/cache

# CentOS
sudo chown -R nginx:nginx /var/www/federations-manager/storage
sudo chown -R nginx:nginx /var/www/federations-manager/bootstrap/cache
sudo chmod -R 775 /var/www/federations-manager/storage
sudo chmod -R 775 /var/www/federations-manager/bootstrap/cache
```

### Optimise for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 5. Web Server

### Nginx configuration

Create `/etc/nginx/sites-available/federations-manager` (Ubuntu) or
`/etc/nginx/conf.d/federations-manager.conf` (CentOS):

```nginx
server {
    listen 80;
    server_name registry.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name registry.example.com;

    root /var/www/federations-manager/public;
    index index.php;

    ssl_certificate     /etc/ssl/certs/registry.crt;
    ssl_certificate_key /etc/ssl/private/registry.key;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    client_max_body_size 32M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass   unix:/run/php/php8.4-fpm.sock;  # Ubuntu
        # fastcgi_pass unix:/run/php-fpm/www.sock;     # CentOS
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include        fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Metadata feed — allow unauthenticated external access
    location ~ ^/metadata/.+/feed$ {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

Enable and reload (Ubuntu):
```bash
sudo ln -s /etc/nginx/sites-available/federations-manager /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Reload (CentOS):
```bash
sudo nginx -t && sudo systemctl reload nginx
```

> **SimpleSAMLphp users:** the block above covers the Laravel application only. After completing the steps above, extend this config with the `/simplesaml/` location block described in [simplesamlphp-deployment.md § Step 6](simplesamlphp-deployment.md#step-6--configure-nginx).

---

## 6. Queue Worker

### What it does and why it must run

The application does not process background tasks inline with web requests.
Instead it places jobs into a database queue and relies on a persistent worker
process to pick them up and execute them.

| Job | What happens without the worker |
|-----|----------------------------------|
| **Metadata generation** | Federation metadata XML is never rebuilt — feeds go stale and eventually expire |
| **Certificate monitoring** | Expiry warnings are never sent to entity operators |
| **Webhook delivery** | Subscriber URLs stop receiving `metadata.generated`, `entity.approved`, and other events |
| **Entity validation** | SAML compliance checks against the REFEDS/eduGAIN rule set never run |
| **eduGAIN sync** | The upstream eduGAIN aggregate is never pulled |
| **Scheduler heartbeat** | The `/health` endpoint reports **"No heartbeat — cron may not be running"** even if cron is fine |

**The queue worker is as critical as the web server.** It must start on boot and
restart automatically on failure.

---

### Option A — systemd (recommended)

A ready-made unit file is included in the repository:

```bash
sudo cp /var/www/federations-manager/deploy/federations-management.service /etc/systemd/system/federations-management.service
```

If your install path or web user differs, edit the `User=`, `Group=`, and
`WorkingDirectory=` lines before continuing.

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now federations-management
sudo systemctl status federations-management
```

Check the worker log:

```bash
sudo journalctl -u federations-management -f
# or
tail -f /var/www/federations-manager/storage/logs/worker.log
```

---

### Option B — Supervisor

Install Supervisor:
```bash
# Ubuntu
sudo apt install -y supervisor

# CentOS
sudo dnf install -y supervisor
```

Create `/etc/supervisor/conf.d/federation-worker.conf`:

```ini
[program:federation-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/federations-manager/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/federations-manager/storage/logs/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
user=www-data       ; Ubuntu — change to nginx for CentOS
```

Load and start:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start federation-worker:*
sudo supervisorctl status
```

---

### Deploying updates

After any application update, signal the worker to restart gracefully so it
picks up the new code without dropping in-flight jobs:

```bash
php artisan queue:restart
```

The worker finishes any job it is currently processing, then exits. systemd or
Supervisor will start a fresh process automatically.

---

## 7. Scheduler (Cron)

The Laravel scheduler must run every minute. Add this entry to the system crontab:

```bash
sudo crontab -e -u www-data    # Ubuntu
# sudo crontab -e -u nginx     # CentOS
```

Add the line:
```
* * * * * cd /var/www/federations-manager && php artisan schedule:run >> /dev/null 2>&1
```

**What the scheduler runs:**

| Schedule | Task |
|----------|------|
| Every minute | Scheduler heartbeat (health check signal) |
| Every 5 minutes | Laravel Horizon snapshot |
| Every 15 min (configurable) | Auto-generate signed metadata for active federations |
| Every 6 hours (configurable) | eduGAIN metadata sync |
| Daily at 06:00 | Certificate expiry check and notifications |
| Sunday at 03:00 | Full entity metadata validation |
| Sunday at 04:00 | Cleanup of old validation results, audit logs, metadata cache |

Timing and enable/disable per task is configurable from the **Admin → Scheduler** page in the UI.

---

## 8. SAML Authentication

The application supports dual login: SAML2 via SimpleSAMLphp and local email/password. SAML is optional — local login works without it.

### SimpleSAMLphp setup

1. Follow [simplesamlphp-deployment.md](simplesamlphp-deployment.md) for the full SSP setup (Option A — standalone or Option B — Composer package)
2. Set the following in `.env`:

```dotenv
# SSP location (set by simplesamlphp-deployment.md)
# Option A: SIMPLESAMLPHP_CONFIG_DIR=/var/www/simplesaml/config
# Option B: SIMPLESAMLPHP_CONFIG_DIR=/var/www/federation/ssp-config

SAML2_SP_ENTITY_ID=https://registry.example.com/saml2/metadata
SAML2_BASEURLPATH=/simplesaml/
SAML2_AUTH_SOURCE=federation-sp

SAML2_IDP_ENTITY_ID=https://idp.example.com/idp/shibboleth
SAML2_IDP_SSO_URL=https://idp.example.com/idp/profile/SAML2/Redirect/SSO
SAML2_IDP_SLS_URL=https://idp.example.com/idp/profile/SAML2/Redirect/SLO
SAML2_IDP_CERT=MIIDXTCCAkWg...     # Base64 IdP certificate, no headers

# Optional — attribute name overrides (defaults shown)
# SAML2_ATTR_MAIL=mail
# SAML2_ATTR_DISPLAY_NAME=displayName
# SAML2_ATTR_GIVEN_NAME=givenName
# SAML2_ATTR_SURNAME=sn
# SAML2_ATTR_EPPN=eduPersonPrincipalName
```

### Required IdP attributes

Configure your IdP to release these attributes to the SP:

| Attribute | SAML name | Required |
|---|---|---|
| Email address | `mail` | **Yes** — login aborted if missing |
| Display name | `displayName` | **Yes** (or givenName + sn) |
| First + last name | `givenName` + `sn` | **Yes** if no `displayName` |
| ePPN | `eduPersonPrincipalName` | Recommended — persistent user identity |

> Releasing `eduPersonPrincipalName` is strongly recommended. Without it the application falls back to `mail` as the persistent identity, which breaks the account link if the user's email changes.

See [simplesamlphp-deployment.md § User provisioning](simplesamlphp-deployment.md#user-provisioning) for full provisioning and account-linking details.

When SAML is not configured, only local email/password login is available.

---

## 9. Metadata Signing (xmlsectool)

Required for generating signed federation metadata. Skip this section if you do not need signed metadata output.

### Install xmlsectool

```bash
cd /tmp
curl -LO https://shibboleth.net/downloads/tools/xmlsectool/latest/xmlsectool-3.0.0-bin.zip
unzip xmlsectool-3.0.0-bin.zip
sudo mv xmlsectool-3.0.0 /opt/xmlsectool
sudo ln -s /opt/xmlsectool/xmlsectool.sh /usr/local/bin/xmlsectool
sudo chmod +x /usr/local/bin/xmlsectool
xmlsectool --version
```

### Signing key and certificate

Generate a self-signed certificate for signing (replace with your CA-issued cert in production):

```bash
sudo mkdir -p /etc/federation
sudo openssl req -x509 -nodes -newkey rsa:4096 -days 3650 \
  -keyout /etc/federation/signing.key \
  -out /etc/federation/signing.crt \
  -subj "/CN=Federation Registry Metadata Signing"
sudo chmod 600 /etc/federation/signing.key
sudo chown www-data:www-data /etc/federation/signing.*   # adjust for CentOS
```

Set paths in `.env`:

```dotenv
XMLSECTOOL_PATH=/usr/local/bin/xmlsectool
FEDERATION_SIGNING_KEY=/etc/federation/signing.key
FEDERATION_SIGNING_CERT=/etc/federation/signing.crt
```

---

## 10. Verification

### Environment validation

Checks all required env vars, database connectivity, and Redis:

```bash
php artisan app:validate-env
```

### Full self-test

Runs six live checks (database, Redis, xmlsectool, scheduler heartbeat, certificate parser, queue worker) and prints a colour-coded table:

```bash
php artisan app:selftest
```

To skip the queue round-trip check:

```bash
php artisan app:selftest --skip-queue
```

### Health endpoint

Returns JSON status; responds 503 if any check fails:

```bash
curl -s https://registry.example.com/health | jq .
```

Optionally protect it with a bearer token:

```dotenv
HEALTH_CHECK_TOKEN=your-secret-token
```

```bash
curl -s -H "Authorization: Bearer your-secret-token" https://registry.example.com/health
```

### UI health page

Log in as an Admin and navigate to **Admin → System Health** for a live browser view of all checks with descriptions and remediation guidance. Can be disabled with `HEALTH_UI_ENABLED=false`.

### First admin user

After installation, create the first admin account via the registration form on the login page (invitation required) or directly in the database:

```bash
php artisan tinker
```

```php
$user = \App\Models\User::create([
    'name'     => 'Admin',
    'email'    => 'admin@example.com',
    'password' => bcrypt('change-me'),
    'status'   => 'active',
]);
$user->assignRole('Admin');
```

---

## Feature Flags

| Variable | Default | Description |
|----------|---------|-------------|
| `HEALTH_UI_ENABLED` | `true` | Show System Health page in admin UI |
| `HEALTH_CHECK_TOKEN` | _(unset)_ | Bearer token for `GET /health` (open if unset) |
| `JAGGER_IMPORT_ENABLED` | `false` | Enable Import from Jagger UI |
| `I18N_ENABLED` | `false` | Enable language switcher |
| `FEDERATION_REGISTRATION_AUTHORITY` | _(unset)_ | URI used in metadata `registrationAuthority` |
