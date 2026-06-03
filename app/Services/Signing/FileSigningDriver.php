<?php

declare(strict_types=1);

namespace App\Services\Signing;

use App\Exceptions\XmlSigningException;
use Carbon\Carbon;
use App\Models\AuditLog;
use App\Models\Federation;
use App\Services\HealthChecks\CheckStatus;
use App\Services\HealthChecks\HealthCheckResult;
use App\Services\Signing\Contracts\SigningDriver;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Process\Process;
use Throwable;

class FileSigningDriver implements SigningDriver
{
    private const MINIMAL_XML = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <md:EntitiesDescriptor
            xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
            xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
            ID="_healthcheck">
        </md:EntitiesDescriptor>
        XML;

    public function __construct(
        private readonly SignedMetadataValidator $validator,
    ) {}

    public function sign(string $xml, Federation $federation): string
    {
        $tool = config('federation.xmlsectool_path');
        $key  = $this->keyPath($federation);
        $cert = $this->certPath($federation);

        if (! $tool) {
            throw new XmlSigningException('XMLSECTOOL_PATH is not configured.');
        }

        if (! file_exists($key)) {
            throw new XmlSigningException("No signing key on disk for federation \"{$federation->name}\".");
        }

        if (! file_exists($cert)) {
            throw new XmlSigningException("No signing certificate on disk for federation \"{$federation->name}\".");
        }

        $tmpDir = storage_path('app/temp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $inFile  = tempnam($tmpDir, 'xmlsec_in_');
        $outFile = tempnam($tmpDir, 'xmlsec_out_');

        try {
            file_put_contents($inFile, $xml);

            $process = new Process([
                $tool,
                '--sign',
                '--digest',                   'SHA-256',
                '--referenceIdAttributeName', 'ID',
                '--inFile',                   $inFile,
                '--outFile',                  $outFile,
                '--keyFile',                  $key,
                '--certificate',              $cert,
            ], env: $this->javaEnv());

            $process->run();

            if (! $process->isSuccessful()) {
                throw new XmlSigningException(
                    'xmlsectool signing failed: ' . $this->parseError($process->getErrorOutput() ?: $process->getOutput())
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
                'new_values' => ['federation_id' => $federation->id, 'driver' => 'file'],
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
        return file_exists($this->keyPath($federation));
    }

    public function hasCert(Federation $federation): bool
    {
        return file_exists($this->certPath($federation));
    }

    public function storeKey(Federation $federation, string $pem): void
    {
        $path = $this->keyPath($federation);
        $dir  = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($path, $pem);
        chmod($path, 0600);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'federation_signing_key_uploaded',
            'old_values' => null,
            'new_values' => ['federation_id' => $federation->id, 'federation_name' => $federation->name],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function storeCert(Federation $federation, string $pem): void
    {
        $path = $this->certPath($federation);
        $dir  = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($path, $pem);
        chmod($path, 0600);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'federation_signing_cert_uploaded',
            'old_values' => null,
            'new_values' => ['federation_id' => $federation->id, 'federation_name' => $federation->name],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function deleteAll(Federation $federation): void
    {
        $keyPath  = $this->keyPath($federation);
        $certPath = $this->certPath($federation);
        $dir      = dirname($keyPath);

        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
        if (file_exists($certPath)) {
            unlink($certPath);
        }
        if (is_dir($dir) && count(scandir($dir)) === 2) {
            rmdir($dir);
        }

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'federation_signing_credentials_deleted',
            'old_values' => ['federation_id' => $federation->id, 'federation_name' => $federation->name],
            'new_values' => null,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function keyInfo(Federation $federation): ?array
    {
        $path = $this->keyPath($federation);

        if (! file_exists($path)) {
            return null;
        }

        $pem     = trim((string) file_get_contents($path));
        $key     = @openssl_pkey_get_private($pem);
        $details = $key ? openssl_pkey_get_details($key) : null;

        $typeLabels = [
            OPENSSL_KEYTYPE_RSA => 'RSA',
            OPENSSL_KEYTYPE_DSA => 'DSA',
            OPENSSL_KEYTYPE_DH  => 'DH',
            OPENSSL_KEYTYPE_EC  => 'EC',
        ];

        $lines       = array_filter(array_map('trim', explode("\n", $pem)));
        $headerLines = array_filter($lines, fn($l) => str_starts_with($l, '-----BEGIN'));
        $footerLines = array_filter($lines, fn($l) => str_starts_with($l, '-----END'));
        $header      = current($headerLines) ?: '';
        $footer      = end($footerLines) ?: '';
        $body        = implode('', array_filter($lines, fn($l) => ! str_starts_with($l, '-----')));
        $preview     = $header . "\n"
            . substr($body, 0, 50) . "\n...\n" . substr($body, -50) . "\n"
            . $footer;

        $mtime = filemtime($path);

        return [
            'type'       => 'key',
            'key_type'   => $details ? ($typeLabels[$details['type']] ?? 'Unknown') : '—',
            'bits'       => $details ? (string) $details['bits'] : '—',
            'preview'    => $preview,
            'created_at' => $mtime ? Carbon::createFromTimestamp($mtime)->timezone(config('app.timezone'))->format('Y-m-d H:i') : null,
        ];
    }

    public function certInfo(Federation $federation): ?array
    {
        $path = $this->certPath($federation);

        if (! file_exists($path)) {
            return null;
        }

        $pem  = (string) file_get_contents($path);
        $info = @openssl_x509_parse($pem);

        if (! $info) {
            return null;
        }

        $now      = time();
        $validTo  = $info['validTo_time_t'] ?? null;
        $expired  = $validTo !== null && $validTo < $now;
        $daysLeft = $validTo !== null ? (int) (($validTo - $now) / 86400) : null;

        [$validityClass, $validityLabel] = $this->validityBadge($expired, $daysLeft);

        $mtime = filemtime($path);

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
            'created_at'     => $mtime ? Carbon::createFromTimestamp($mtime)->timezone(config('app.timezone'))->format('Y-m-d H:i') : null,
        ];
    }

    public function healthCheck(): HealthCheckResult
    {
        $start = hrtime(true);
        $tool  = config('federation.xmlsectool_path');

        if (! $tool) {
            $ms = $this->ms($start);
            return HealthCheckResult::warn('signing:file', 'XMLSECTOOL_PATH not set — file-based signing unavailable', $ms);
        }

        if (! is_executable((string) $tool)) {
            $ms = $this->ms($start);
            return HealthCheckResult::fail('signing:file', "xmlsectool binary not executable: {$tool}", $ms);
        }

        return $this->roundTrip($tool, $start);
    }

    public function viewName(): string
    {
        return 'signing-keys-file';
    }

    private function keyPath(Federation $federation): string
    {
        return storage_path("app/signing-keys/{$federation->id}/signing.key");
    }

    private function certPath(Federation $federation): string
    {
        return storage_path("app/signing-keys/{$federation->id}/signing.crt");
    }

    private function roundTrip(string $tool, int $start): HealthCheckResult
    {
        $tmpDir = storage_path('app/temp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpKeyFile  = tempnam($tmpDir, 'hc_key_');
        $tmpCertFile = tempnam($tmpDir, 'hc_crt_');
        $inFile      = tempnam($tmpDir, 'hc_in_');
        $outFile     = tempnam($tmpDir, 'hc_out_');

        try {
            // Generate a temporary RSA key pair for the health-check round-trip
            $keyRes = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            if (! $keyRes) {
                return HealthCheckResult::fail('signing:file', 'Could not generate temporary RSA key for health check', $this->ms($start));
            }

            $keyPem = '';
            openssl_pkey_export($keyRes, $keyPem);

            $dn   = ['CN' => 'Jagger Health Check'];
            $csr  = openssl_csr_new($dn, $keyRes, ['digest_alg' => 'sha256']);
            $cert = openssl_csr_sign($csr, null, $keyRes, 1, ['digest_alg' => 'sha256']);
            $certPem = '';
            openssl_x509_export($cert, $certPem);

            file_put_contents($tmpKeyFile, $keyPem);
            file_put_contents($tmpCertFile, $certPem);
            file_put_contents($inFile, self::MINIMAL_XML);

            $process = new Process([
                $tool,
                '--sign',
                '--digest',                   'SHA-256',
                '--referenceIdAttributeName', 'ID',
                '--inFile',                   $inFile,
                '--outFile',                  $outFile,
                '--keyFile',                  $tmpKeyFile,
                '--certificate',              $tmpCertFile,
            ], env: $this->javaEnv());
            $process->setTimeout(15);
            $process->run();

            $ms = $this->ms($start);

            if (! $process->isSuccessful()) {
                return HealthCheckResult::fail(
                    'signing:file',
                    $this->parseError($process->getErrorOutput() ?: $process->getOutput()),
                    $ms
                );
            }

            if (! file_exists($outFile) || filesize($outFile) === 0) {
                return HealthCheckResult::fail('signing:file', 'Signing round-trip produced no output', $ms);
            }

            return HealthCheckResult::ok('signing:file', 'xmlsectool round-trip successful', $ms);
        } catch (Throwable $e) {
            return HealthCheckResult::fail('signing:file', 'Exception: ' . $e->getMessage(), $this->ms($start));
        } finally {
            foreach ([$tmpKeyFile, $tmpCertFile, $inFile, $outFile] as $f) {
                if ($f && file_exists($f)) {
                    unlink($f);
                }
            }
        }
    }

    private function parseError(string $raw): string
    {
        $lines  = array_filter(array_map('trim', explode("\n", $raw)));
        $errors = array_values(array_filter($lines, fn($l) => str_starts_with($l, 'ERROR')));

        if (empty($errors)) {
            return trim(implode(' ', array_slice($lines, 0, 2)));
        }

        return implode(' | ', array_map(
            fn($l) => preg_replace('/^ERROR\s+XMLSecTool\s+-\s+/', '', $l),
            $errors
        ));
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
