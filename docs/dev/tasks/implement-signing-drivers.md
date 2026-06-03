# Task: Implement Pluggable Signing Driver Architecture

Read `docs/dev/CONTEXT.md` and `docs/analysis.md` fully before starting.

## Context

Federation metadata signing currently uses `XmlsectoolSigner` which reads private keys
directly from disk as plaintext PEM files and has a global fallback key
(`FEDERATION_SIGNING_KEY` / `FEDERATION_SIGNING_CERT` env vars).

This task replaces that with a pluggable `SigningDriver` interface with two concrete
implementations (file-based and SoftHSM2 PKCS#11), per-federation driver selection,
and an installer step to guide SoftHSM2 setup.

**Why remove the global fallback key:**
eduGAIN registers each federation's signing certificate. If signing silently falls back
to a global key, eduGAIN verifies against the registered cert, the check fails, and
the federation's metadata silently disappears from the eduGAIN aggregate.

**Design principles:**
- Switching backends is one env var + key import ceremony — no PHP changes
- Each driver owns its health check
- Each driver owns its key management UI partial
- `FILE_SIGNING_IS_ACTIVE=true` always; `SOFTHSM_SIGNING_IS_ACTIVE` set by installer

---

## Security notice — SoftHSM2 bypass risk

SoftHSM2 is a **software** token. Anyone with root access on the server can:
1. Copy the token files from `/var/lib/softhsm/tokens/`
2. Read the PIN from `.env` (`JAGGER_HSM_PIN`)
3. Call `xmlsectool` directly with the same `pkcs11.cfg` and sign arbitrary metadata
   completely bypassing the application

This is the fundamental difference from a real hardware HSM (Nitrokey, CloudHSM) where
the key material physically cannot leave the device even with root access.

**SoftHSM2 protects against:** unprivileged users, web exploits running as `www-data`,
accidental exposure in backups, log leaks, file permission mistakes.

**SoftHSM2 does NOT protect against:** a root-level attacker who knows the PIN.

**The only detection mechanism for bypass:** the application's signing audit log.
If metadata is signed by calling xmlsectool directly, no `AuditLog` record is created.
This makes the absence of an audit record meaningful — monitor for metadata changes
without a corresponding audit entry.

This limitation is documented here so it informs operational decisions (host hardening,
log monitoring). It does not change the implementation.

---

## SoftHSM2 token architecture — one token per federation (Option B)

Each federation gets its own SoftHSM2 token, identified by label `jagger-fed-{id}`.
The token's slot ID (assigned by SoftHSM2 at init time) is stored in a dedicated
`softhsm_tokens` DB table.

```
Token: "jagger-fed-{uuid-1}"  → federation 1 private key + certificate (both in token)
Token: "jagger-fed-{uuid-2}"  → federation 2 private key + certificate (both in token)
```

**Why per-federation (not one shared token):**
If a shared token is corrupted or accidentally deleted, ALL federation signing keys are
lost simultaneously. Per-federation tokens limit blast radius to one federation.

**Both the private key AND the certificate live inside the token.**
xmlsectool's PKCS#11 path (`--pkcs11Config`) locates the signing certificate from the
token automatically by matching `CKA_ID` — no `--certificate` file argument is needed
or used. `pkcs11-tool` is used to import the certificate object into the token.

**The `softhsm_tokens` table** stores token metadata alongside the cryptographic material:

| column | type | notes |
|--------|------|-------|
| `id` | uuid PK | |
| `federation_id` | uuid FK → federations | cascadeOnDelete |
| `token_label` | varchar(255) | `jagger-fed-{federation_id}` |
| `slot_id` | varchar(64) | assigned by SoftHSM2 at init; used in pkcs11.cfg |
| `created_by` | uuid FK → users nullOnDelete | operator who initialised the token |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp nullable | soft-delete on token removal |

---

## Items checklist

- [ ] 1. `SigningDriver` interface
- [ ] 2. `FileSigningDriver`
- [ ] 3. `SoftHsmSigningDriver`
- [ ] 3b. `SignedMetadataValidator`
- [ ] 4. `SigningDriverFactory`
- [ ] 5. Migrations — `signing_driver` column + `softhsm_tokens` table + drop timestamp columns
- [ ] 6. `SoftHsmToken` model
- [ ] 7. `config/federation.php` additions
- [ ] 8. `.env` / `.env.example` changes
- [ ] 9. Federation creation — driver selector
- [ ] 10. `FederationSigningKeys` Livewire — driver-aware
- [ ] 11. Driver view partials (file + softhsm)
- [ ] 12. `GenerateMetadataJob` — resolve driver
- [ ] 13. `FederationMetadataSign` Livewire — driver-aware
- [ ] 14. Delete `XmlsectoolSigner`
- [ ] 15. `SigningHealthCheck` — replaces `XmlsectoolCheck`
- [ ] 16. `HealthUiController` update
- [ ] 17. Installer — SoftHSM2 step
- [ ] 18. `Federation` model cleanup
- [ ] 19. `CONTEXT.md` update

---

## STEP 1 — SigningDriver interface

Create `app/Services/Signing/Contracts/SigningDriver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Signing\Contracts;

use App\Models\Federation;
use App\Services\HealthChecks\HealthCheckResult;

interface SigningDriver
{
    /**
     * Sign an XML metadata string and return the signed XML.
     *
     * Must throw XmlSigningException on any failure — never return unsigned XML.
     * Must use only the per-federation credential; no fallback to global keys.
     * Must write an AuditLog entry on every successful signing operation.
     */
    public function sign(string $xml, Federation $federation): string;

    /**
     * Return true if a private key credential is stored for the federation.
     * Used by FederationMetadataSign and the health check.
     */
    public function hasKey(Federation $federation): bool;

    /**
     * Return true if a signing certificate is stored for the federation.
     * Used by FederationMetadataSign and the health check.
     */
    public function hasCert(Federation $federation): bool;

    /**
     * Store a PEM-encoded private key for the federation.
     * FileSigningDriver writes to disk (chmod 600).
     * SoftHsmSigningDriver initialises a new per-federation token and imports the key.
     * Must audit-log the action.
     *
     * @throws \RuntimeException on storage failure.
     */
    public function storeKey(Federation $federation, string $pem): void;

    /**
     * Store a PEM-encoded X.509 certificate for the federation.
     * FileSigningDriver writes to disk (chmod 600).
     * SoftHsmSigningDriver imports the certificate object into the federation's token
     * using pkcs11-tool (--write-object --type cert). The certificate lives entirely
     * inside the token — it is NOT written to disk.
     * Must audit-log the action.
     *
     * @throws \RuntimeException on storage failure.
     */
    public function storeCert(Federation $federation, string $pem): void;

    /**
     * Delete all credentials for the federation (key + cert).
     * FileSigningDriver removes both PEM files.
     * SoftHsmSigningDriver deletes the entire token (softhsm2-util --delete-token)
     * and soft-deletes the softhsm_tokens DB row.
     * Must audit-log the action.
     */
    public function deleteAll(Federation $federation): void;

    /**
     * Return display information about the stored private key.
     *
     * Expected keys (all nullable): type, bits, created_at, pem_preview.
     * FileSigningDriver: reads from disk via openssl_pkey_get_details().
     * SoftHsmSigningDriver: reads public key attributes from token via pkcs11-tool.
     * Return null if no key is stored.
     *
     * @return array<string, mixed>|null
     */
    public function keyInfo(Federation $federation): ?array;

    /**
     * Return display information about the stored certificate.
     *
     * Expected keys (all nullable): subject, issuer, serial, not_before,
     * not_after, validity_class, validity_label, pem.
     * FileSigningDriver: reads cert file via openssl_x509_parse().
     * SoftHsmSigningDriver: reads certificate object from token via
     *   pkcs11-tool --read-object --type cert, then openssl_x509_parse().
     * KMS/Vault drivers that do not manage certificates should return null.
     * Return null if no cert is stored.
     *
     * @return array<string, mixed>|null
     */
    public function certInfo(Federation $federation): ?array;

    /**
     * Run a self-test for this driver and return a HealthCheckResult.
     *
     * FileSigningDriver: verifies xmlsectool binary + does a live signing round-trip.
     * SoftHsmSigningDriver: verifies softhsm2-util + pkcs11-tool binaries +
     *   PKCS11_LIBRARY file exists + SOFTHSM2_CONF is readable.
     * Called by SigningHealthCheck on every active driver.
     */
    public function healthCheck(): HealthCheckResult;

    /**
     * Return the Blade partial name for the key management UI.
     *
     * The partial is included by federation-signing-keys.blade.php.
     * Convention: 'signing-keys.file', 'signing-keys.softhsm'.
     */
    public function viewName(): string;
}
```

---

## STEP 2 — FileSigningDriver

Create `app/Services/Signing/FileSigningDriver.php`.

Move the entire body of `XmlsectoolSigner::sign()` here unchanged.

Key differences from the old signer:
- No fallback — if `signingKeyPath()` or `signingCertPath()` does not exist →
  throw `XmlSigningException` immediately
- `sign()` must write an `AuditLog` entry on every successful call
- `storeKey()` / `storeCert()`: `file_put_contents($path, $pem)` + `chmod(0600)` +
  create parent dir if needed + `AuditLog::create()`
- `deleteAll()`: `unlink` both files if they exist + `AuditLog::create()`
- `keyInfo()`: `openssl_pkey_get_details()` — same logic as current
  `FederationSigningKeys::showKeyInfo()`
- `certInfo()`: `openssl_x509_parse()` — same logic as current
  `FederationSigningKeys::showCertInfo()`
- `hasKey()` / `hasCert()`: `file_exists()` on the path
- `healthCheck()`: move `XmlsectoolCheck` logic here; return `HealthCheckResult`
- `viewName()`: return `'signing-keys.file'`

Private path helpers (moved from Federation model in Step 18):

```php
private function keyPath(Federation $federation): string
{
    return storage_path("app/signing-keys/{$federation->id}/signing.key");
}

private function certPath(Federation $federation): string
{
    return storage_path("app/signing-keys/{$federation->id}/signing.crt");
}
```

---

## STEP 3 — SoftHsmSigningDriver

Create `app/Services/Signing/SoftHsmSigningDriver.php`.

### Architecture reminder

- One token per federation, label = `jagger-fed-{federation->id}`
- Both private key AND certificate are stored inside the token (not on disk)
- Slot ID is stored in the `softhsm_tokens` DB table
- pkcs11.cfg is generated dynamically per federation from the DB slot ID

### sign()

**No `--certificate` argument.** xmlsectool finds the certificate inside the token
automatically by matching `CKA_ID` with the private key. This is the correct PKCS#11
path — using `--certificate` alongside `--pkcs11Config` is a bug (file mode mixed
with token mode).

```php
$process = new Process([
    $tool,
    '--sign',
    '--digest',                   'SHA-256',
    '--referenceIdAttributeName', 'ID',
    '--pkcs11Config',             $this->pkcs11ConfigPath($federation),
    '--keyAlias',                 $this->keyAlias($federation),
    '--keyPassword',              config('federation.softhsm_pin'),
    '--inFile',                   $inFile,
    '--outFile',                  $outFile,
], env: $this->javaEnv());
```

Must write an `AuditLog` entry on every successful signing call.

### Private helpers

```php
private function keyAlias(Federation $federation): string
{
    return "jagger-fed-{$federation->id}";
}

private function pkcs11ConfigPath(Federation $federation): string
{
    return storage_path("app/signing-keys/{$federation->id}/pkcs11.cfg");
}

private function pkcs11Config(Federation $federation): string
{
    $token = SoftHsmToken::where('federation_id', $federation->id)
        ->whereNull('deleted_at')
        ->firstOrFail();

    return implode("\n", [
        'name = softhsm2',
        'library = ' . config('federation.pkcs11_library'),
        'slot = ' . $token->slot_id,
    ]);
}
```

### storeKey()

1. Write PEM to a temp file (unlink in finally)
2. `softhsm2-util --init-token --slot 0 --label "{alias}" --so-pin {sopin} --pin {pin}`
3. Parse the assigned slot ID from `softhsm2-util --show-slots` output (match by label)
4. `SoftHsmToken::create([federation_id, token_label, slot_id, created_by])`
5. `softhsm2-util --import {tmpfile} --token "{alias}" --label "{alias}" --id 01 --pin {pin}`
6. Write `pkcs11.cfg` to `storage/app/signing-keys/{id}/pkcs11.cfg`
7. `AuditLog::create()`

### storeCert()

The certificate goes INTO the token — not to disk.

```bash
pkcs11-tool --module {pkcs11_library} \
  --write-object {tmp_cert_der} --type cert \
  --label "{alias}" --id 01 \
  --token-label "{alias}" --pin {pin}
```

Steps:
1. Convert PEM to DER: `openssl_x509_read()` + export to DER format (write to temp file)
2. Run `pkcs11-tool --write-object` via `Process`
3. Unlink temp DER file
4. `AuditLog::create()`

### deleteAll()

1. `softhsm2-util --delete-token --token "{alias}"` (suppresses errors if already gone)
2. Unlink `pkcs11.cfg` if it exists
3. `SoftHsmToken::where('federation_id', ...)->whereNull('deleted_at')->update(['deleted_at' => now()])`
4. `AuditLog::create()`

### hasKey()

Query `SoftHsmToken` table for a non-deleted row with `federation_id`.
Optionally verify with `softhsm2-util --show-slots` if stricter check needed.

### hasCert()

Run `pkcs11-tool --list-objects --type cert --token-label "{alias}"` and check output
for the certificate object with the expected label. Returns `false` if pkcs11-tool
is not installed or token not found.

### keyInfo()

Run `pkcs11-tool --list-objects --type privkey --token-label "{alias}"` and parse
key type and size from output. Return shape: `['type' => 'RSA', 'bits' => 2048, ...]`.

### certInfo()

1. `pkcs11-tool --read-object --type cert --label "{alias}" --token-label "{alias}"
   --output-file {tmp_der}`
2. Convert DER to PEM: `openssl x509 -inform DER -in {tmp_der} -out {tmp_pem}`
3. `openssl_x509_parse(file_get_contents(tmp_pem))`
4. Return same shape as `FileSigningDriver::certInfo()`
5. Unlink temp files in finally

### healthCheck()

Check in order — stop at first FAIL:
1. `softhsm2-util` binary exists and is executable → FAIL if not
2. `pkcs11-tool` binary exists and is executable → FAIL if not
3. `PKCS11_LIBRARY` (`libsofthsm2.so`) file exists → FAIL if not
4. `SOFTHSM2_CONF` is set and file is readable → WARN if not set

### viewName()

Return `'signing-keys.softhsm'`.

---

## STEP 3b — SignedMetadataValidator

Create `app/Services/Signing/SignedMetadataValidator.php`.

This class is called by **both** `FileSigningDriver::sign()` and `SoftHsmSigningDriver::sign()`
immediately after xmlsectool returns signed XML and before that XML is returned to the caller.

If any invariant fails, throw `XmlSigningException` — never return output that fails validation.

### Invariants checked

```php
class SignedMetadataValidator
{
    /**
     * @throws XmlSigningException if the signed output fails any structural invariant.
     */
    public function validate(string $inputXml, string $signedXml): void
    {
        $input  = $this->parse($inputXml,  'input');
        $signed = $this->parse($signedXml, 'signed output');

        $this->assertEntitiesDescriptorRoot($signed);
        $this->assertEntityCountMatches($input, $signed);
        $this->assertEntityIdsMatch($input, $signed);
        $this->assertValidUntilSane($signed);
    }
}
```

**`assertEntitiesDescriptorRoot()`**
`<EntitiesDescriptor>` must be the root element (namespace `urn:oasis:names:tc:SAML:2.0:metadata`).
Throws if the root is anything else — catches a case where xmlsectool returned an error document.

**`assertEntityCountMatches()`**
Count `<EntityDescriptor>` elements in input and signed output.
Throws if the count differs — catches truncation or injection of extra entities.

**`assertEntityIdsMatch()`**
Collect all `entityID` attribute values from `<EntityDescriptor>` in both documents.
Throws if the sets differ (symmetric difference is non-empty) — catches entity substitution.

**`assertValidUntilSane()`**
If `validUntil` attribute is present on the root, parse it and verify:
- It is in the future (not already expired)
- It is no more than 14 days from now (catches a date set absurdly far in the future)
If `validUntil` is absent, skip this check — it is optional in SAML metadata.

### Usage in both drivers

At the end of `sign()` in `FileSigningDriver` and `SoftHsmSigningDriver`, after reading
`$outFile` back:

```php
$signedXml = file_get_contents($outFile);
app(SignedMetadataValidator::class)->validate($xml, $signedXml);
return $signedXml;
```

If validation throws, the signed file is discarded and the exception propagates to the caller
(`GenerateMetadataJob`), which logs it and does NOT cache or persist the output.

---

## STEP 4 — SigningDriverFactory

Create `app/Services/Signing/SigningDriverFactory.php`:

```php
class SigningDriverFactory
{
    /** @var array<string, class-string<SigningDriver>> */
    private array $drivers = [
        'file'    => FileSigningDriver::class,
        'softhsm' => SoftHsmSigningDriver::class,
    ];

    public function make(Federation $federation): SigningDriver
    {
        $name = $federation->signing_driver ?? 'file';

        if (! isset($this->drivers[$name])) {
            throw new \InvalidArgumentException("Unknown signing driver: {$name}");
        }

        return app($this->drivers[$name]);
    }

    /** @return array<string, string> name => label for active drivers only */
    public function activeDrivers(): array
    {
        $map = [
            'file'    => ['label' => 'Local file (PEM on disk)',  'env' => 'FILE_SIGNING_IS_ACTIVE'],
            'softhsm' => ['label' => 'SoftHSM2 (PKCS#11 token)', 'env' => 'SOFTHSM_SIGNING_IS_ACTIVE'],
        ];

        return collect($map)
            ->filter(fn($d) => config('federation.signing_drivers.' . $d['env'], false))
            ->mapWithKeys(fn($d, $key) => [$key => $d['label']])
            ->all();
    }
}
```

Register in `AppServiceProvider::register()`:

```php
$this->app->singleton(SigningDriverFactory::class);
```

---

## STEP 5 — Migrations

### Migration A — add `signing_driver` to federations, drop timestamp columns

```php
Schema::table('federations', function (Blueprint $table) {
    $table->string('signing_driver', 50)->default('file')->after('slug');
    $table->dropColumn(['signing_key_uploaded_at', 'signing_cert_uploaded_at']);
});
```

Existing federations that have key files on disk keep `signing_driver = 'file'`
(the default), so nothing breaks.

### Migration B — create `softhsm_tokens` table

```php
Schema::create('softhsm_tokens', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('federation_id')->constrained()->cascadeOnDelete();
    $table->string('token_label', 255);
    $table->string('slot_id', 64);
    $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();

    $table->index('federation_id');
});
```

---

## STEP 6 — SoftHsmToken model

Create `app/Models/SoftHsmToken.php`:

```php
class SoftHsmToken extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['federation_id', 'token_label', 'slot_id', 'created_by'];

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

Add to `Federation` model:

```php
public function softHsmToken(): HasOne
{
    return $this->hasOne(SoftHsmToken::class)->whereNull('deleted_at');
}
```

---

## STEP 7 — config/federation.php

Add:

```php
'signing_drivers' => [
    'FILE_SIGNING_IS_ACTIVE'    => env('FILE_SIGNING_IS_ACTIVE', true),
    'SOFTHSM_SIGNING_IS_ACTIVE' => env('SOFTHSM_SIGNING_IS_ACTIVE', false),
],

// SoftHSM2 / PKCS#11
'pkcs11_library'  => env('PKCS11_LIBRARY', '/usr/lib/softhsm/libsofthsm2.so'),
'softhsm2_conf'   => env('SOFTHSM2_CONF', '/etc/softhsm2.conf'),
'softhsm_pin'     => env('JAGGER_HSM_PIN'),
'softhsm_so_pin'  => env('JAGGER_HSM_SO_PIN'),
```

Remove:

```php
'signing_key'  => env('FEDERATION_SIGNING_KEY'),
'signing_cert' => env('FEDERATION_SIGNING_CERT'),
```

---

## STEP 8 — .env / .env.example

Add:

```
FILE_SIGNING_IS_ACTIVE=true
SOFTHSM_SIGNING_IS_ACTIVE=false
PKCS11_LIBRARY=/usr/lib/softhsm/libsofthsm2.so
SOFTHSM2_CONF=/etc/softhsm2.conf
JAGGER_HSM_PIN=
JAGGER_HSM_SO_PIN=
```

Remove:

```
FEDERATION_SIGNING_KEY=
FEDERATION_SIGNING_CERT=
```

---

## STEP 9 — Federation creation — driver selector

### `FederationController::create()`

```php
public function create(): View
{
    Gate::authorize('create', Federation::class);
    $signingDrivers = app(SigningDriverFactory::class)->activeDrivers();
    return view('federations.create', compact('signingDrivers'));
}
```

### `federations/create.blade.php`

Add after the existing fields:

```html
<div class="mb-3">
    <label class="form-label">Signing Backend</label>
    <select name="signing_driver" class="form-select form-select-sm" required>
        @foreach($signingDrivers as $value => $label)
            <option value="{{ $value }}" {{ old('signing_driver') === $value ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
</div>
```

### `FederationController::store()`

Add to validation rules:

```php
'signing_driver' => ['required', Rule::in(array_keys(app(SigningDriverFactory::class)->activeDrivers()))],
```

---

## STEP 10 — FederationSigningKeys Livewire

Replace all direct file I/O with driver calls.

Resolve the driver at the top of the component:

```php
private function driver(): SigningDriver
{
    return app(SigningDriverFactory::class)->make($this->federation);
}
```

Replace every call:
- `file_put_contents(signingKeyPath(), ...)` → `$this->driver()->storeKey($this->federation, $pem)`
- `file_put_contents(signingCertPath(), ...)` → `$this->driver()->storeCert($this->federation, $pem)`
- `file_exists(signingKeyPath())` → `$this->driver()->hasKey($this->federation)`
- `file_exists(signingCertPath())` → `$this->driver()->hasCert($this->federation)`
- `openssl_pkey_get_details(...)` block → `$this->driver()->keyInfo($this->federation)`
- `openssl_x509_parse(...)` block → `$this->driver()->certInfo($this->federation)`
- `unlink(...)` blocks in `deleteBoth()` → `$this->driver()->deleteAll($this->federation)`

Remove `$credentialModal` computation from PHP — each driver returns a pre-shaped array
from `keyInfo()` / `certInfo()` so the view renders the array directly.

---

## STEP 11 — Driver view partials

### `resources/views/livewire/partials/signing-keys-file.blade.php`

Extract the current `federation-signing-keys.blade.php` content verbatim.
No logic changes — just a move.

### `resources/views/livewire/partials/signing-keys-softhsm.blade.php`

New partial. Show:
- Token status: initialised (green, from `softhsmToken` relation) / not initialised (warning)
- PIN configuration status: `JAGGER_HSM_PIN` set in `.env` (check via `config('federation.softhsm_pin')`)
- Import flow: upload PEM key → `storeKey()` inits token + imports key into token
- Upload PEM cert → `storeCert()` imports cert object into token (NOT to disk)
- Key info / cert info modals — same modal structure as file partial
  (cert info read from token via pkcs11-tool, not from disk)
- Delete token button → `deleteAll()` with SwalDefault confirmation
- Security note: "Keys are stored in a software token. Root access to this server
  allows signing outside this application."

### `resources/views/livewire/federation-signing-keys.blade.php`

Replace body with:

```html
@include('livewire.partials.' . $driverViewName)
```

Add to `render()`:

```php
'driverViewName' => $this->driver()->viewName(),
```

---

## STEP 12 — GenerateMetadataJob

Replace:

```php
$signer->sign($xml, $federation)
```

With:

```php
app(SigningDriverFactory::class)->make($federation)->sign($xml, $federation)
```

Remove the `XmlsectoolSigner $signer` constructor injection entirely.

---

## STEP 13 — FederationMetadataSign Livewire

Replace:

```php
$pairOk = $federation->hasUploadedSigningKey() && $federation->hasUploadedSigningCert();
$globalKeySet = (bool) config('federation.signing_key') && (bool) config('federation.signing_cert');
$canSign = $pairOk || $globalKeySet;
```

With:

```php
$driver = app(SigningDriverFactory::class)->make($federation);
$canSign = $driver->hasKey($federation) && $driver->hasCert($federation);
```

Remove `$globalKeySet` and `$pairOk` — they no longer exist.
Update `$signingKeyLabel` accordingly (no global fallback label).

---

## STEP 14 — Delete XmlsectoolSigner

Delete `app/Services/Metadata/XmlsectoolSigner.php`.

Verify no remaining references:

```bash
grep -r "XmlsectoolSigner" app/ resources/ tests/
```

Fix any found (tests: update to use `FileSigningDriver` or mock `SigningDriver`).

---

## STEP 15 — SigningHealthCheck

Create `app/Services/HealthChecks/SigningHealthCheck.php`:

```php
class SigningHealthCheck implements HealthCheck
{
    public function run(): HealthCheckResult
    {
        $factory = app(SigningDriverFactory::class);
        $results = [];

        foreach ($factory->activeDrivers() as $name => $label) {
            $driver = $factory->make(new Federation(['signing_driver' => $name]));
            $result = $driver->healthCheck();
            $results[] = "[{$label}] " . $result->message;

            if ($result->status === CheckStatus::Fail) {
                return HealthCheckResult::fail(implode('; ', $results));
            }
        }

        $hasWarn = collect($results)->contains(fn($r) => str_contains($r, 'warn'));

        return $hasWarn
            ? HealthCheckResult::warn(implode('; ', $results))
            : HealthCheckResult::ok('All active signing drivers healthy');
    }
}
```

Register in `HealthCheckRunner` in place of `XmlsectoolCheck`.

Delete `app/Services/HealthChecks/XmlsectoolCheck.php`.

---

## STEP 16 — HealthUiController

Replace `XmlsectoolCheck` reference with `SigningHealthCheck`.
Update action items / help text to reference driver-specific setup.
Add a note about the SoftHSM2 bypass risk and audit log monitoring.

---

## STEP 17 — Installer: SoftHSM2 step

Add a new step between current step 1 (Requirements) and step 2 (Database).
Renumber existing steps 2–5 → 3–6.

### What the step does

1. Detect `softhsm2-util` and `pkcs11-tool` binary presence
2. If both found: show green status, write `SOFTHSM_SIGNING_IS_ACTIVE=true` on proceed
3. If not found: show install instructions
   ```
   sudo apt install softhsm2 opensc
   ```
   Option A: "Install & continue" — runs `apt-get install -y softhsm2 opensc` via
   `Process::fromShellCommandline()`; writes `SOFTHSM_SIGNING_IS_ACTIVE=true` on success
   Option B: "Skip" — writes `SOFTHSM_SIGNING_IS_ACTIVE=false`; file driver remains available

`FILE_SIGNING_IS_ACTIVE=true` is always written regardless of outcome.

`JAGGER_HSM_PIN` and `JAGGER_HSM_SO_PIN` are NOT set by the installer — the operator
sets them manually in `.env` after installation (they are secrets).

Display a security notice on this step explaining the SoftHSM2 bypass risk (root access
+ PIN = ability to sign outside the application).

---

## STEP 18 — Federation model cleanup

Remove from `app/Models/Federation.php`:
- `signingKeyPath(): string`
- `signingCertPath(): string`
- `hasUploadedSigningKey(): bool`
- `hasUploadedSigningCert(): bool`
- `signing_key_uploaded_at` from `$casts`
- `signing_cert_uploaded_at` from `$casts`

Add:
- `signing_driver` to `$fillable`
- `softHsmToken(): HasOne` (from Step 6)

Keep `metadataPath(bool $eduGainOnly): string` — unrelated to signing.

Note: move path computation into driver private helpers (Step 2/3) BEFORE removing
from the model, or the drivers will break.

Also update `FederationController::forceDelete()` — it currently manually deletes
`storage/app/signing-keys/{id}/` directory. Replace with:
`app(SigningDriverFactory::class)->make($federation)->deleteAll($federation)`
so driver-specific cleanup runs correctly for both file and softhsm.

---

## STEP 19 — CONTEXT.md

At the end of `docs/dev/CONTEXT.md` add a session block following the standard format.

---

## Key files reference

| File | Action |
|------|--------|
| `app/Services/Signing/Contracts/SigningDriver.php` | CREATE |
| `app/Services/Signing/FileSigningDriver.php` | CREATE |
| `app/Services/Signing/SoftHsmSigningDriver.php` | CREATE |
| `app/Services/Signing/SignedMetadataValidator.php` | CREATE |
| `app/Services/Signing/SigningDriverFactory.php` | CREATE |
| `app/Models/SoftHsmToken.php` | CREATE |
| `app/Services/HealthChecks/SigningHealthCheck.php` | CREATE |
| `resources/views/livewire/partials/signing-keys-file.blade.php` | CREATE |
| `resources/views/livewire/partials/signing-keys-softhsm.blade.php` | CREATE |
| `database/migrations/..._add_signing_driver_to_federations.php` | CREATE |
| `database/migrations/..._create_softhsm_tokens_table.php` | CREATE |
| `app/Services/Metadata/XmlsectoolSigner.php` | DELETE |
| `app/Services/HealthChecks/XmlsectoolCheck.php` | DELETE |
| `app/Models/Federation.php` | MODIFY |
| `app/Jobs/GenerateMetadataJob.php` | MODIFY |
| `app/Livewire/FederationSigningKeys.php` | MODIFY |
| `app/Livewire/FederationMetadataSign.php` | MODIFY |
| `app/Http/Controllers/FederationController.php` | MODIFY |
| `app/Http/Controllers/HealthUiController.php` | MODIFY |
| `app/Providers/AppServiceProvider.php` | MODIFY |
| `app/Services/HealthChecks/HealthCheckRunner.php` | MODIFY |
| `resources/views/livewire/federation-signing-keys.blade.php` | MODIFY |
| `resources/views/federations/create.blade.php` | MODIFY |
| `resources/views/install/steps/*.blade.php` | MODIFY (renumber + new step) |
| `app/Http/Controllers/Install/InstallController.php` | MODIFY |
| `config/federation.php` | MODIFY |
| `.env.example` | MODIFY |
| `docs/dev/CONTEXT.md` | MODIFY |
