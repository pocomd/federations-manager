<?php

declare(strict_types=1);

namespace App\Services\Entity;

use App\Models\EntityCertificate;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use OpenSSLCertificate;
use RuntimeException;

class CertificateService
{
    /**
     * Parse a PEM-encoded X.509 certificate.
     *
     * @return array{
     *   subject: string,
     *   issuer: string,
     *   serial: string,
     *   not_before: CarbonImmutable,
     *   not_after: CarbonImmutable,
     *   key_bits: int,
     *   key_algorithm: string,
     *   fingerprint: string,
     *   signature_algorithm: string,
     * }
     * @throws InvalidArgumentException if PEM is invalid
     */
    public function parse(string $pem): array
    {
        $cert = @openssl_x509_read($this->normalizePem($pem));

        if ($cert === false) {
            throw new InvalidArgumentException('Invalid PEM certificate: ' . openssl_error_string());
        }

        $parsed     = openssl_x509_parse($cert);
        $pubKey     = openssl_pkey_get_public($cert);
        $keyDetails = $pubKey ? openssl_pkey_get_details($pubKey) : [];

        if ($parsed === false) {
            throw new RuntimeException('Failed to parse certificate details.');
        }

        $fingerprint = openssl_x509_fingerprint($cert, 'sha256');
        if ($fingerprint === false) {
            $fingerprint = '';
        }

        // Format fingerprint as colon-separated hex pairs (standard display format)
        $fingerprint = implode(':', str_split(strtoupper($fingerprint), 2));

        return [
            'subject'             => $this->buildDnString($parsed['subject'] ?? []),
            'issuer'              => $this->buildDnString($parsed['issuer'] ?? []),
            'serial'              => strtoupper($parsed['serialNumberHex'] ?? dechex((int) ($parsed['serialNumber'] ?? 0))),
            'not_before'          => CarbonImmutable::createFromTimestamp($parsed['validFrom_time_t']),
            'not_after'           => CarbonImmutable::createFromTimestamp($parsed['validTo_time_t']),
            'key_bits'            => (int) ($keyDetails['bits'] ?? 0),
            'key_algorithm'       => $this->resolveKeyAlgorithm((int) ($keyDetails['type'] ?? -1)),
            'fingerprint'         => $fingerprint,
            'signature_algorithm' => $parsed['signatureTypeSN'] ?? $parsed['signatureTypeLN'] ?? 'unknown',
        ];
    }

    /**
     * Check whether a certificate fingerprint matches a known Debian weak key.
     *
     * CVE-2008-0166: Debian OpenSSL generated predictable RSA/DSA keys due to
     * a broken random-number seeder. The blacklist is a SHA-1 fingerprint list.
     *
     * In production, load the blacklist from storage/app/debian-blacklist.txt
     * (one lower-case SHA-1 hex fingerprint per line, no colons).
     */
    public function isDebianWeak(string $fingerprint): bool
    {
        // Normalise: strip colons, lowercase
        $normalised = strtolower(str_replace(':', '', $fingerprint));

        $blacklistPath = storage_path('app/debian-blacklist.txt');

        if (! file_exists($blacklistPath)) {
            // No blacklist file — cannot determine weakness; assume safe
            return false;
        }

        $handle = fopen($blacklistPath, 'r');
        if ($handle === false) {
            return false;
        }

        while (($line = fgets($handle)) !== false) {
            if (trim($line) === $normalised) {
                fclose($handle);

                return true;
            }
        }

        fclose($handle);

        return false;
    }

    public function isExpired(EntityCertificate $cert): bool
    {
        return $cert->not_after->isPast();
    }

    /**
     * Returns 0 if already expired.
     */
    public function daysUntilExpiry(EntityCertificate $cert): int
    {
        if ($this->isExpired($cert)) {
            return 0;
        }

        return (int) CarbonImmutable::now()->diffInDays($cert->not_after, absolute: true);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /**
     * Normalise any PEM-like input to a canonical PEM string OpenSSL accepts.
     * Handles: wrong line lengths, CRLF endings, raw base64 without headers,
     * extra whitespace introduced by textarea round-trips or Livewire serialization.
     */
    public function normalizePem(string $input): string
    {
        $input = trim($input);

        // Strip existing PEM headers and all whitespace to get raw base64
        $base64 = preg_replace('/-----[^-]+-----/', '', $input);
        $base64 = preg_replace('/\s+/', '', $base64);

        if ($base64 === '') {
            return $input; // let openssl_x509_read produce its own error
        }

        return "-----BEGIN CERTIFICATE-----\n"
             . chunk_split($base64, 64, "\n")
             . "-----END CERTIFICATE-----\n";
    }

    private function buildDnString(array $dn): string
    {
        $parts = [];

        foreach (['CN', 'O', 'OU', 'L', 'ST', 'C', 'emailAddress'] as $key) {
            if (isset($dn[$key]) && $dn[$key] !== '') {
                $parts[] = "{$key}={$dn[$key]}";
            }
        }

        return implode(', ', $parts);
    }

    private function resolveKeyAlgorithm(int $type): string
    {
        return match ($type) {
            OPENSSL_KEYTYPE_RSA => 'RSA',
            OPENSSL_KEYTYPE_DSA => 'DSA',
            OPENSSL_KEYTYPE_DH  => 'DH',
            OPENSSL_KEYTYPE_EC  => 'EC',
            default             => 'unknown',
        };
    }
}
