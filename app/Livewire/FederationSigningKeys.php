<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Federation;
use App\Services\Signing\Contracts\SigningDriver;
use App\Services\Signing\FileSigningDriver;
use App\Services\Signing\SigningDriverFactory;
use App\Services\Signing\SoftHsmSigningDriver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

class FederationSigningKeys extends Component
{
    use WithFileUploads;

    public Federation $federation;

    public int $step = 1;
    public string $pendingType = '';  // 'key' | 'cert' — what step 1 detected
    public string $pendingPem  = '';  // PEM held in memory during step 2 (not stored until pair complete)

    public $uploadedFile;
    public string $password = '';

    public ?array $credentialModal = null;

    // ── Driver migration ──────────────────────────────────────────────────────
    public int   $migrationStep = 0;  // 0=idle 1=running 2=done -1=failed
    public array $migrationLog  = [];
    public bool  $deletePemFiles = false;


    public function mount(Federation $federation): void
    {
        Gate::authorize('update', $federation);
        $this->federation = $federation;
    }

    private function checkAccess(Federation $federation): void
    {
        Gate::authorize('update', $federation);
    }

    private function driver(): SigningDriver
    {
        return app(SigningDriverFactory::class)->make($this->federation);
    }


    /**
     * Step 1: parse the uploaded file and detect content type.
     * Holds the PEM in memory (pendingPem) — nothing is stored yet.
     * If both credentials are present in the file, validates pair and stores both immediately.
     */
    public function detect(): void
    {
        $this->checkAccess($this->federation);

        $this->validate(['uploadedFile' => 'required|file|max:512']);

        $content = file_get_contents($this->uploadedFile->getRealPath());
        $result  = $this->detectContent($content);

        $this->reset('uploadedFile', 'password');

        if ($result['type'] === 'error') {
            $this->dispatch('notify', type: 'error', message: $result['message']);
            return;
        }

        if ($result['type'] === 'both') {
            $pairError = $this->validatePair($result['key'], $result['cert']);
            if ($pairError) {
                $this->dispatch('notify', type: 'error', message: $pairError);
                return;
            }
            $this->driver()->storeKey($this->federation, $result['key']);
            $this->driver()->storeCert($this->federation, $result['cert']);
            $this->federation->refresh();
            $this->dispatch('notify', type: 'success', message: 'Key pair uploaded and stored successfully.');
            return;
        }

        // Single credential — hold in memory and advance to step 2 to request the other
        $this->pendingPem  = $result['pem'];
        $this->pendingType = $result['type'];
        $this->step        = 2;
    }


    /**
     * Step 2: upload the missing credential, validate the pair, then store both.
     */
    public function complete(): void
    {
        $this->checkAccess($this->federation);

        $this->validate(['uploadedFile' => 'required|file|max:512']);

        $content      = file_get_contents($this->uploadedFile->getRealPath());
        $expectedType = $this->pendingType === 'key' ? 'cert' : 'key';
        $result       = $this->detectContent($content);

        $this->reset('uploadedFile', 'password');

        if ($result['type'] === 'error') {
            $this->dispatch('notify', type: 'error', message: $result['message']);
            return;
        }

        $detectedType = $result['type'] === 'both' ? $expectedType : $result['type'];

        if ($detectedType !== $expectedType) {
            $label = $expectedType === 'key' ? 'private key' : 'certificate';
            $this->dispatch('notify', type: 'error', message: "Expected a {$label} — upload the correct file or cancel and start over.");
            return;
        }

        $newPem = $result['type'] === 'both'
            ? ($expectedType === 'key' ? $result['key'] : $result['cert'])
            : $result['pem'];

        // Validate pair before storing either
        $pairError = $this->pendingType === 'key'
            ? $this->validatePair($this->pendingPem, $newPem)
            : $this->validatePair($newPem, $this->pendingPem);

        if ($pairError) {
            $this->dispatch('notify', type: 'error', message: $pairError);
            return;
        }

        // Store both now that the pair is validated
        if ($this->pendingType === 'key') {
            $this->driver()->storeKey($this->federation, $this->pendingPem);
            $this->driver()->storeCert($this->federation, $newPem);
        } else {
            $this->driver()->storeCert($this->federation, $this->pendingPem);
            $this->driver()->storeKey($this->federation, $newPem);
        }

        $this->pendingPem  = '';
        $this->pendingType = '';
        $this->step        = 1;
        $this->federation->refresh();
        $this->dispatch('notify', type: 'success', message: 'Key pair complete and stored successfully.');
    }


    public function cancel(): void
    {
        $this->step        = 1;
        $this->pendingType = '';
        $this->pendingPem  = '';
        $this->reset('uploadedFile', 'password');
    }


    public function deleteBoth(): void
    {
        $this->checkAccess($this->federation);
        $this->driver()->deleteAll($this->federation);
        $this->federation->refresh();
        $this->dispatch('notify', type: 'success', message: 'Signing key and certificate removed.');
    }


    public function showKeyInfo(): void
    {
        $this->checkAccess($this->federation);

        $info = $this->driver()->keyInfo($this->federation);

        if (! $info) {
            $this->dispatch('notify', type: 'error', message: 'No key found.');
            return;
        }

        $this->credentialModal = $info;
    }

    public function showCertInfo(): void
    {
        $this->checkAccess($this->federation);

        $info = $this->driver()->certInfo($this->federation);

        if (! $info) {
            $this->dispatch('notify', type: 'error', message: 'No certificate found.');
            return;
        }

        $this->credentialModal = $info;
    }

    public function closeModal(): void
    {
        $this->credentialModal = null;
    }


    public function render(): View
    {
        $driver        = $this->driver();
        $hasKey        = $driver->hasKey($this->federation);
        $hasCert       = $driver->hasCert($this->federation);
        $softHsmActive = (bool) config('federation.signing_drivers.SOFTHSM_SIGNING_IS_ACTIVE', false);

        return view('livewire.federation-signing-keys', [
            'hasKey'             => $hasKey,
            'hasCert'            => $hasCert,
            'driverViewName'     => $driver->viewName(),
            'softHsmActive'      => $softHsmActive,
            'migrationPreflight' => $softHsmActive ? $this->migrationPreflight() : null,
        ]);
    }

    // ── Migration: file → SoftHSM2 ────────────────────────────────────────────

    public function startMigration(): void
    {
        $this->checkAccess($this->federation);

        $this->migrationStep = 1;
        $this->migrationLog  = [];

        try {
            // Read the PEM files that the file driver has stored on disk.
            // We do this directly rather than through the driver API so that
            // even if signing_driver is changed mid-way, we still have the raw PEM.
            $keyPath  = storage_path("app/signing-keys/{$this->federation->id}/signing.key");
            $certPath = storage_path("app/signing-keys/{$this->federation->id}/signing.crt");

            if (! file_exists($keyPath)) {
                throw new \RuntimeException('signing.key not found — cannot read from file driver storage.');
            }
            if (! file_exists($certPath)) {
                throw new \RuntimeException('signing.crt not found — cannot read from file driver storage.');
            }

            $keyPem  = (string) file_get_contents($keyPath);
            $certPem = (string) file_get_contents($certPath);

            $this->migrationLog[] = ['ok', 'PEM files read from disk'];

            // Initialise a SoftHSM2 token for this federation and import the key.
            // SoftHsmSigningDriver::storeKey() handles init-token + softhsm2-util --import
            // in one call and creates the SoftHsmToken DB record.
            $softHsm = app(SoftHsmSigningDriver::class);
            $softHsm->storeKey($this->federation, $keyPem);

            $this->migrationLog[] = ['ok', 'Private key imported into SoftHSM2 token'];

            // Import the certificate as a PKCS#11 cert object via pkcs11-tool.
            $softHsm->storeCert($this->federation, $certPem);

            $this->migrationLog[] = ['ok', 'Certificate imported into token'];

            // Flip the signing driver. From this point forward all signing
            // operations for this federation will use SoftHSM2.
            $this->federation->update(['signing_driver' => 'softhsm']);

            $this->migrationLog[] = ['ok', 'Federation signing driver switched to SoftHSM2'];

            AuditLog::create([
                'user_id'    => Auth::id(),
                'entity_id'  => null,
                'action'     => 'federation_signing_driver_migrated',
                'old_values' => ['driver' => 'file'],
                'new_values' => ['driver' => 'softhsm', 'federation_id' => $this->federation->id],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            // Optionally remove the PEM files. Non-fatal if this fails —
            // the migration itself is already complete.
            if ($this->deletePemFiles) {
                app(FileSigningDriver::class)->deleteAll($this->federation);
                $this->migrationLog[] = ['ok', 'PEM files removed from disk'];
            }

            $this->federation->refresh();
            $this->migrationStep = 2;

        } catch (\Throwable $e) {
            $this->migrationLog[] = ['error', $e->getMessage()];
            $this->migrationStep  = -1;
        }
    }

    public function resetMigration(): void
    {
        $this->migrationStep  = 0;
        $this->migrationLog   = [];
        $this->deletePemFiles = false;
    }

    /** Returns pre-flight check results for the migration wizard. */
    private function migrationPreflight(): array
    {
        $disabled  = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
        $execOk    = ! in_array('exec', $disabled, true) && ! in_array('proc_open', $disabled, true);

        $softhsmBin = $this->shellBinary(['softhsm2-util', '/usr/bin/softhsm2-util', '/usr/local/bin/softhsm2-util']);
        $pkcs11Bin  = $this->shellBinary(['pkcs11-tool', '/usr/bin/pkcs11-tool', '/usr/local/bin/pkcs11-tool']);
        $pinSet     = (bool) config('federation.softhsm_pin');
        $confOk     = is_readable((string) config('federation.softhsm2_conf', ''));
        $libOk      = file_exists((string) config('federation.pkcs11_library', ''));

        return [
            'exec_ok'      => $execOk,
            'softhsm_bin'  => $softhsmBin,
            'pkcs11_bin'   => $pkcs11Bin,
            'pin_set'      => $pinSet,
            'conf_ok'      => $confOk,
            'lib_ok'       => $libOk,
            'wizard_ready' => $execOk && $softhsmBin && $pkcs11Bin && $pinSet && $confOk && $libOk,
        ];
    }

    private function shellBinary(array $candidates): ?string
    {
        foreach ($candidates as $bin) {
            if (str_contains($bin, '/') && is_executable($bin)) {
                return $bin;
            }
        }

        $disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
        if (! in_array('shell_exec', $disabled, true)) {
            foreach ($candidates as $bin) {
                $r = @shell_exec('which ' . escapeshellarg($bin) . ' 2>/dev/null');
                if ($r && ($p = trim($r)) !== '') {
                    return $p;
                }
            }
        }

        return null;
    }


    private function detectContent(string $content): array
    {
        $password = $this->password;

        $p12Certs  = [];
        $p12Parsed = @openssl_pkcs12_read($content, $p12Certs, $password);
        if (! $p12Parsed && $password !== '') {
            $p12Parsed = @openssl_pkcs12_read($content, $p12Certs, '');
        }
        if ($p12Parsed && isset($p12Certs['pkey'], $p12Certs['cert'])) {
            return ['type' => 'both', 'key' => $p12Certs['pkey'], 'cert' => $p12Certs['cert']];
        }

        if (str_contains($content, '-----BEGIN')) {
            $hasKey  = (bool) preg_match('/-----BEGIN (?:ENCRYPTED |RSA |EC )?PRIVATE KEY-----/', $content);
            $hasCert = (bool) preg_match('/-----BEGIN CERTIFICATE-----/', $content);

            if ($hasKey && $hasCert) {
                preg_match(
                    '/(-----BEGIN (?:ENCRYPTED |RSA |EC )?PRIVATE KEY-----.*?-----END (?:ENCRYPTED |RSA |EC )?PRIVATE KEY-----)/s',
                    $content,
                    $keyMatch
                );
                preg_match('/(-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----)/s', $content, $certMatch);

                if (! $keyMatch || ! $certMatch) {
                    return ['type' => 'error', 'message' => 'Could not extract key and certificate sections from the PEM file.'];
                }

                $keyPem = $this->exportPrivateKey($keyMatch[1], $password);
                if ($keyPem === null) {
                    return ['type' => 'error', 'message' => 'The private key is encrypted — enter the password and try again.'];
                }

                return ['type' => 'both', 'key' => $keyPem, 'cert' => $certMatch[1]];
            }

            if ($hasKey) {
                $keyPem = $this->exportPrivateKey($content, $password);
                if ($keyPem === null) {
                    return ['type' => 'error', 'message' => 'The private key is encrypted — enter the password and try again.'];
                }
                return ['type' => 'key', 'pem' => $keyPem];
            }

            if ($hasCert) {
                if (@openssl_x509_read($content) === false) {
                    return ['type' => 'error', 'message' => 'The certificate could not be parsed.'];
                }
                return ['type' => 'cert', 'pem' => $content];
            }

            return ['type' => 'error', 'message' => 'PEM file found but contains neither a private key nor a certificate.'];
        }

        return ['type' => 'error', 'message' => 'Unrecognized format. Upload a PEM (.pem, .key, .crt) or PKCS#12 (.p12, .pfx) file.'];
    }

    private function exportPrivateKey(string $content, string $password): ?string
    {
        $key = @openssl_pkey_get_private($content, $password !== '' ? $password : null);
        if ($key === false && $password !== '') {
            $key = @openssl_pkey_get_private($content);
        }
        if ($key === false) {
            return null;
        }
        $pem = '';
        openssl_pkey_export($key, $pem);
        return $pem ?: null;
    }

    private function validatePair(string $keyPem, string $certPem): ?string
    {
        $key  = @openssl_pkey_get_private($keyPem);
        $cert = @openssl_x509_read($certPem);

        if ($key === false || $cert === false) {
            return null;
        }

        $keyDetails  = openssl_pkey_get_details($key);
        $certPubKey  = openssl_pkey_get_public($cert);
        $certDetails = $certPubKey ? openssl_pkey_get_details($certPubKey) : false;

        if ($keyDetails && $certDetails && $keyDetails['key'] !== $certDetails['key']) {
            return 'The private key does not match the certificate — upload the correct matching file.';
        }

        return null;
    }
}
