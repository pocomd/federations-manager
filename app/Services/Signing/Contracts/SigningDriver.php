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
     * Must validate the signed output via SignedMetadataValidator before returning.
     */
    public function sign(string $xml, Federation $federation): string;

    /**
     * Return true if a private key credential is stored for the federation.
     */
    public function hasKey(Federation $federation): bool;

    /**
     * Return true if a signing certificate is stored for the federation.
     */
    public function hasCert(Federation $federation): bool;

    /**
     * Store a PEM-encoded private key for the federation.
     * Must audit-log the action.
     *
     * @throws \RuntimeException on storage failure.
     */
    public function storeKey(Federation $federation, string $pem): void;

    /**
     * Store a PEM-encoded X.509 certificate for the federation.
     * FileSigningDriver writes to disk (chmod 600).
     * SoftHsmSigningDriver imports the certificate into the federation's token
     * using pkcs11-tool (--write-object --type cert). The cert is NOT written to disk.
     * Must audit-log the action.
     *
     * @throws \RuntimeException on storage failure.
     */
    public function storeCert(Federation $federation, string $pem): void;

    /**
     * Delete all credentials for the federation (key + cert).
     * FileSigningDriver removes both PEM files from disk.
     * SoftHsmSigningDriver deletes the entire per-federation token and soft-deletes the DB row.
     * Must audit-log the action.
     */
    public function deleteAll(Federation $federation): void;

    /**
     * Return display information about the stored private key, or null if none.
     *
     * Expected keys: type (string 'key'), key_type, bits, preview, created_at.
     * FileSigningDriver: reads from disk via openssl_pkey_get_details().
     * SoftHsmSigningDriver: reads key attributes from token via pkcs11-tool.
     *
     * @return array<string, mixed>|null
     */
    public function keyInfo(Federation $federation): ?array;

    /**
     * Return display information about the stored certificate, or null if none.
     *
     * Expected keys: type (string 'cert'), subject, issuer, serial, valid_from,
     * valid_to, validity_class, validity_label, pem, created_at.
     * FileSigningDriver: reads cert file via openssl_x509_parse().
     * SoftHsmSigningDriver: reads certificate object from token via pkcs11-tool.
     *
     * @return array<string, mixed>|null
     */
    public function certInfo(Federation $federation): ?array;

    /**
     * Run a self-test and return a HealthCheckResult.
     *
     * FileSigningDriver: verifies xmlsectool binary + performs a live signing round-trip.
     * SoftHsmSigningDriver: verifies softhsm2-util + pkcs11-tool binaries
     *   + PKCS11_LIBRARY file exists + SOFTHSM2_CONF is readable.
     */
    public function healthCheck(): HealthCheckResult;

    /**
     * Return the Blade partial name for the key management UI.
     * Convention: 'signing-keys-file', 'signing-keys-softhsm'.
     * Used as: @include('livewire.partials.' . $driver->viewName())
     */
    public function viewName(): string;
}
