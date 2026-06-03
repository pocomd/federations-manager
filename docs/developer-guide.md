# Federation Manager — Developer & Maintainer Guide

> **Audience:** Engineers joining the project, operators maintaining the production
> system, and anyone diagnosing an issue in the codebase.  
> **Stack:** Laravel 13 · PHP 8.4 · MySQL 8 · Redis · Livewire 4 · Alpine.js · Bootstrap 5

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Prerequisites & Tech Stack](#2-prerequisites--tech-stack)
3. [Local Development Setup](#3-local-development-setup)
4. [Architecture Overview](#4-architecture-overview)
5. [Authentication](#5-authentication)
6. [Roles & Permissions (RBAC)](#6-roles--permissions-rbac)
7. [Core Domain: Entities](#7-core-domain-entities)
8. [Core Domain: Federations](#8-core-domain-federations)
9. [Metadata Pipeline](#9-metadata-pipeline)
10. [Compliance Rule Engine](#10-compliance-rule-engine)
11. [Certificate Monitoring](#11-certificate-monitoring)
12. [Notification System](#12-notification-system)
13. [Webhook System](#13-webhook-system)
14. [Discovery Endpoints (JEDI / WebFinger)](#14-discovery-endpoints-jedi--webfinger)
15. [OIDC Client Support](#15-oidc-client-support)
16. [Attribute Registry & ARP](#16-attribute-registry--arp)
17. [Mail System](#17-mail-system)
18. [Invitations & Self-Registration](#18-invitations--self-registration)
19. [Statistics & Reporting](#19-statistics--reporting)
20. [eduGAIN Integration](#20-edugain-integration)
21. [Import from Jagger](#21-import-from-jagger)
22. [Internationalisation](#22-internationalisation)
23. [Scheduler & Background Jobs](#23-scheduler--background-jobs)
24. [Queue Workers (Horizon)](#24-queue-workers-horizon)
25. [Artisan Commands](#25-artisan-commands)
26. [Testing](#26-testing)
27. [Known Bug Patterns & Gotchas](#27-known-bug-patterns--gotchas)
28. [Maintenance Runbook](#28-maintenance-runbook)
29. [Deployment Notes](#29-deployment-notes)

---

## 1. Project Overview

Federation Manager replaces Jagger (ResourceRegistry3), HEAnet's legacy PHP/Kohana
federation registry. It manages SAML2 entities (Identity Providers and Service Providers)
for an NREN federation, generates signed metadata XML, monitors certificate health,
enforces REFEDS/eduGAIN compliance rules, and provides a modern operator UI.

**What Jagger lacked — and this app provides:**

| Capability | Jagger | This app |
|---|---|---|
| API-first metadata | No | JEDI + WebFinger + MDQ |
| REFEDS compliance checking | Manual | 34 automated rules |
| Certificate monitoring | None | Dashboard + expiry alerts |
| OIDC client registration | No | Full OIDC entity type |
| eduGAIN upstream sync | No | Scheduled XMLReader sync |
| Notification system | None | In-app bell + email |
| Webhook delivery | None | HMAC-signed HTTP callbacks |
| Multi-role RBAC | 1 admin role | 4-role model |
| Self-registration | No | Invitation flow |

---

## 2. Prerequisites & Tech Stack

| Layer | Choice | Notes |
|---|---|---|
| Framework | Laravel 13 | PHP 8.4 minimum |
| Database | MySQL 8.0 | utf8mb4, strict mode on |
| Cache / Queue | Redis 7 | Sessions also on Redis in production |
| Web server | Nginx | PHP-FPM |
| Queue worker | Laravel Horizon | Linux only (ext-pcntl) |
| Scheduler | Crontab → `php artisan schedule:run` | Every minute |
| Frontend | Livewire 4 + Bootstrap 5 + Alpine.js | No separate JS build needed for Alpine |
| XML signing | xmlsectool 3.0.0 (Java CLI) | Optional — signing key required |
| XML parsing | PHP DOMDocument + DOMXPath | Built-in |
| Auth | SimpleSAMLphp via aacotroneo/laravel-saml2 | Production only |
| Permissions | Spatie Laravel Permission v6 | Redis-cached |
| Testing | Pest 3 | MySQL test DB required for feature tests |
| Build | Vite + npm | Bootstrap Icons via CDN (not Vite bundle) |

**Required PHP extensions:** `pdo_mysql`, `mbstring`, `xml`, `openssl`, `redis`, `pcntl` (Linux/production)

---

## 3. Local Development Setup

### 3.1 Clone & install

```bash
git clone https://github.com/pocomd/federations-manager.git federations-manager
cd federations-manager
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

### 3.2 Configure `.env`

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=next
DB_USERNAME=next
DB_PASSWORD=<password>

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database        # use 'redis' on production with Horizon

# Optional — required only for signed metadata output
FEDERATION_SIGNING_KEY=/path/to/signing.key
FEDERATION_SIGNING_CERT=/path/to/signing.crt
XMLSECTOOL_PATH=/usr/local/bin/xmlsectool
```

### 3.3 Database setup

```bash
# Create test database (feature tests require it)
mysql -u root -p -e "CREATE DATABASE next_test; GRANT ALL ON next_test.* TO 'next'@'localhost';"

# Migrate + seed development data
php artisan migrate
php artisan db:seed
php artisan db:seed --class=DevelopmentSeeder   # dev fixtures only

# Sync compliance rules into rule_definitions table
php artisan rules:sync
```

### 3.4 Dev login credentials

| Email | Password | Role |
|---|---|---|
| `admin@example.com` | `password` | Admin |
| `operator@example.com` | `password` | Federation Manager |

> DevelopmentSeeder creates these users. Do not use `password123`; seeder
> uses `Hash::make('password')`.

### 3.5 Queue worker (local)

With `QUEUE_CONNECTION=database` no daemon is needed — jobs execute synchronously
or you can run:

```bash
php artisan queue:work --queue=high,default,low
```

### 3.6 Scheduler (local)

```bash
php artisan schedule:run   # run once manually
# or keep a tinker session open and call individual jobs directly
```

---

## 4. Architecture Overview

### 4.1 Request flow

```
Browser ──HTTP──► Nginx ──FastCGI──► PHP-FPM
                                       │
                              bootstrap/app.php
                                       │
                         EnsureAuthenticated middleware
                                       │
                        ┌──────────────┴──────────────┐
                        │                             │
                  Blade + Livewire              API controllers
                  (full-page requests)          (JSON responses)
                        │
              Livewire component
              ↕ wire:model / wire:click
              Alpine.js (client state)
```

### 4.2 Directory layout

```
app/
  Console/Commands/       Artisan commands (3)
  Exceptions/             Custom exceptions
  Http/
    Controllers/          34 controllers (see §7–§19)
    Middleware/           EnsureAuthenticated, SetLocale
    Requests/             Form request validation (6)
  Jobs/                   8 queued/dispatchable jobs
  Livewire/               9 Livewire components
  Mail/                   2 Mailable classes
  Models/                 36 Eloquent models
  Observers/              EntityObserver (audit + cache bust)
  Policies/               EntityPolicy (isManager static helper)
  Providers/              AppServiceProvider (observer, RuleRegistry)
  Services/
    Auth/                 SAML + Invitation
    EduGain/              eduGAIN API
    Entity/               Metadata, Import, Certificates, Validation
    Mail/                 Template rendering + send
    Metadata/             Rule engine + 34 rule classes
    Notification/         In-app + email notification dispatch
    Webhook/              Endpoint dispatch

config/
  federation.php          Signing keys, xmlsectool path, reg authority

database/
  migrations/             49 migrations
  seeders/                10 seeders (DatabaseSeeder + DevelopmentSeeder)
  jagger/                 JaggerImporter.php migration helper

routes/
  web.php                 ~160 web routes (auth-guarded + public)
  api.php                 4 API routes (MDQ, export, JEDI, OIDC config)
  console.php             Scheduled job bindings

resources/
  views/                  78 Blade templates
  lang/en/ lang/ro/       Translation files
  css/ js/                Vite entrypoints
```

### 4.3 Frontend conventions

- **Classic Livewire only** — never Volt single-file components.
- **Alpine.js** is auto-bundled by Livewire 4. Never import Alpine manually.
- **`@click` must be an expression**, not a block statement. Move complex JS into
  `x-data` methods.
- **`navigator.clipboard`** is forbidden in Blade — requires HTTPS. Use
  DOM textarea + `document.execCommand('copy')`.
- **`wire:click` with UUIDs** — always wrap UUID in single quotes:
  `wire:click="method('{{ $model->id }}')"`.
- **Bootstrap Icons** loaded via CDN `<link>` in `<head>`, not via Vite.

---

## 5. Authentication

Two independent authentication paths coexist on `/login`:

### 5.1 Institutional login (SimpleSAMLphp)

- Button → `GET /saml/login` → `SamlAuthController::login()` → SSP redirect to IdP
- IdP posts SAML assertion to `POST /saml/acs` → `SamlAuthController::acs()`
- `findOrCreateUser()`:
  - New user → assigned default role from `SystemPreference::get('default_saml_role', 'Guest')`
  - Existing user → role preserved; `name` + `email` updated from assertion
- Session key `login_type = 'saml'` set for correct logout routing.

### 5.2 Local login (fallback for admin when IdP is down)

- Form → `POST /login` → `SamlAuthController::localLogin()` → `Auth::attempt()`
- Session key `login_type = 'local'`
- Local users are created manually by Admin via `/users`

### 5.3 Logout

`POST /logout` reads `session('login_type')`:
- `'local'` → `Auth::logout()` + redirect to `/login`
- `'saml'` → `Auth::logout()` then `SimpleSAML\Auth\Simple::logout()` (IdP SLO)

### 5.4 Middleware: `EnsureAuthenticated`

Checks `Auth::check()` OR a live SimpleSAMLphp session. Unauthenticated requests
redirect to `/login`. Applied to all routes except:
- Auth routes (`/login`, `/saml/*`, `/logout`)
- Public metadata feeds (`/metadata/{federation}/feed`, `/metadata/{federation}/edugain`)
- Invitation registration (`/register/{token}`)
- WebFinger (`/.well-known/webfinger`)
- API endpoints (use separate throttle middleware)

---

## 6. Roles & Permissions (RBAC)

Powered by **Spatie Laravel Permission v6** with a Redis-backed cache.

### 6.1 Four roles

| Role | Description |
|---|---|
| **Admin** | All 25 permissions. System administration. |
| **Federation Manager** | Manages assigned federations + their entities. 14 permissions. |
| **Entity Manager** | Creates and manages their own entities. 7 permissions. |
| **Guest** | Default for new SAML users. Can view metadata only. 1 permission. |

### 6.2 Permission matrix

| Permission | Admin | FM | EM | Guest |
|---|:---:|:---:|:---:|:---:|
| `entity.view` | ✓ | ✓ | ✓ | |
| `entity.create` | ✓ | | ✓ | |
| `entity.edit` | ✓ | ✓ | ✓ | |
| `entity.delete` | ✓ | | | |
| `entity.addToFederation` | ✓ | ✓ | | |
| `entity.removeFromFederation` | ✓ | ✓ | | |
| `entity.submitForFederation` | ✓ | | ✓ | |
| `entity.requestContactInvitation` | ✓ | | ✓ | |
| `federation.view` | ✓ | ✓ | | |
| `federation.create` | ✓ | | | |
| `federation.edit` | ✓ | ✓ | | |
| `federation.approveRequest` | ✓ | ✓ | | |
| `federation.rejectRequest` | ✓ | ✓ | | |
| `metadata.generate` | ✓ | ✓ | | |
| `metadata.sign` | ✓ | ✓ | | |
| `metadata.view` | ✓ | ✓ | ✓ | ✓ |
| `user.view/create/edit/delete` | ✓ | | | |
| `user.invite` | ✓ | ✓ | | |
| `invitation.manage` | ✓ | ✓ | | |
| `arp.view/edit` | ✓ | | | |
| `compliance.view` | ✓ | ✓ | ✓ | |

### 6.3 Admin-only gate: `federation.create`

System-administration pages (Scheduler, Preferences, Webhooks, Mail Templates,
Compliance Rules) are gated on `federation.create`, which **only Admin holds**.
`federation.edit` (shared with FM) must **not** be used as an Admin-only gate.

### 6.4 Federation Manager scoping

FM users are scoped to specific federations via the `federation_managers` pivot table.
`FederationController::index()` and `FederationManager::federations()` both filter
`whereHas('managers', fn($q) => $q->where('user_id', Auth::id()))` for FM users.
Admins see all federations.

### 6.5 Resetting permissions

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
```

---

## 7. Core Domain: Entities

An **Entity** represents one SAML2 participant: an IdP, SP, or OIDC client.

### 7.1 Entity model (`app/Models/Entity.php`)

Key fields:

| Field | Type | Notes |
|---|---|---|
| `id` | uuid v7 | Primary key |
| `entity_id` | varchar(255) UNIQUE | SAML entityID URI |
| `type` | enum(idp, sp, oidc) | |
| `status` | enum(draft, pending, active, suspended, deleted) | |
| `edugain` | boolean | Include in eduGAIN feed |
| `scope` | varchar(255) | IdP shibboleth scope |
| `nameid_formats` | json | Array of NameID format URNs |
| `requested_attributes` | json | SP requested attributes (raw JSON) |
| `sha1_entity_id` | varchar(40) | For MDQ lookups |
| `source` | enum(manual, edugain, imported) | Origin |

**CRITICAL** — accessor collision: `getRequestedAttributesAttribute()` is defined
on Entity. The relationship for the `entity_requested_attributes` table must be named
`entityRequestedAttributes()`, NOT `requestedAttributes()`.

### 7.2 Entity lifecycle

```
draft ──submit──► pending ──FM approves──► active ──EntitySuspendModal──► suspended ──destroy──► (trashed)
  │                                                                              │
  └──destroy (any non-active)──────────────────────────────────────────►(trashed)──restore──► suspended
                                                                                         └──force-delete──► gone
```

- **draft**: created, not yet submitted to any federation.
- **pending**: submitted to a federation (or imported), awaiting FM approval.
- **active**: approved by FM, included in metadata feeds.
- **suspended**: removed from feeds; not deleted. Must be reactivated manually.
- **trashed (soft-deleted)**: hidden from all lists. Restore always returns to `suspended`.

### 7.3 Creating an entity

1. User fills `EntityForm` Livewire component (7 tabs: Basic, Endpoints, Certificates,
   REFEDS Attributes, Requested Attrs, Languages, OIDC).
2. EM users must pass the **Validation Gate** (`runValidationGate()`) before saving.
3. `EntityController::store()` runs in a DB transaction, creates Entity + all child records,
   writes an `EntityManager` owner row for the creator, fires `EntityObserver::created()`
   (AuditLog + webhook).
4. If the creating user is EM, `NotificationService::dispatch('entity_pending_approval')`
   is called so Federation Managers receive an in-app notification.

### 7.4 Entity child tables

| Table | Relation on Entity | Purpose |
|---|---|---|
| `entity_ui_info` | `uiInfo()` HasMany | Multilingual display name, description, logo, org info |
| `entity_endpoints` | `endpoints()` HasMany | SSO, ACS, SLO, Artifact endpoints |
| `entity_certificates` | `certificates()` HasMany | X.509 certs (signing/encryption) |
| `entity_contacts` | `contacts()` HasMany | Technical, support, security, admin, billing |
| `entity_attributes` | `attributes()` HasMany | Entity category + assurance profile URIs |
| `entity_requested_attributes` | `entityRequestedAttributes()` HasMany | SP requested attrs |
| `entity_arp` | `arpReleases()` / `arpRestrictions()` HasMany | Attribute release policy |
| `entity_rule_config` | `ruleConfigs()` HasMany | Per-entity rule overrides |
| `entity_oidc_config` | `oidcConfig()` HasOne | OIDC client config (type=oidc only) |
| `entity_validation_results` | (written by jobs) | Historical validation output |
| `entity_managers` | `managers()` BelongsToMany | Owner/co-manager users |

### 7.5 Importing entities

Three paths exist:
- **Manual form** — `EntityController::store()` via `EntityForm` Livewire
- **XML import** — `EntityImportController` + `EntityImportService::fromXml()`
- **JSON array import** — `EntityImportController` + `EntityImportService::fromArray()`
- **Jagger migration** — `JaggerImportCommand` + `JaggerImporter` (bulk, offline)

---

## 8. Core Domain: Federations

A **Federation** groups entities under a shared registration authority URI.

### 8.1 Federation model (`app/Models/Federation.php`)

Key relations:

```php
entities()          BelongsToMany via entity_federation (pivot)
managers()          BelongsToMany via federation_managers (pivot)
registrationPolicies() HasMany
validators()        HasMany FederationValidator
requiredAttributes() HasMany FederationRequiredAttribute
ruleConfigs()       HasMany FederationRuleConfig
enabledPolicies()   HasMany where enabled=true
```

**Pivot caveats:**
- `federation_managers` has no timestamp columns — do **not** call `->withTimestamps()`
  on the relationship (Laravel bug trap: passes `false` as `$createdAt` but still
  adds `updated_at` to SELECT → SQLSTATE column not found).
- `wherePivot()` inside `withCount()` closures is invalid — use
  `where('entity_federation.status', 'active')` instead.

### 8.2 Entity membership lifecycle

```
POST /entities/{entity}/federation-submit
  → EntityFederation pivot created with status=pending

FM approves via FederationController::approveEntity() or FederationManager::approveEntity()
  → pivot status = active
  → entity.status promoted draft/pending → active (first approval only)
  → federation_metadata cache key forgotten
  → webhook 'entity.approved' dispatched
  → notification 'entity_approved' dispatched

FM rejects via FederationController::rejectEntity()
  → pivot status = rejected
  → notification 'entity_rejected' dispatched
```

### 8.3 Federation show page tabs

The show page has 8 Bootstrap tabs:
1. **General** — details card (inline edit) + Chart.js pie chart
2. **Membership** — pending/active IdPs/SPs, add-entity form
3. **Metadata** — generation actions, public feed URLs, eduGAIN panel
4. **Attributes** — required attributes table
5. **Validators** — external validator table + run-in-modal
6. **Rules** — federation-level compliance rule overrides
7. **Contacts** — federation contact list
8. **Managers** — assigned FM users (add/remove)

### 8.4 Deactivating a federation

The `FederationDeactivateModal` Livewire component runs a 6-step wizard:
1. Impact summary (active/pending entity counts)
2. Active entities — leave/disable/move to another federation
3. Pending entities — keep/reject (skipped when none)
4. Jobs info (auto-generation stops automatically)
5. Notify — toggle email to entity owners
6. Confirm + execute (DB::transaction)

---

## 9. Metadata Pipeline

### 9.1 Components

| Class | Role |
|---|---|
| `EntityMetadataService` | Renders XML for a single entity (DOMDocument) |
| `GenerateMetadataJob` | Aggregates all active entities for a federation into EntitiesDescriptor XML; optionally signs it |
| `AutoGenerateMetadataJob` | Dispatches `GenerateMetadataJob` for all active federations on schedule |
| `XmlsectoolSigner` | Wraps the `xmlsectool` Java CLI for XML signing |
| `MetadataGenerationController` | HTTP endpoints for manual generation, download, public feeds |
| `FederationManager` (Livewire) | UI trigger for per-federation generation |

### 9.2 Cache keys

| Key | Content | TTL |
|---|---|---|
| `federation_metadata:{id}` | Signed/unsigned XML for full feed | `metadata_valid_until_hours` (default 6h) |
| `federation_edugain_metadata:{id}` | eduGAIN-only subset XML | Same |

Cache is busted by `EntityObserver` on `updated()`, `deleted()`, `restored()` events.

### 9.3 Public feed endpoints (no auth required)

```
GET /metadata/{federation}/feed          → full aggregate XML
GET /metadata/{federation}/edugain       → eduGAIN-tagged entities only
```

Both abort 404 if `federation->status !== 'active'`. On cache miss they call
`GenerateMetadataJob::dispatchSync()` inline.

### 9.4 Signed metadata download (auth required)

```
GET /metadata/{federation}/download      → download cached signed XML
```

Signing requires `FEDERATION_SIGNING_KEY`, `FEDERATION_SIGNING_CERT`,
`XMLSECTOOL_PATH` set in `.env`. If not set, unsigned XML is returned.

### 9.5 MDQ (Metadata Query Protocol)

```
GET /api/entities/mdq/{sha1_entityid}
```

Returns a signed `EntityDescriptor` for a single entity. The `sha1_entity_id` column
on entities is auto-populated in `Entity::boot()` on save.

---

## 10. Compliance Rule Engine

### 10.1 Architecture

```
RuleRegistry ──scans──► app/Services/Metadata/Rules/**/*.php
     │
     ▼
RuleEngine::evaluate(Entity $entity)
     │
     ├── loads active rule_definitions from DB (one query, keyed by id)
     ├── resolves priority: entity_rule_config > federation_rule_config > rule default
     └── for each rule: RuleResult (pass/fail/warning/not_applicable)
```

### 10.2 34 rule classes

| Group | IDs | Checks |
|---|---|---|
| Structural | S01–S10 | entityID URI, uniqueness, role descriptor, protocol support, certificate present, SSO endpoint, ACS endpoint, ACS index uniqueness, binding URNs, HTTPS |
| Certificate | C01–C05 | Certificate valid (parseable PEM), key size ≥ 2048, not expired, not Debian-weak key, SHA-256+ signature algorithm |
| REFEDS | R01–R15 | DisplayName, Description, OrgName, OrgDisplayName, OrgURL, ContactPerson, SIRTFI security contact, CoCo privacy URL, R&S canonical URI, shibboleth scope, scope matches domain, RegistrationInfo, WantAssertionsSigned, WantAuthnRequestsSigned, NameIDFormat unspecified |
| XSD | X01 | SAML2 schema validation (graceful warning if schema files absent) |
| OIDC | O01–O03 | Redirect URI HTTPS, valid grant types, scopes contains openid |

Rule IDs are always **zero-padded**: `S01`, `C05`, `R15`, `X01`, `O03`.

### 10.3 Syncing rules to DB

```bash
php artisan rules:sync
# Output: 34 rules discovered and synced
```

This command must be re-run whenever a rule class is added or removed.

### 10.4 UI locations

- `/rules` — global toggle active/inactive per rule (Admin)
- `/federations/{id}/rules` — federation-level enabled + severity overrides
- `/entities/{id}/rules` — entity-level overrides (shows federation baseline)

### 10.5 `appliesTo()` return type

`MetadataRule::appliesTo()` returns `string[]`, e.g., `['idp']`, `['sp']`,
`['idp', 'sp']`, `['oidc']`. RuleEngine uses `in_array($entity->type, $rule->appliesTo())`.

---

## 11. Certificate Monitoring

### 11.1 Dashboard

`CertificateDashboard` Livewire component at `/certificates/monitor`.

Severity buckets (based on `not_after`):

| Severity | Threshold |
|---|---|
| expired | `not_after < now` |
| critical | ≤ 14 days remaining |
| warning | ≤ 30 days |
| advisory | ≤ 60 days |
| info | ≤ 90 days |
| healthy | > 90 days |

### 11.2 Automated checks

`CheckCertificateExpiryJob` runs on schedule (configurable via Scheduler UI).
Per-certificate it dispatches `certificate_expiring` or `certificate_expired`
notifications via `NotificationService`. Cache deduplication prevents duplicate
alerts for the same cert on the same day.

### 11.3 `CertificateService`

`app/Services/Entity/CertificateService.php` — parses PEM strings via `openssl_x509_parse()`:

```php
parse(string $pem): array     // extracts subject, issuer, not_before, not_after, key_bits, etc.
daysUntilExpiry(EntityCertificate $cert): int
isDebianWeak(EntityCertificate $cert): bool
```

---

## 12. Notification System

### 12.1 Architecture

```
Any service/controller calls:
  app(NotificationService::class)->dispatch(
      string $type,        // e.g. 'entity_pending_approval'
      array  $data,        // template variables
      ?Entity $entity,
      ?Federation $federation
  )

NotificationService:
  ├── loads NotificationType (returns early if not found or inactive)
  ├── resolves recipients from type flags:
  │     notify_submitter       → entity owners (EntityManager)
  │     notify_federation_managers → federation->managers()
  │     notify_admins          → User::role('Admin')
  ├── per user: checks UserNotificationPreference
  │     via_ui=true  → creates AppNotification row
  │     via_email=true → sends email via Mail facade
  └── external contacts (notify_entity_technical/admin):
        sends email directly (no DB row)
```

### 12.2 12 notification types (seeded)

`entity_pending_approval`, `entity_approved`, `entity_rejected`, `entity_suspended`,
`certificate_expiring`, `certificate_expired`, `user_registered`,
`invitation_request_created`, `invitation_request_approved`, `invitation_request_rejected`,
`metadata_generated`, `federation_deactivated`

### 12.3 UI

- **Bell icon** in topbar (`notification-bell.blade.php` partial) — Alpine x-data
  dropdown showing last 5 unread, unread count badge.
- `/notifications` — full paginated list with mark-read and archive actions.
- `/notifications/archive` — archived (deleted) notifications.
- `/profile/notifications` — per-user toggle for each notification type.

### 12.4 Wired dispatch points

| Event | Source |
|---|---|
| `entity_pending_approval` | `EntityController::store()` (Guest/EM only) |
| `entity_approved` | `FederationController::approveEntity()` + `FederationManager::approveEntity()` |
| `entity_rejected` | `FederationController::rejectEntity()` + `FederationManager::confirmReject()` |
| `certificate_expiring/expired` | `CheckCertificateExpiryJob` |
| `user_registered` | `SamlAuthController::findOrCreateUser()` (new users) + `InvitationRegistrationController::register()` |
| `invitation_request_created/approved/rejected` | `InvitationRequestController` |
| `metadata_generated` | `GenerateMetadataJob` (non-eduGAIN runs) |

---

## 13. Webhook System

### 13.1 Endpoint management

`/webhooks` — Admin-only UI to create/view/delete webhook endpoints.

Each `WebhookEndpoint` stores:
- `url` — target URL (must be HTTPS)
- `secret` — auto-generated Str::random(64) HMAC key
- `events` — JSON array of subscribed event names, or `['*']` for wildcard
- `active` — boolean toggle

### 13.2 Dispatch flow

```
WebhookService::dispatch(string $event, array $payload)
  ├── queries active endpoints with subscribesTo($event)
  ├── creates WebhookDelivery (status=pending)
  └── dispatches DeliverWebhookJob (queue: webhooks, tries=5)

DeliverWebhookJob::handle()
  ├── signs payload: X-Hub-Signature-256: sha256=<HMAC-SHA256>
  ├── sends POST via Http::timeout(10)
  ├── updates delivery status=delivered/failed
  └── on failure: re-throws for retry with backoff [60,120,300,600,1800]s
```

### 13.3 Wired events

| Event | Dispatch point |
|---|---|
| `entity.created` | `EntityObserver::created()` |
| `entity.approved` | `FederationController::approveEntity()` |
| `metadata.generated` | `FederationController::generateMetadata()` + `GenerateMetadataJob` |

### 13.4 Retrying failed deliveries

`POST /webhooks/{webhook}/deliveries/{delivery}/retry` — available in webhook show page.

---

## 14. Discovery Endpoints (JEDI / WebFinger)

### 14.1 JEDI — JSON-based Entity Discovery Interface

```
GET /api/discovery/entities
    ?type=idp|sp
    &federation=<uri>
    &q=<search term>
    &page=<n>
```

Returns paginated JSON using `JediEntityResource`. Cached 15 min under
`discovery_jedi_<md5(params)>`. Cache is invalidated via marker key
`discovery_jedi_flush_marker` (forgotten in `EntityObserver::invalidateFederationCache()`).

### 14.2 WebFinger (RFC 7033)

```
GET /.well-known/webfinger?resource=<entityID>
```

Returns `{subject, links:[{rel, href}]}` where `href` is the federation metadata feed URL.
Returns 400 if `?resource` missing, 404 if entity not found or not active.

### 14.3 MDQ (Metadata Query Protocol)

```
GET /api/entities/mdq/{sha1_entityid}
```

Returns a SAML2 `EntityDescriptor` with `Content-Type: application/samlmetadata+xml`.
Entity must be active. Falls back to unsigned XML if signing not configured.

---

## 15. OIDC Client Support

Entities can have `type = 'oidc'` to represent OIDC clients (Relying Parties).

### 15.1 Data model

`entity_oidc_config` table (one-to-one via `EntityOidcConfig` model):

| Field | Type |
|---|---|
| `client_id` | varchar nullable |
| `redirect_uris` | json array |
| `grant_types` | json array |
| `response_types` | json array |
| `scopes` | json array |
| `application_type` | varchar(50) nullable |
| `token_endpoint_auth_method` | varchar(100) nullable |
| `logo_uri`, `policy_uri`, `tos_uri` | text nullable |

### 15.2 EntityForm OIDC tab

Properties in `EntityForm.php`:
- `$oidcRedirectUris` — newline-separated string in textarea
- `$oidcGrantTypes` — array of checked checkboxes
- `$oidcScopes` — space-separated string
- `$oidcApplicationType`, `$oidcTokenEndpointAuthMethod`

Saved via `saveOidcConfig()` (NOT `save()` — that's a Livewire base method conflict).

### 15.3 RFC 7591 metadata endpoint

```
GET /api/entities/{entity}/oidc-configuration
```

Returns JSON with `client_id`, `redirect_uris`, `grant_types`, `scope`, etc.
Returns 404 if entity type ≠ `oidc` or no config exists.

### 15.4 OIDC compliance rules

Three rules in `app/Services/Metadata/Rules/Oidc/`:
- `O01_RedirectUriHttps` — redirect URIs must use HTTPS (or `http://localhost`)
- `O02_GrantTypeValid` — grant types must be from allowed list
- `O03_ScopeContainsOpenid` — scopes array must include `openid`

---

## 16. Attribute Registry & ARP

### 16.1 Attribute definitions

`/attributes` — registry of 59 standard attributes across 4 schemas:
- **eduPerson** (13) — `eduPersonPrincipalName`, `eduPersonAffiliation`, etc.
- **LDAP** (26) — `cn`, `mail`, `givenName`, etc.
- **SCHAC** (16) — `schacHomeOrganization`, `schacPersonalUniqueCode`, etc.
- **voPerson** (4) — `voPersonID`, etc.

`AttributeDefinition` model fields: `name`, `oid`, `urn`, `full_name`, `schema`,
`description`, `is_active`.

### 16.2 SP Requested Attributes

SP entities declare which attributes they request via
`/entities/{entity}/requested-attributes`.

**Model naming warning:** The `Entity` model has a `getRequestedAttributesAttribute()`
accessor for the JSON column `requested_attributes`. The relationship must be called
`entityRequestedAttributes()` — not `requestedAttributes()`.

### 16.3 IdP Attribute Release Policy (ARP)

IdPs configure per-SP attribute release at `/entities/{idp}/arp`.
Stored in `entity_arp` table (`EntityArp` model with `idp_entity_id` + `sp_entity_id` FKs).

---

## 17. Mail System

### 17.1 Mail templates

`/mail/templates` — Admin-managed templates for federation bulk email.

5 groups seeded: `invitation`, `entity_suspended`, `federation_deactivated`, `general`, `compliance_failure`.
13 supported placeholders: `[[contact_name]]`, `[[entity_name]]`, `[[federation_name]]`,
`[[invitation_url]]`, `[[expiry_hours]]`, `[[mail_signature]]`, etc.

`MailTemplateService::render(MailTemplate $tpl, array $data): array` — performs
`str_replace` on all `[[placeholder]]` keys. `$data` must contain **model objects**
(e.g. `['entity' => $entityModel]`), not string-keyed placeholders.

### 17.2 Bulk send to federation

`/federations/{federation}/mail/compose` → `FederationMailController::compose()`

Dispatches `SendFederationMailJob` (queue: `low`, timeout: 300s) which
calls `MailTemplateService::sendToFederation()`. Results logged to `mail_log` table.

### 17.3 Flash message rule

**Never** use `withErrors()` for user-facing action errors. Always use
`->with('error', 'message')` so flash reaches the global toastr handler.
After a Livewire `$this->redirect(..., navigate: true)` use
`session()->flash()` instead of `$this->dispatch('notify')`.

### 17.4 Compliance failure notifications

`ComplianceNotifyController` sends per-entity compliance failure emails using the
`compliance_failure` mail template group. Three routes:

```
GET  entities/{entity}/compliance-notify/preview     → JSON {subject, body} preview
POST entities/{entity}/compliance-notify             → send to one entity's technical contacts
POST federations/{federation}/compliance-notify      → bulk send to selected entity IDs
```

**Entity page flow** (`/entities/{entity}/validate`):
- "Preview notification" button opens a modal with rendered subject + body.
- "Notify contacts" button shows a popover listing each technical contact's name and
  email on hover, then POSTs to `entities.compliance-notify`.
- Send is blocked when no compliance issues exist on the current validation result.

**Federation re-validation flow** (`/federations/{federation}/revalidate`):
- After running a batch re-validation, checkboxes appear on entity cards.
- "Select all failing entities" pre-selects entities with errors or warnings.
- "Send notifications" POSTs the checked `entity_ids[]` to `federations.compliance-notify`.
- Only active federation members in the submitted IDs are processed; entities with no
  validation result are skipped.

Both paths call `MailTemplateService::sendToEntity()` which delivers to `['technical']`
contact type and logs each send to `mail_log`. The controller is gated on
`Gate::authorize('update', $entity|$federation)`.

---

## 18. Invitations & Self-Registration

### 18.1 Invitation flow (Admin / FM)

1. FM creates invitation at `/invitations` (email, federation, optional entity).
2. `InvitationService::create()` generates `Str::random(64)` token, stores in
   `invitations` table, sends `InvitationMail`.
3. Invitee follows link to `/register/{token}`.
4. `InvitationRegistrationController` validates token (throws `InvalidInvitationException`
   for expired/revoked/already-used).
5. User created, assigned Guest role (or Entity Manager if entity_id set,
   with `entity_managers` owner row).

### 18.2 Invitation states

`pending` → `accepted` (on registration) or `expired` (TTL from `invitation_expiry_hours`)
or `revoked` (FM action).

Expired/revoked invitations can be reissued (creates new token, deletes old).
Reissuing a revoked invitation requires a `reissue_comment`.

### 18.3 Contact invitation requests (Entity Manager → FM)

EMs with `entity.requestContactInvitation` can request that an existing contact
be added as a co-manager. FM reviews at `/invitation-requests`.

---

## 19. Statistics & Reporting

`/statistics` — requires `compliance.view` permission.

Dashboard shows 4 Chart.js charts:
- **Registration trend** — monthly entity registrations (last 12 months)
- **Compliance trend** — monthly average compliance pass rate
- **Certificate forecast** — certs expiring by month (next 6 months)
- **Federation members** — horizontal bar chart of IdP+SP counts per federation

CSV exports: `/statistics/export/entities`, `/statistics/export/certificates`,
`/statistics/export/memberships`.

Data is cached 30 minutes under `registry_statistics`.

---

## 20. eduGAIN Integration

### 20.1 Status panels (EduGainApiService)

`EduGainApiService` queries `https://technical.edugain.org/api.php`:
- `getEccsStatus(entityId)` — ECCS compliance status for IdPs
- `getEntityPresence(entityId)` — whether entity appears in eduGAIN
- `getFederationStatus(fedCode)` — member counts for a federation code

All responses cached 1 hour. Controlled by `edugain_checks_enabled` system preference.

### 20.2 Upstream sync

`SyncEduGainMetadataJob` runs on schedule (configurable interval):
1. `Http::timeout(120)->get($url)` — fetches `EntitiesDescriptor` XML
2. `XMLReader` walks `EntityDescriptor` elements; calls `EntityImportService::fromXml()`
3. Batch upsert every 100 entities via `DB::transaction()`
4. Post-loop: entities with `source=edugain` not in the current feed are
   `update(['status' => 'suspended'])`

The sync URL is configured via `SchedulerSetting::get('edugain_metadata_url')`.

---

## 21. Import from Jagger

### 21.1 Jagger import command

```bash
php artisan jagger:import [--dry-run] [--host=...] [--database=...] [--username=...] [--password=...]
```

Reads Jagger's MySQL database directly. 7 import phases (all idempotent via `firstOrCreate`):
1. Federations
2. Entities + UI info
3. Certificates (normalises bare base64 to PEM)
4. Endpoints
5. Contacts
6. Entity attributes
7. Entity-federation relationships

Production prompts for confirmation. `--dry-run` substitutes `dry-run-{id}` IDs.

### 21.2 JaggerImportSeeder

`JaggerImportSeeder` reads connection from env vars:
`JAGGER_DB_HOST`, `JAGGER_DB_PORT`, `JAGGER_DB_NAME`, `JAGGER_DB_USER`, `JAGGER_DB_PASSWORD`.

---

## 22. Internationalisation

### 22.1 Translation files

`lang/en/app.php` and `lang/ro/app.php` — all UI strings.
Key prefixes: `nav_*`, `action_*`, `entity_*`, `status_*`, `dashboard_*`,
`cert_*`, `validation_*`, `auth_*`, `suspend_*`, `deactivate_*`.

### 22.2 Locale resolution (SetLocale middleware)

Priority: `session('app_locale')` → `user->preferred_locale` → `SystemPreference::get('default_language')`.

Changing language: `GET /language/{lang}` (supported langs from `SystemPreference::get('supported_languages', 'en,ro')`).

### 22.3 Adding a new language

1. Update `supported_languages` preference to include the new code.
2. Create `lang/{code}/app.php`.
3. Add flag emoji + label to `$supportedLangs` array in `topbar.blade.php`.

---

## 23. Scheduler & Background Jobs

All schedule times and thresholds are configurable via `/scheduler` (Admin-only).

### 23.1 Scheduler settings (stored in `scheduler_settings` table)

| Group | Key | Default | Type |
|---|---|---|---|
| metadata | `auto_generate_enabled` | false | bool |
| metadata | `interval` | 6 | integer (hours) |
| metadata | `valid_until_hours` | 48 | integer |
| metadata | `cache_duration_hours` | 6 | integer |
| validation | `auto_enabled` | false | bool |
| validation | `schedule_day` | 0 (Sun) | integer |
| validation | `schedule_time` | 03:00 | time |
| certificates | `check_enabled` | true | bool |
| certificates | `check_time` | 06:00 | time |
| certificates | `notify_*_days` (4 thresholds) | 14/30/60/90 | integer |
| edugain | `sync_enabled` | false | bool |
| edugain | `interval_hours` | 24 | integer |
| edugain | `metadata_url` | (REFEDS URL) | url |
| cleanup | `enabled` | false | bool |
| cleanup | `metadata_days` | 30 | integer |

### 23.2 Scheduled jobs (`routes/console.php`)

| Job | Schedule (from DB) | Queue |
|---|---|---|
| `AutoGenerateMetadataJob` | Configurable interval | `high` |
| `SyncEduGainMetadataJob` | Configurable interval (if enabled) | `high` |
| `ValidateEntityMetadataJob` | Weekly, configurable day+time | `low` |
| `CheckCertificateExpiryJob` | Daily, configurable time | `low` |
| `CleanupJob` | Daily (if enabled) | `default` |
| `horizon:snapshot` | Every 5 minutes | — |

`SchedulerSetting::get(key, default)` always falls back gracefully to the default
if the DB is unavailable (wrapped in try-catch).

---

## 24. Queue Workers (Horizon)

Horizon is configured in `config/horizon.php`. Queue names follow a three-level
priority model (`high` → `default` → `low`) so new jobs can be added without
changing the supervisor or service file.

| Supervisor | Queue | Workers | Timeout |
|---|---|---|---|
| `supervisor-high` | `high` | 2 | 120s |
| `supervisor-default` | `default` | 1 | — |
| `supervisor-low` | `low` | 1 | 300s |

**Production start:**
```bash
php artisan horizon
# or via Supervisor daemon
```

**Windows/local development:** Horizon requires `ext-pcntl` (Linux only). Use
`QUEUE_CONNECTION=database` + `php artisan queue:work --queue=high,default,low` locally.

---

## 25. Artisan Commands

| Command | Description |
|---|---|
| `php artisan rules:sync` | Scans rule classes, upserts `rule_definitions`, flushes cache |
| `php artisan saml:download-schemas` | Downloads 4 SAML XSD files to `storage/app/schemas/` |
| `php artisan jagger:import` | Imports data from a Jagger MySQL database |
| `php artisan permission:cache-reset` | Clears Spatie permission cache from Redis |
| `php artisan db:seed --class=RolesAndPermissionsSeeder` | Re-applies role/permission setup |
| `php artisan db:seed --class=DevelopmentSeeder` | Re-seeds dev fixture users + entities |
| `php artisan horizon` | Start Horizon queue worker (production/Linux) |
| `php artisan schedule:run` | Run due scheduled jobs (called by cron every minute) |

---

## 26. Testing

### 26.1 Running tests

```bash
# Full suite
php artisan test

# Specific test file
php artisan test tests/Feature/Controllers/EntityControllerTest.php

# Specific test by name
php artisan test --filter "it can create an entity"

# Unit tests only (no MySQL needed)
php artisan test tests/Unit

# Stop on first failure
php artisan test --stop-on-failure
```

### 26.2 Test database

Feature tests use `next_test` database. Configured in `phpunit.xml`:

```xml
<env name="DB_DATABASE" value="next_test"/>
<env name="CACHE_STORE" value="array"/>   <!-- IMPORTANT: isolates from dev Redis -->
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="MAIL_MAILER" value="array"/>
```

**`CACHE_STORE=array`** is critical — without it tests hit real Redis and can read
stale cached data from the dev environment, causing phantom test failures.

### 26.3 Test structure

```
tests/
  Feature/
    Attributes/         ARP, AttributeDefinition, RequestedAttributes
    Auth/               SAML + local login
    Controllers/        HTTP-level tests for all 20+ controllers
    EduGain/            EduGainController
    Federations/        Show tabs, validators, registration policies
    Jobs/               SyncEduGainMetadataJob
    Livewire/           EntityForm validation, FederationManager, OIDC form
    Mail/               Templates, federation mail
    Multilingual/       Language switch, entity multilingual fields
    Observers/          EntityObserver audit log
    Preferences/        SystemPreferences
    Scheduler/          SchedulerSettings
    Services/           NotificationService, dispatch wiring
  Unit/
    Jobs/               GenerateMetadataJob
    Metadata/           XmlsectoolSigner
    Rules/              All 34 rule classes (unit, no DB)
    Services/           CertificateService, EduGainApiService, EntityImportService,
                        EntityMetadataService, ExternalValidatorService, MailTemplateService
```

### 26.4 Current baseline

**418 passed, 0 failed, 1175 assertions**

### 26.5 Useful test helpers

```php
// Create a user with a specific role
$user = User::factory()->create();
$user->assignRole('Federation Manager');

// Create an entity in a known state
$entity = Entity::factory()->create(['type' => 'idp', 'status' => 'active']);

// Bind a fake SAML service (for auth tests)
$this->app->bind(SamlServiceInterface::class, FakeSamlService::class);

// Mock external HTTP (eduGAIN, validators)
Http::fake(['https://technical.edugain.org/*' => Http::response([...])]);

// Fake queues
Queue::fake();
Queue::assertPushed(GenerateMetadataJob::class);
```

---

## 27. Known Bug Patterns & Gotchas

These are recurring pitfalls documented from real bugs hit during development.

### Entity model accessor collision

```php
// WRONG — returns JSON array from accessor, NOT a Collection
$entity->requestedAttributes

// CORRECT — returns Collection from HasMany
$entity->entityRequestedAttributes
```

### Livewire method naming

Never name a Livewire method `validate()` or `save()` (the latter is safe but
`saveOidcConfig()` is used for OIDC to distinguish it). Use `runValidationGate()`,
`validateEntity()`, `saveOidcConfig()`, etc.

### Alpine.js `@click` must be an expression

`@click` is evaluated as `return (expression)`. Block statements (`try`, `if {...} else {...}`)
throw "Unexpected token". Move all logic into `x-data` methods.

### `navigator.clipboard` requires HTTPS

Forbidden in Blade. Use DOM textarea + `document.execCommand('copy')`.

### Bare `x-data` on Livewire-managed elements

`<tr wire:key="..." wire:click="..." x-data>` — bare `x-data` creates an isolated Alpine
scope that breaks `wire:click` on re-render. Either remove `x-data` or give it a value.

### `@entangle` inside x-data attribute strings

`<div x-data="{ action: @entangle('prop') }">` — `@entangle` is a Blade directive,
not resolved inside HTML attribute strings. Use `$wire.entangle('prop')` instead.

### Alpine `@click.outside` placement

Must be on the **same element as `x-data`**, not a child. Otherwise the toggle button
(outside the child) triggers `@click.outside` immediately, opening and closing in the
same event.

### Bootstrap `.dropdown-menu` + Alpine `x-show`

`Bootstrap sets display:none` via CSS. `x-show` removes the inline style but falls
back to CSS → still hidden. Don't use `.dropdown-menu` on Alpine-controlled dropdowns;
use plain `bg-white border rounded shadow-sm p-2` with `position:absolute`.

### UUID quoting in wire:click / Alpine expressions

```php
// WRONG — UUID parsed as numeric literal
wire:click="method({{ $model->id }})"

// CORRECT
wire:click="method('{{ $model->id }}')"
```

### `json_encode()` in wire:click

Raw JSON output (containing `{`, `"`, `[`) breaks Alpine's expression parser.
Move logic into a Livewire method, pass only primitive (integer/string) arguments.

### MySQL row size limit

`utf8mb4` counts `varchar(1024)` as 4096 bytes worst-case. Use `text()` for URL columns.
`entity_id` uses `varchar(255)`.

### MySQL strict mode — timestamp columns

`$table->timestamp('col')` alone is invalid. Always add `.nullable()` or `.useCurrent()`.

### MySQL index name length

Maximum 64 characters. Always provide explicit short index names on compound indexes.

### `wherePivot()` inside `withCount()`

Invalid SQL — use `where('table.column', value)` instead.

### `withTimestamps()` on pivot without timestamp columns

Passes `false` as `$createdAt` but still appends `updated_at` to SELECT → column not
found error. Omit `withTimestamps()` entirely on pivots without those columns.

### RuleRegistry / RuleEngine return plain arrays

`RuleRegistry::all()` and `RuleEngine::evaluate()` return `MetadataRule[]` and
`RuleResult[]` — not Collections. Wrap with `collect()` before calling Collection methods.

### MailTemplateService `$data` structure

`render()` expects model objects: `['entity' => $entityModel]`, not string placeholders.

### `federation.create` vs `federation.edit` as Admin gate

`federation.edit` is shared with FM. Use `federation.create` (Admin-only) to gate
system-administration pages.

### Toastr vs session flash after Livewire redirect

After `$this->redirect(..., navigate: true)`, use `session()->flash('success', '...')`
instead of `$this->dispatch('notify', ...)`. The dispatch is consumed in the
pre-navigation lifecycle and lost.

### `withErrors()` for action errors

Never use `withErrors()` for user-facing errors. Use `->with('error', 'message')` so
the flash reaches the toastr handler.

### Blade class name resolution

Bare class names are not auto-resolved in Blade. Always use fully qualified names:
`\App\Models\SystemPreference::get('key')`.

---

## 28. Maintenance Runbook

### Re-apply roles and permissions after code change

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
```

### Sync new/removed compliance rules

```bash
php artisan rules:sync
# Verify: php artisan tinker --execute "echo \App\Services\Metadata\RuleRegistry::make()->count();"
```

### Download/refresh SAML XSD schemas

```bash
php artisan saml:download-schemas --force
```

### Clear all caches

```bash
php artisan cache:clear           # application cache
php artisan config:clear          # config cache
php artisan route:clear           # route cache
php artisan view:clear            # blade compiled cache
php artisan permission:cache-reset # Spatie permission cache
```

### Re-seed development data

```bash
php artisan db:seed --class=DevelopmentSeeder
# Idempotent: force-deletes then re-creates admin@example.com and operator@example.com
```

### Manually trigger a scheduled job

```bash
php artisan schedule:run          # runs all due jobs
# or dispatch individually:
php artisan tinker
>>> App\Jobs\CheckCertificateExpiryJob::dispatch()
>>> App\Jobs\AutoGenerateMetadataJob::dispatch()
```

### Inspect Horizon queue

```bash
php artisan horizon:status
php artisan horizon:pause
php artisan horizon:continue
php artisan queue:failed           # list failed jobs
php artisan queue:retry all        # retry all failed jobs
```

### Run the Jagger migration (one-time)

```bash
# Dry-run first
php artisan jagger:import --dry-run --host=old-db.example.com --database=jagger

# If output looks correct
php artisan jagger:import --host=old-db.example.com --database=jagger
```

### Regenerate metadata for all federations

```bash
php artisan tinker
>>> App\Jobs\AutoGenerateMetadataJob::dispatchSync()
```

### Check permission state for a user

```bash
php artisan tinker
>>> $u = App\Models\User::where('email','admin@example.com')->first();
>>> $u->getRoleNames()     // assigned roles
>>> $u->getAllPermissions()->pluck('name')  // effective permissions
>>> $u->can('federation.create')           // specific check
```

---

## 29. Deployment Notes

### 29.1 Environment checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`
- [ ] `FEDERATION_REGISTRATION_AUTHORITY` set to NREN URI
- [ ] `FEDERATION_SIGNING_KEY` + `FEDERATION_SIGNING_CERT` present for signed metadata
- [ ] `XMLSECTOOL_PATH` points to Java xmlsectool binary
- [ ] SimpleSAMLphp installed and configured (not installed via Composer)
- [ ] Horizon running as a Supervisor daemon

### 29.2 First deploy

```bash
php artisan migrate
php artisan db:seed          # production: does NOT include DevelopmentSeeder
php artisan rules:sync
php artisan saml:download-schemas
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon          # or via Supervisor
```

### 29.3 Subsequent deploys

```bash
php artisan migrate
php artisan rules:sync        # if any rules changed
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:restart   # graceful restart
```

### 29.4 Crontab entry (every minute)

```
* * * * * www-data php /var/www/federations-manager/artisan schedule:run >> /dev/null 2>&1
```

### 29.5 Nginx configuration

Serve only the `public/` directory. All routes must rewrite to `index.php`:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 29.6 SimpleSAMLphp

Not installed via Composer in this project.
Install `simplesamlphp/simplesamlphp` directly on the production server.
`SamlService.php` has a `class_exists()` guard — it throws a descriptive
`RuntimeException` if SimpleSAMLphp is absent (rather than a fatal error).

---

*Guide updated to codebase state as of 2026-06-01. Test baseline: 418 passed, 0 failed.*
