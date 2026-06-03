<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\EntityCertificate;
use App\Models\EntityEndpoint;
use App\Services\Entity\CertificateService;
use App\Services\Entity\EntityMetadataService;
use App\Services\Entity\ValidationResult;
use App\Services\Metadata\RuleEngine;
use App\Services\Metadata\RuleRegistry;
use App\Services\Metadata\XmlsectoolSigner;
use Carbon\CarbonImmutable;

// ── Test helpers ───────────────────────────────────────────────────────────────

/**
 * Build a fully-valid IdP Entity in memory (no DB).
 * All fields satisfy every structural and REFEDS check.
 * Uses setRelation() so loadMissing() skips all DB queries.
 */
function makeValidIdp(): Entity
{
    $entity = new Entity();
    $entity->entity_id              = 'https://idp.example.org/saml2/idp';
    $entity->type                   = 'idp';
    $entity->scope                  = 'testuniversity.ie';
    $entity->registration_authority = 'https://www.heanet.ie/';
    $entity->nameid_formats         = [];

    $entity->setRelation('uiInfo', collect([
        makeUiInfo('display_name',    'en', 'Test IdP'),
        makeUiInfo('description',     'en', 'A test identity provider'),
        makeUiInfo('org_name',        'en', 'Test University'),
        makeUiInfo('org_display_name','en', 'Test University'),
        makeUiInfo('org_url',         'en', 'https://www.testuniversity.ie'),
    ]));

    $entity->setRelation('contacts', collect([
        makeContact('technical', 'tech@testuniversity.ie'),
        makeContact('security',  'security@testuniversity.ie'),
    ]));

    $entity->setRelation('endpoints', collect([
        makeEndpoint('sso', EntityEndpoint::BINDING_HTTP_REDIRECT, 'https://idp.example.org/saml2/sso/redirect'),
    ]));

    $entity->setRelation('attributes', collect([]));

    return $entity;
}

/**
 * Build a signing EntityCertificate stub in memory (no DB).
 */
function makeSigningCert(bool $expired = false, int $keyBits = 2048): EntityCertificate
{
    $cert                      = new EntityCertificate();
    $cert->use                 = 'signing';
    $cert->key_bits            = $keyBits;
    $cert->fingerprint         = str_repeat('ab', 32); // 64 hex chars (no colons, lowercase)
    $cert->signature_algorithm = 'sha256WithRSAEncryption';
    $cert->pem                 = "-----BEGIN CERTIFICATE-----\n"
                                 . base64_encode(random_bytes(100)) . "\n"
                                 . "-----END CERTIFICATE-----\n";
    $cert->not_after           = $expired
        ? CarbonImmutable::now()->subDay()
        : CarbonImmutable::now()->addYear();

    return $cert;
}

// ── Suite setup ────────────────────────────────────────────────────────────────

beforeEach(function () {
    $registry = new RuleRegistry(
        rulesPath:      app_path('Services/Metadata/Rules'),
        rulesNamespace: 'App\\Services\\Metadata\\Rules',
    );
    $this->service = new EntityMetadataService(
        new CertificateService(),
        new XmlsectoolSigner(),
        RuleEngine::withAllActive($registry),
    );
});

// ── validate() ────────────────────────────────────────────────────────────────

describe('EntityMetadataService::validate()', function () {
    it('returns a ValidationResult instance', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        expect($this->service->validate($entity))->toBeInstanceOf(ValidationResult::class);
    });

    // S1 — entityID is a valid URI
    it('passes S1 for a valid https entityID', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $s1 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'S01');

        expect($s1['status'])->toBe('pass');
    });

    it('fails S1 when entityID is not a valid URI', function () {
        $entity            = makeValidIdp();
        $entity->entity_id = 'not-a-valid-uri';
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $s1 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'S01');

        expect($s1['status'])->toBe('fail');
    });

    // S3 — required endpoint present
    it('fails S3 when IdP has no SSO endpoint', function () {
        $entity = makeValidIdp();
        $entity->setRelation('endpoints', collect([]));
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $s3 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'S03');

        expect($s3['status'])->toBe('fail');
    });

    // C1 — at least one signing cert
    it('fails C1 when entity has no signing certificates', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([]));

        $c1 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'C01');

        expect($c1['status'])->toBe('fail');
    });

    // C2 — key size ≥ 2048 bits
    it('fails C2 when signing cert key_bits is less than 2048', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([makeSigningCert(keyBits: 1024)]));

        $c2 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'C02');

        expect($c2['status'])->toBe('fail');
    });

    // C3 — no expired certs
    it('fails C3 when the signing cert is expired', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([makeSigningCert(expired: true)]));

        $c3 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'C03');

        expect($c3['status'])->toBe('fail');
    });

    // R1 — DisplayName present
    it('fails R1 when display_name uiInfo is missing', function () {
        $entity = makeValidIdp();
        // Remove display_name from uiInfo
        $entity->setRelation('uiInfo',
            $entity->uiInfo->reject(fn($u) => $u->field === 'display_name')->values()
        );
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $r1 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'R01');

        expect($r1['status'])->toBe('warning');
    });

    // S4 — HTTPS on all endpoint URLs
    it('validate check S4 is always present in checks array', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $codes = collect($this->service->validate($entity)->checks())->pluck('id');

        expect($codes)->toContain('S04');
    });

    // S9 — all endpoint binding URIs must be valid SAML2 identifiers
    it('validate S9 fails when IdP has an invalid binding URN', function () {
        $entity = makeValidIdp();
        $entity->setRelation('endpoints', collect([
            makeEndpoint('sso', 'urn:custom:bindings:NonStandard', 'https://idp.example.org/saml2/sso'),
        ]));
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $s9 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'S09');

        expect($s9['status'])->toBe('fail');
    });

    // S8 — ACS index uniqueness (SP only; not_applicable for IdP)
    it('validate S8 is not_applicable for IdP entities', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $s8 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'S08');

        expect($s8['status'])->toBe('not_applicable');
    });

    // R5 — English org_url must be present
    it('validate R5 warns when org_url is absent', function () {
        $entity = makeValidIdp();
        $entity->setRelation('uiInfo', collect([
            makeUiInfo('display_name',    'en', 'Test IdP'),
            makeUiInfo('description',     'en', 'A test IdP'),
            makeUiInfo('org_name',        'en', 'Test University'),
            makeUiInfo('org_display_name','en', 'Test University'),
        ]));
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $r5 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'R05');

        expect($r5['status'])->toBe('warning');
    });

    // R10 — shibmd:Scope (IdP only)
    it('fails R10 for an IdP without a scope', function () {
        $entity        = makeValidIdp();
        $entity->scope = null;
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $r10 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'R10');

        expect($r10['status'])->toBe('warning');
    });

    it('R10 is not_applicable for SP entities', function () {
        $entity = makeValidIdp();
        $entity->type                          = 'sp';
        $entity->scope                         = null;
        $entity->sp_want_assertions_signed     = false;
        $entity->sp_want_authn_requests_signed = false;
        // Replace SSO endpoints with ACS endpoint for S3/S6 to pass
        $entity->setRelation('endpoints', collect([
            makeEndpoint('acs', EntityEndpoint::BINDING_HTTP_POST, 'https://sp.example.org/saml2/acs', 1, true),
        ]));
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $r10 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'R10');

        expect($r10['status'])->toBe('not_applicable');
    });

    // R7 — SIRTFI requires a security contact
    it('fails R7 when SIRTFI is asserted but no security contact is present', function () {
        $entity = makeValidIdp();
        // Remove the security contact so R7 fires
        $entity->setRelation('contacts', collect([
            makeContact('technical', 'tech@testuniversity.ie'),
        ]));
        $entity->setRelation('attributes', collect([
            makeAttribute(\App\Models\EntityAttribute::ATTR_ENTITY_CATEGORY, 'https://refeds.org/sirtfi'),
        ]));
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $r7 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'R07');

        expect($r7['status'])->toBe('fail');
    });

    it('passes R7 when SIRTFI is asserted and a security contact is present', function () {
        $entity = makeValidIdp(); // already has security contact
        $entity->setRelation('attributes', collect([
            makeAttribute(\App\Models\EntityAttribute::ATTR_ENTITY_CATEGORY, 'https://refeds.org/sirtfi'),
        ]));
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $r7 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'R07');

        expect($r7['status'])->toBe('pass');
    });

    // R8 — CoCo v2 requires mdui:PrivacyStatementURL
    it('fails R8 when CoCo v2 is asserted but privacy_url uiInfo is absent', function () {
        $entity = makeValidIdp();
        // Remove any privacy_url from uiInfo (makeValidIdp does not add one)
        $entity->setRelation('attributes', collect([
            makeAttribute(\App\Models\EntityAttribute::ATTR_ENTITY_CATEGORY, 'https://refeds.org/category/code-of-conduct/v2'),
        ]));
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $r8 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'R08');

        expect($r8['status'])->toBe('fail');
    });

    it('passes R8 when CoCo v2 is asserted and privacy_url is present', function () {
        $entity = makeValidIdp();
        $entity->setRelation('uiInfo', $entity->uiInfo->merge([
            makeUiInfo('privacy_url', 'en', 'https://www.testuniversity.ie/privacy'),
        ]));
        $entity->setRelation('attributes', collect([
            makeAttribute(\App\Models\EntityAttribute::ATTR_ENTITY_CATEGORY, 'https://refeds.org/category/code-of-conduct/v2'),
        ]));
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $r8 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'R08');

        expect($r8['status'])->toBe('pass');
    });

    // R9 — R&S entity must use the canonical REFEDS URI
    it('warns R9 when a non-canonical R&S URI is used', function () {
        $entity = makeValidIdp();
        // Use a non-canonical variant that contains 'research-and-scholarship' but isn't the canonical URI
        $entity->setRelation('attributes', collect([
            makeAttribute(\App\Models\EntityAttribute::ATTR_ENTITY_CATEGORY, 'https://example.org/category/research-and-scholarship'),
        ]));
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $r9 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'R09');

        expect($r9['status'])->toBe('fail');
    });

    it('passes R9 when the canonical R&S URI is used', function () {
        $entity = makeValidIdp();
        $entity->setRelation('attributes', collect([
            makeAttribute(\App\Models\EntityAttribute::ATTR_ENTITY_CATEGORY, 'http://refeds.org/category/research-and-scholarship'),
        ]));
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $r9 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'R09');

        expect($r9['status'])->toBe('pass');
    });

    // R12 — registrationAuthority
    it('fails R12 when registration_authority is missing', function () {
        $entity                         = makeValidIdp();
        $entity->registration_authority = null;
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $r12 = collect($this->service->validate($entity)->checks())->firstWhere('id', 'R12');

        expect($r12['status'])->toBe('fail');
    });

    // passed() / errors() helpers
    it('reports passed() true when no checks fail', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        expect($this->service->validate($entity)->passed())->toBeTrue();
    });

    it('reports passed() false when at least one check fails', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([])); // C2/C3/C4/C5 produce fail status

        expect($this->service->validate($entity)->passed())->toBeFalse();
    });
});

// ── renderXml() ───────────────────────────────────────────────────────────────

describe('EntityMetadataService::renderXml()', function () {
    it('produces a well-formed XML string starting with the XML declaration', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([]));

        expect($this->service->renderXml($entity))->toStartWith('<?xml');
    });

    it('includes the entityID attribute on the root EntityDescriptor', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([]));

        expect($this->service->renderXml($entity))
            ->toContain('entityID="https://idp.example.org/saml2/idp"');
    });

    it('declares all required SAML namespace prefixes on the root element', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([]));

        $xml = $this->service->renderXml($entity);

        expect($xml)->toContain('xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"');
        expect($xml)->toContain('xmlns:mdui="urn:oasis:names:tc:SAML:metadata:ui"');
        expect($xml)->toContain('xmlns:mdrpi="urn:oasis:names:tc:SAML:metadata:rpi"');
        expect($xml)->toContain('xmlns:ds="http://www.w3.org/2000/09/xmldsig#"');
        expect($xml)->toContain('xmlns:shibmd="urn:mace:shibboleth:metadata:1.0"');
    });

    it('renders IDPSSODescriptor for an IdP entity', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([]));

        expect($this->service->renderXml($entity))->toContain('IDPSSODescriptor');
    });

    it('renders SPSSODescriptor (not IDPSSODescriptor) for an SP entity', function () {
        $entity       = makeValidIdp();
        $entity->type = 'sp';
        $entity->setRelation('endpoints', collect([
            makeEndpoint('acs', EntityEndpoint::BINDING_HTTP_POST, 'https://sp.example.org/saml2/acs', 1, true),
        ]));
        $entity->setRelation('certificates', collect([]));

        $xml = $this->service->renderXml($entity);

        expect($xml)->toContain('SPSSODescriptor');
        expect($xml)->not->toContain('IDPSSODescriptor');
    });

    it('includes shibmd:Scope when scope is set on an IdP', function () {
        $entity = makeValidIdp(); // scope = 'testuniversity.ie'
        $entity->setRelation('certificates', collect([]));

        $xml = $this->service->renderXml($entity);

        expect($xml)->toContain('shibmd:Scope');
        expect($xml)->toContain('testuniversity.ie');
    });

    it('includes mdrpi:RegistrationInfo when registration_authority is set', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([]));

        expect($this->service->renderXml($entity))
            ->toContain('mdrpi:RegistrationInfo')
            ->toContain('https://www.heanet.ie/');
    });

    it('includes ds:KeyDescriptor elements for each certificate', function () {
        $entity = makeValidIdp();
        $entity->setRelation('certificates', collect([makeSigningCert()]));

        $xml = $this->service->renderXml($entity);

        expect($xml)->toContain('KeyDescriptor');
        expect($xml)->toContain('use="signing"');
    });
});

// ── X1 schema checks ──────────────────────────────────────────────────────────

describe('EntityMetadataService::validate() — X1 XSD check', function () {

    beforeEach(function () {
        $registry = new RuleRegistry(
            rulesPath:      app_path('Services/Metadata/Rules'),
            rulesNamespace: 'App\\Services\\Metadata\\Rules',
        );
        $this->service = new EntityMetadataService(
            new CertificateService(),
            new XmlsectoolSigner(),
            RuleEngine::withAllActive($registry),
        );
    });

    it('validate X1 skips gracefully when schema file missing', function () {
        $schemaPath = storage_path('app/schemas/saml-schema-metadata-2.0.xsd');
        $existed    = file_exists($schemaPath);

        if ($existed) {
            rename($schemaPath, $schemaPath . '.bak');
        }

        try {
            $entity = makeValidIdp();

            $result = $this->service->validate($entity);
            $checks = collect($result->checks());

            $x1 = $checks->firstWhere('id', 'X01');
            expect($x1)->not->toBeNull()
                ->and($x1['status'])->toBe('warning')
                ->and($x1['message'])->toContain('not available');
        } finally {
            if ($existed) {
                rename($schemaPath . '.bak', $schemaPath);
            }
        }
    });

    it('validate includes X1 check when schema file exists', function () {
        $schemaPath = storage_path('app/schemas/saml-schema-metadata-2.0.xsd');

        if (!file_exists($schemaPath)) {
            $this->markTestSkipped('Schema file not downloaded — run php artisan saml:download-schemas');
        }

        $entity = makeValidIdp();
        $result = $this->service->validate($entity);
        $checks = collect($result->checks());

        expect($checks->firstWhere('id', 'X01'))->not->toBeNull();
    });

    it('validate X1 passes for valid entity XML', function () {
        $schemaPath = storage_path('app/schemas/saml-schema-metadata-2.0.xsd');

        if (!file_exists($schemaPath)) {
            $this->markTestSkipped('Schema file not downloaded — run php artisan saml:download-schemas');
        }

        $entity = makeValidIdp();
        $result = $this->service->validate($entity);
        $checks = collect($result->checks());

        $x1 = $checks->firstWhere('id', 'X01');
        expect($x1['status'])->toBe('pass');
    });
});
