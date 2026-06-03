<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\EntityCertificate;
use App\Services\Metadata\Rules\Certificate\C01_CertificateValid;
use App\Services\Metadata\Rules\Certificate\C02_KeySize;
use App\Services\Metadata\Rules\Certificate\C03_NotExpired;
use App\Services\Metadata\Rules\Certificate\C04_NotDebianWeak;
use App\Services\Metadata\Rules\Certificate\C05_SignatureAlgorithm;
use Carbon\Carbon;

function makeCertEntity(array $certs = []): Entity
{
    $entity = new Entity();
    $entity->entity_id = 'https://idp.example.org/saml2';
    $entity->type      = 'idp';
    $entity->setRelation('certificates', collect($certs));
    $entity->setRelation('endpoints',    collect([]));
    $entity->setRelation('uiInfo',       collect([]));
    $entity->setRelation('contacts',     collect([]));
    $entity->setRelation('attributes',   collect([]));
    return $entity;
}

function makeCert(array $attrs = []): EntityCertificate
{
    $cert = new EntityCertificate();
    $cert->use                 = $attrs['use']                 ?? 'signing';
    $cert->key_bits            = $attrs['key_bits']            ?? 2048;
    $cert->key_algorithm       = $attrs['key_algorithm']       ?? 'RSA';
    $cert->signature_algorithm = $attrs['signature_algorithm'] ?? 'sha256WithRSAEncryption';
    $cert->not_after           = $attrs['not_after']           ?? Carbon::now()->addYear();
    $cert->debian_weak         = $attrs['debian_weak']         ?? false;
    $cert->fingerprint         = $attrs['fingerprint']         ?? str_repeat('ab', 20);
    $cert->subject             = $attrs['subject']             ?? 'CN=test';
    return $cert;
}

// ── C01 ────────────────────────────────────────────────────────────────────

test('C01 passes when certificate is present', function () {
    $result = (new C01_CertificateValid())->evaluate(makeCertEntity([makeCert()]));
    expect($result->status)->toBe('pass');
});

test('C01 fails when no certificates', function () {
    $result = (new C01_CertificateValid())->evaluate(makeCertEntity());
    expect($result->status)->toBe('fail');
});

// ── C02 ────────────────────────────────────────────────────────────────────

test('C02 passes for RSA 2048-bit key', function () {
    $result = (new C02_KeySize())->evaluate(makeCertEntity([makeCert(['key_bits' => 2048])]));
    expect($result->status)->toBe('pass');
});

test('C02 passes for RSA 4096-bit key', function () {
    $result = (new C02_KeySize())->evaluate(makeCertEntity([makeCert(['key_bits' => 4096])]));
    expect($result->status)->toBe('pass');
});

test('C02 fails for RSA 1024-bit key', function () {
    $result = (new C02_KeySize())->evaluate(makeCertEntity([makeCert(['key_bits' => 1024])]));
    expect($result->status)->toBe('fail');
});

test('C02 passes for EC 256-bit key', function () {
    $result = (new C02_KeySize())->evaluate(makeCertEntity([makeCert(['key_algorithm' => 'EC', 'key_bits' => 256])]));
    expect($result->status)->toBe('pass');
});

test('C02 returns not_applicable when no certs', function () {
    $result = (new C02_KeySize())->evaluate(makeCertEntity());
    expect($result->status)->toBe('not_applicable');
});

// ── C03 ────────────────────────────────────────────────────────────────────

test('C03 passes for valid certificate', function () {
    $result = (new C03_NotExpired())->evaluate(makeCertEntity([makeCert(['not_after' => Carbon::now()->addYear()])]));
    expect($result->status)->toBe('pass');
});

test('C03 fails for expired certificate', function () {
    $result = (new C03_NotExpired())->evaluate(makeCertEntity([makeCert(['not_after' => Carbon::now()->subDay()])]));
    expect($result->status)->toBe('fail');
});

test('C03 warns for certificate expiring within 30 days', function () {
    $result = (new C03_NotExpired())->evaluate(makeCertEntity([makeCert(['not_after' => Carbon::now()->addDays(15)])]));
    expect($result->status)->toBe('warning');
});

test('C03 returns not_applicable when no certs', function () {
    $result = (new C03_NotExpired())->evaluate(makeCertEntity());
    expect($result->status)->toBe('not_applicable');
});

// ── C04 ────────────────────────────────────────────────────────────────────

test('C04 passes when certificate is not a Debian weak key', function () {
    $result = (new C04_NotDebianWeak())->evaluate(makeCertEntity([makeCert(['debian_weak' => false])]));
    expect($result->status)->toBe('pass');
});

test('C04 fails for Debian weak key', function () {
    $result = (new C04_NotDebianWeak())->evaluate(makeCertEntity([makeCert(['debian_weak' => true])]));
    expect($result->status)->toBe('fail');
});

// ── C05 ────────────────────────────────────────────────────────────────────

test('C05 passes for SHA-256 signature algorithm', function () {
    $result = (new C05_SignatureAlgorithm())->evaluate(
        makeCertEntity([makeCert(['signature_algorithm' => 'sha256WithRSAEncryption'])])
    );
    expect($result->status)->toBe('pass');
});

test('C05 warns for SHA-1 signature algorithm', function () {
    $result = (new C05_SignatureAlgorithm())->evaluate(
        makeCertEntity([makeCert(['signature_algorithm' => 'sha1WithRSAEncryption'])])
    );
    expect($result->status)->toBe('warning');
});

test('C05 warns for MD5 signature algorithm', function () {
    $result = (new C05_SignatureAlgorithm())->evaluate(
        makeCertEntity([makeCert(['signature_algorithm' => 'md5WithRSAEncryption'])])
    );
    expect($result->status)->toBe('warning');
});
