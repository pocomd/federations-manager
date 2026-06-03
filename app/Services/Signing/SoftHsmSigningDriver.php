<?php

declare(strict_types=1);

namespace App\Services\Signing;

use App\Exceptions\XmlSigningException;
use Carbon\Carbon;
use App\Models\AuditLog;
use App\Models\Federation;
use App\Models\SoftHsmToken;
use App\Services\HealthChecks\HealthCheckResult;
use App\Services\Signing\Contracts\SigningDriver;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Process\Process;
use Throwable;

class SoftHsmSigningDriver implements SigningDriver
{
    public function __construct(
        private readonly SignedMetadataValidator $validator,
    ) {}

    public function sign(string $xml, Federation $federation): string
    {
        $tool = config('federation.xmlsectool_path');

        if (! $tool) {
            throw new XmlSigningException('XMLSECTOOL_PATH is not configured.');
        }

        $this->writePkcs11Config($federation);

        $tmpDir = storage_path('app/temp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $inFile  = tempnam($tmpDir, 'xmlsec_in_');
        $outFile = tempnam($tmpDir, 'xmlsec_out_');

        try {
            file_put_contents($inFile, $xml);

            // No --certificate flag: xmlsectool finds the cert in the token by matching CKA_ID
            $process = new Process([
                $tool,
                '--sign',
                '--digest',                   'SHA-256',
                '--referenceIdAttributeName', 'ID',
                '--pkcs11Config',             $this->pkcs11ConfigPath($federation),
                '--keyAlias',                 $this->keyAlias($federation),
                '--keyPassword',              (string) config('federation.softhsm_pin'),
                '--inFile',                   $inFile,
                '--outFile',                  $outFile,
            ], env: $this->javaEnv());

            $process->run();

            if (! $process->isSuccessful()) {
                throw new XmlSigningException(
                    'xmlsectool PKCS#11 signing failed: ' .
                    trim($process->getErrorOutput() ?: $process->getOutput())
                );
            }

            if (! file_exists($outFile) || filesize($outFile) === 0) {
                throw new XmlSigningException('xmlsectool produced no output file.');
            }

            $signedXml = file_get_contents($outFile);

            $this->validator->validate($xml, $signedXml);

            AuditLog::create([
                'user_id'    => Auth::id(),
                'entity_id'  => null,
                'action'     => 'federation_metadata_signed',
                'old_values' => null,
                'new_values' => ['federation_id' => $federation->id, 'driver' => 'softhsm'],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'system',
            ]);

            return $signedXml;
        } finally {
            if (file_exists($inFile)) {
                unlink($inFile);
            }
            if (file_exists($outFile)) {
                unlink($outFile);
            }
        }
    }

    public function hasKey(Federation $federation): bool
    {
        return SoftHsmToken::where('federation_id', $federation->id)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function hasCert(Federation $federation): bool
    {
        $alias  = $this->keyAlias($federation);
        $lib    = config('federation.pkcs11_library');
        $pin    = config('federation.softhsm_pin');

        $process = new Process([
            'pkcs11-tool', '--module', $lib,
            '--list-objects', '--type', 'cert',
            '--token-label', $alias,
            '--pin', (string) $pin,
        ]);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful()
            && str_contains($process->getOutput(), $alias);
    }

    public function storeKey(Federation $federation, string $pem): void
    {
        $alias = $this->keyAlias($federation);
        $pin   = (string) config('federation.softhsm_pin');
        $soPin = (string) config('federation.softhsm_so_pin', $pin);
        $lib   = (string) config('federation.pkcs11_library');

        $tmpDir = storage_path('app/temp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpKey = tempnam($tmpDir, 'hsm_key_');

        try {
            file_put_contents($tmpKey, $pem);
            chmod($tmpKey, 0600);

            // Initialize a new token for this federation
            $init = new Process([
                'softhsm2-util', '--init-token',
                '--slot', '0',
                '--label', $alias,
                '--so-pin', $soPin,
                '--pin', $pin,
            ]);
            $init->setTimeout(15);
            $init->run();

            if (! $init->isSuccessful()) {
                throw new \RuntimeException(
                    'softhsm2-util --init-token failed: ' . trim($init->getOutput() . $init->getErrorOutput())
                );
            }

            // Find the assigned slot ID by label
            $slotId = $this->findSlotByLabel($alias);

            if ($slotId === null) {
                throw new \RuntimeException("Could not locate SoftHSM2 slot for token \"{$alias}\" after initialisation.");
            }

            // Store in DB
            SoftHsmToken::create([
                'federation_id' => $federation->id,
                'token_label'   => $alias,
                'slot_id'       => $slotId,
                'created_by'    => Auth::id(),
            ]);

            // Import the private key into the token
            $import = new Process([
                'softhsm2-util', '--import', $tmpKey,
                '--token', $alias,
                '--label', $alias,
                '--id', '01',
                '--pin', $pin,
            ]);
            $import->setTimeout(15);
            $import->run();

            if (! $import->isSuccessful()) {
                throw new \RuntimeException(
                    'softhsm2-util --import failed: ' . trim($import->getOutput() . $import->getErrorOutput())
                );
            }

            // Write pkcs11.cfg
            $this->writePkcs11Config($federation);

        } finally {
            if ($tmpKey && file_exists($tmpKey)) {
                unlink($tmpKey);
            }
        }

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'federation_signing_key_uploaded',
            'old_values' => null,
            'new_values' => ['federation_id' => $federation->id, 'federation_name' => $federation->name, 'driver' => 'softhsm'],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function storeCert(Federation $federation, string $pem): void
    {
        $alias  = $this->keyAlias($federation);
        $lib    = (string) config('federation.pkcs11_library');
        $pin    = (string) config('federation.softhsm_pin');

        $tmpDir = storage_path('app/temp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpDer = tempnam($tmpDir, 'hsm_cert_');

        try {
            // Convert PEM → DER for pkcs11-tool
            $x509 = @openssl_x509_read($pem);
            if ($x509 === false) {
                throw new \RuntimeException('Could not parse certificate PEM.');
            }

            $derProcess = new Process(['openssl', 'x509', '-outform', 'DER', '-out', $tmpDer]);
            $derProcess->setInput($pem);
            $derProcess->run();

            if (! $derProcess->isSuccessful() || ! file_exists($tmpDer) || filesize($tmpDer) === 0) {
                throw new \RuntimeException('Could not convert certificate to DER format.');
            }

            $write = new Process([
                'pkcs11-tool', '--module', $lib,
                '--write-object', $tmpDer,
                '--type', 'cert',
                '--label', $alias,
                '--id', '01',
                '--token-label', $alias,
                '--pin', $pin,
            ]);
            $write->setTimeout(15);
            $write->run();

            if (! $write->isSuccessful()) {
                throw new \RuntimeException(
                    'pkcs11-tool --write-object failed: ' . trim($write->getOutput() . $write->getErrorOutput())
                );
            }
        } finally {
            if ($tmpDer && file_exists($tmpDer)) {
                unlink($tmpDer);
            }
        }

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'federation_signing_cert_uploaded',
            'old_values' => null,
            'new_values' => ['federation_id' => $federation->id, 'federation_name' => $federation->name, 'driver' => 'softhsm'],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function deleteAll(Federation $federation): void
    {
        $alias = $this->keyAlias($federation);

        // Delete the SoftHSM2 token (suppress errors if already gone)
        $delete = new Process(['softhsm2-util', '--delete-token', '--token', $alias]);
        $delete->setTimeout(10);
        $delete->run();

        // Remove pkcs11.cfg from disk
        $cfgPath = $this->pkcs11ConfigPath($federation);
        if (file_exists($cfgPath)) {
            unlink($cfgPath);
        }

        // Soft-delete the DB row
        SoftHsmToken::where('federation_id', $federation->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'federation_signing_credentials_deleted',
            'old_values' => ['federation_id' => $federation->id, 'federation_name' => $federation->name, 'driver' => 'softhsm'],
            'new_values' => null,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function keyInfo(Federation $federation): ?array
    {
        if (! $this->hasKey($federation)) {
            return null;
        }

        $alias = $this->keyAlias($federation);
        $lib   = config('federation.pkcs11_library');
        $pin   = config('federation.softhsm_pin');

        $process = new Process([
            'pkcs11-tool', '--module', $lib,
            '--list-objects', '--type', 'privkey',
            '--token-label', $alias,
            '--pin', (string) $pin,
        ]);
        $process->setTimeout(10);
        $process->run();

        $output  = $process->getOutput();
        $keyType = 'RSA';
        $bits    = '—';

        if (preg_match('/Key Length:\s*(\d+)/i', $output, $m)) {
            $bits = $m[1];
        }
        if (preg_match('/Key Type:\s*(\w+)/i', $output, $m)) {
            $keyType = strtoupper($m[1]);
        }

        $token = SoftHsmToken::where('federation_id', $federation->id)
            ->whereNull('deleted_at')
            ->first();

        return [
            'type'       => 'key',
            'key_type'   => $keyType,
            'bits'       => $bits,
            'preview'    => null,
            'created_at' => $token?->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
        ];
    }

    public function certInfo(Federation $federation): ?array
    {
        if (! $this->hasKey($federation)) {
            return null;
        }

        $alias  = $this->keyAlias($federation);
        $lib    = config('federation.pkcs11_library');
        $pin    = config('federation.softhsm_pin');

        $tmpDir = storage_path('app/temp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpDer = tempnam($tmpDir, 'hsm_crt_');
        $tmpPem = tempnam($tmpDir, 'hsm_crtp_');

        try {
            $read = new Process([
                'pkcs11-tool', '--module', $lib,
                '--read-object', '--type', 'cert',
                '--label', $alias,
                '--token-label', $alias,
                '--output-file', $tmpDer,
                '--pin', (string) $pin,
            ]);
            $read->setTimeout(10);
            $read->run();

            if (! $read->isSuccessful() || ! file_exists($tmpDer) || filesize($tmpDer) === 0) {
                return null;
            }

            $convert = new Process(['openssl', 'x509', '-inform', 'DER', '-in', $tmpDer, '-out', $tmpPem]);
            $convert->run();

            if (! $convert->isSuccessful() || ! file_exists($tmpPem)) {
                return null;
            }

            $pem  = (string) file_get_contents($tmpPem);
            $info = @openssl_x509_parse($pem);

            if (! $info) {
                return null;
            }

            $now      = time();
            $validTo  = $info['validTo_time_t'] ?? null;
            $expired  = $validTo !== null && $validTo < $now;
            $daysLeft = $validTo !== null ? (int) (($validTo - $now) / 86400) : null;

            [$validityClass, $validityLabel] = $this->validityBadge($expired, $daysLeft);

            return [
                'type'           => 'cert',
                'subject'        => $this->formatDn($info['subject'] ?? []),
                'issuer'         => $this->formatDn($info['issuer'] ?? []),
                'serial'         => $info['serialNumberHex'] ?? ($info['serialNumber'] ?? '—'),
                'valid_from'     => isset($info['validFrom_time_t'])
                    ? Carbon::createFromTimestamp($info['validFrom_time_t'])->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
                    : '—',
                'valid_to'       => $validTo ? Carbon::createFromTimestamp($validTo)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '—',
                'validity_class' => $validityClass,
                'validity_label' => $validityLabel,
                'pem'            => trim($pem),
                'created_at'     => null,
            ];
        } finally {
            foreach ([$tmpDer, $tmpPem] as $f) {
                if ($f && file_exists($f)) {
                    unlink($f);
                }
            }
        }
    }

    public function healthCheck(): HealthCheckResult
    {
        $start = hrtime(true);

        $softhsmUtil = trim(shell_exec('which softhsm2-util 2>/dev/null') ?? '');
        if (! $softhsmUtil || ! is_executable($softhsmUtil)) {
            return HealthCheckResult::fail(
                'signing:softhsm',
                'softhsm2-util not found — install the softhsm2 package',
                $this->ms($start)
            );
        }

        $pkcs11tool = trim(shell_exec('which pkcs11-tool 2>/dev/null') ?? '');
        if (! $pkcs11tool || ! is_executable($pkcs11tool)) {
            return HealthCheckResult::fail(
                'signing:softhsm',
                'pkcs11-tool not found — install the opensc package',
                $this->ms($start)
            );
        }

        $lib = (string) config('federation.pkcs11_library');
        if (! file_exists($lib)) {
            return HealthCheckResult::fail(
                'signing:softhsm',
                "PKCS#11 library not found: \"{$lib}\" — check PKCS11_LIBRARY in .env",
                $this->ms($start)
            );
        }

        $conf = (string) config('federation.softhsm2_conf');
        if (! $conf || ! is_readable($conf)) {
            return HealthCheckResult::warn(
                'signing:softhsm',
                "SOFTHSM2_CONF not set or not readable: \"{$conf}\"",
                $this->ms($start)
            );
        }

        return HealthCheckResult::ok('signing:softhsm', 'SoftHSM2 binaries and library present', $this->ms($start));
    }

    public function viewName(): string
    {
        return 'signing-keys-softhsm';
    }

    private function keyAlias(Federation $federation): string
    {
        return "jagger-fed-{$federation->id}";
    }

    private function pkcs11ConfigPath(Federation $federation): string
    {
        return storage_path("app/signing-keys/{$federation->id}/pkcs11.cfg");
    }

    private function writePkcs11Config(Federation $federation): void
    {
        $token = SoftHsmToken::where('federation_id', $federation->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $dir = dirname($this->pkcs11ConfigPath($federation));
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $config = implode("\n", [
            'name = softhsm2',
            'library = ' . config('federation.pkcs11_library'),
            'slot = ' . $token->slot_id,
        ]);

        file_put_contents($this->pkcs11ConfigPath($federation), $config);
    }

    private function findSlotByLabel(string $label): ?string
    {
        $process = new Process(['softhsm2-util', '--show-slots']);
        $process->setTimeout(10);
        $process->run();

        $output  = $process->getOutput();
        $slotId  = null;
        $inBlock = false;

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^Slot\s+(\d+)/', $line, $m)) {
                $inBlock  = true;
                $slotId   = $m[1];
            }
            if ($inBlock && str_contains($line, "Label:") && str_contains($line, $label)) {
                return $slotId;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private function javaEnv(): array
    {
        return JavaEnvironment::env();
    }

    private function ms(int $start): int
    {
        return (int) ((hrtime(true) - $start) / 1_000_000);
    }

    /** @param array<string,string> $dn */
    private function formatDn(array $dn): string
    {
        $parts = [];
        foreach (['CN', 'O', 'OU', 'L', 'ST', 'C'] as $key) {
            if (! empty($dn[$key])) {
                $parts[] = "{$key}={$dn[$key]}";
            }
        }
        return $parts ? implode(', ', $parts) : '—';
    }

    /** @return array{string, string} [class, label] */
    private function validityBadge(bool $expired, ?int $daysLeft): array
    {
        if ($expired) {
            return ['danger', 'Expired'];
        }
        if ($daysLeft !== null && $daysLeft <= 30) {
            return ['warning', $daysLeft . ' days remaining'];
        }
        if ($daysLeft !== null && $daysLeft <= 90) {
            return ['info', $daysLeft . ' days remaining'];
        }
        return ['success', $daysLeft !== null ? $daysLeft . ' days remaining' : '—'];
    }
}
