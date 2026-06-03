<?php

declare(strict_types=1);

use App\Exceptions\XmlSigningException;
use App\Models\Entity;
use App\Models\EntityEndpoint;
use App\Services\Entity\CertificateService;
use App\Services\Entity\EntityMetadataService;
use App\Services\Metadata\RuleEngine;
use App\Services\Metadata\RuleRegistry;
use App\Services\Metadata\XmlsectoolSigner;

// ── Test helpers ───────────────────────────────────────────────────────────────

/**
 * Build a minimal valid IdP entity in memory (no DB required).
 * Uses setRelation() so loadMissing() inside renderXml() skips all DB queries.
 */
function buildTestIdp(): Entity
{
    $e = new Entity();
    $e->entity_id              = 'https://idp.test.example.org/saml2/idp';
    $e->type                   = 'idp';
    $e->scope                  = 'testuniversity.ie';
    $e->registration_authority = 'https://www.heanet.ie/';
    $e->nameid_formats         = [
        'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
        'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
    ];

    $e->setRelation('uiInfo', collect([
        makeUiInfo('display_name',    'en', 'Test Identity Provider'),
        makeUiInfo('description',     'en', 'A test IdP for XML generation'),
        makeUiInfo('org_name',        'en', 'Test University'),
        makeUiInfo('org_display_name','en', 'Test University'),
        makeUiInfo('org_url',         'en', 'https://www.testuniversity.ie'),
    ]));

    $e->setRelation('contacts', collect([
        makeContact('technical', 'tech@testuniversity.ie',      'Technical', 'Contact'),
        makeContact('security',  'security@testuniversity.ie',  'Security',  'Contact'),
    ]));

    $e->setRelation('endpoints', collect([
        makeEndpoint('sso', EntityEndpoint::BINDING_HTTP_REDIRECT, 'https://idp.test.example.org/saml2/sso/redirect'),
        makeEndpoint('sso', EntityEndpoint::BINDING_HTTP_POST,     'https://idp.test.example.org/saml2/sso/post'),
        makeEndpoint('slo', EntityEndpoint::BINDING_HTTP_REDIRECT, 'https://idp.test.example.org/saml2/slo/redirect'),
    ]));

    $e->setRelation('attributes',    collect([]));
    $e->setRelation('certificates',  collect([]));

    return $e;
}

/**
 * Build a minimal valid SP entity in memory (no DB required).
 * Uses setRelation() so loadMissing() inside renderXml() skips all DB queries.
 */
function buildTestSp(): Entity
{
    $e = new Entity();
    $e->entity_id                     = 'https://sp.test.example.org/saml2/metadata';
    $e->type                          = 'sp';
    $e->registration_authority        = 'https://www.heanet.ie/';
    $e->nameid_formats                = [];
    $e->sp_want_assertions_signed     = true;
    $e->sp_want_authn_requests_signed = false;
    $e->requested_attributes          = ['urn:oid:1.3.6.1.4.1.5923.1.1.1.7'];

    $e->setRelation('uiInfo', collect([
        makeUiInfo('display_name',    'en', 'Test Service Provider'),
        makeUiInfo('description',     'en', 'A test SP for XML generation'),
        makeUiInfo('privacy_url',     'en', 'https://sp.test.example.org/privacy'),
        makeUiInfo('org_name',        'en', 'Test University'),
        makeUiInfo('org_display_name','en', 'Test University'),
        makeUiInfo('org_url',         'en', 'https://www.testuniversity.ie'),
    ]));

    $e->setRelation('contacts', collect([
        makeContact('technical', 'tech@testuniversity.ie', 'Technical', 'Contact'),
    ]));

    $e->setRelation('endpoints', collect([
        makeEndpoint('acs', EntityEndpoint::BINDING_HTTP_POST,     'https://sp.test.example.org/saml2/acs', 1, true),
        makeEndpoint('slo', EntityEndpoint::BINDING_HTTP_POST,     'https://sp.test.example.org/saml2/slo'),
    ]));

    $e->setRelation('attributes',   collect([]));
    $e->setRelation('certificates', collect([]));

    return $e;
}

/**
 * Assert that an XML string is well-formed and return the DOMDocument.
 */
function assertWellFormedXml(string $xml): DOMDocument
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $loaded = $dom->loadXML($xml);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors(false);

    expect($loaded)->toBeTrue('DOMDocument::loadXML returned false — XML is not parseable.');
    expect($errors)->toBeEmpty('XML contains parse errors: ' . collect($errors)->pluck('message')->implode('; '));

    return $dom;
}

// ── XmlsectoolSigner tests ────────────────────────────────────────────────────

describe('XmlsectoolSigner::sign()', function () {
    it('throws XmlSigningException when xmlsectool_path is not configured', function () {
        config(['federation.xmlsectool_path' => null]);

        (new XmlsectoolSigner())->sign('<xml/>');
    })->throws(XmlSigningException::class);

    it('throws XmlSigningException when signing_key is not configured', function () {
        config([
            'federation.xmlsectool_path' => '/usr/local/bin/xmlsectool',
            'federation.signing_key'     => null,
            'federation.signing_cert'    => '/etc/signing.crt',
        ]);

        (new XmlsectoolSigner())->sign('<xml/>');
    })->throws(XmlSigningException::class);

    it('throws XmlSigningException when signing_cert is not configured', function () {
        config([
            'federation.xmlsectool_path' => '/usr/local/bin/xmlsectool',
            'federation.signing_key'     => '/etc/signing.key',
            'federation.signing_cert'    => null,
        ]);

        (new XmlsectoolSigner())->sign('<xml/>');
    })->throws(XmlSigningException::class);

    it('throws XmlSigningException when the binary exits non-zero', function () {
        // Use PHP_BINARY as the "tool" — it exists but will fail the xmlsectool arguments.
        config([
            'federation.xmlsectool_path' => PHP_BINARY,
            'federation.signing_key'     => __FILE__,
            'federation.signing_cert'    => __FILE__,
        ]);

        (new XmlsectoolSigner())->sign('<?xml version="1.0"?><root/>');
    })->throws(XmlSigningException::class);
});

// ── renderXml() well-formedness tests ─────────────────────────────────────────

describe('EntityMetadataService::renderXml() — XML well-formedness', function () {
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

    it('produces well-formed XML for an IdP entity', function () {
        $xml = $this->service->renderXml(buildTestIdp());

        assertWellFormedXml($xml);
    });

    it('produces well-formed XML for an SP entity', function () {
        $xml = $this->service->renderXml(buildTestSp());

        assertWellFormedXml($xml);
    });

    it('IdP XML contains correct root element with all namespace prefixes', function () {
        $xml = $this->service->renderXml(buildTestIdp());
        $dom = assertWellFormedXml($xml);

        $root = $dom->documentElement;
        expect($root->localName)->toBe('EntityDescriptor');
        expect($root->getAttribute('entityID'))->toBe('https://idp.test.example.org/saml2/idp');

        // All SAML namespaces declared on root
        $expected = [
            'xmlns:md'     => 'urn:oasis:names:tc:SAML:2.0:metadata',
            'xmlns:mdui'   => 'urn:oasis:names:tc:SAML:metadata:ui',
            'xmlns:mdrpi'  => 'urn:oasis:names:tc:SAML:metadata:rpi',
            'xmlns:mdattr' => 'urn:oasis:names:tc:SAML:metadata:attribute',
            'xmlns:saml'   => 'urn:oasis:names:tc:SAML:2.0:assertion',
            'xmlns:shibmd' => 'urn:mace:shibboleth:metadata:1.0',
            'xmlns:ds'     => 'http://www.w3.org/2000/09/xmldsig#',
        ];

        foreach ($expected as $attr => $value) {
            expect($root->getAttribute($attr))->toBe($value, "Expected {$attr}=\"{$value}\"");
        }
    });

    it('IdP XML contains IDPSSODescriptor with SSO services and Scope', function () {
        $dom  = assertWellFormedXml($this->service->renderXml(buildTestIdp()));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('md',     'urn:oasis:names:tc:SAML:2.0:metadata');
        $xpath->registerNamespace('shibmd', 'urn:mace:shibboleth:metadata:1.0');

        $descriptor = $xpath->query('//md:IDPSSODescriptor');
        expect($descriptor->length)->toBe(1);

        $sso = $xpath->query('//md:SingleSignOnService');
        expect($sso->length)->toBeGreaterThanOrEqual(1);

        $scope = $xpath->query('//shibmd:Scope');
        expect($scope->length)->toBe(1);
        expect($scope->item(0)->textContent)->toBe('testuniversity.ie');
    });

    it('IdP XML contains NameIDFormat elements', function () {
        $dom  = assertWellFormedXml($this->service->renderXml(buildTestIdp()));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');

        $formats = $xpath->query('//md:NameIDFormat');
        expect($formats->length)->toBe(2);
    });

    it('SP XML contains SPSSODescriptor with ACS service', function () {
        $dom  = assertWellFormedXml($this->service->renderXml(buildTestSp()));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');

        $descriptor = $xpath->query('//md:SPSSODescriptor');
        expect($descriptor->length)->toBe(1);
        expect($descriptor->item(0)->getAttribute('WantAssertionsSigned'))->toBe('true');
        expect($descriptor->item(0)->getAttribute('AuthnRequestsSigned'))->toBe('false');

        $acs = $xpath->query('//md:AssertionConsumerService');
        expect($acs->length)->toBeGreaterThanOrEqual(1);
    });

    it('SP XML contains RequestedAttribute elements', function () {
        $dom  = assertWellFormedXml($this->service->renderXml(buildTestSp()));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');

        $attrs = $xpath->query('//md:RequestedAttribute');
        expect($attrs->length)->toBe(1);
        expect($attrs->item(0)->getAttribute('Name'))->toBe('urn:oid:1.3.6.1.4.1.5923.1.1.1.7');
    });

    it('XML contains md:Organization with all three child elements', function () {
        $dom  = assertWellFormedXml($this->service->renderXml(buildTestIdp()));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');

        expect($xpath->query('//md:OrganizationName')->length)->toBeGreaterThanOrEqual(1);
        expect($xpath->query('//md:OrganizationDisplayName')->length)->toBe(1);
        expect($xpath->query('//md:OrganizationURL')->length)->toBe(1);
    });

    it('XML contains md:ContactPerson elements for configured contacts', function () {
        $dom  = assertWellFormedXml($this->service->renderXml(buildTestIdp()));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');

        $contacts  = $xpath->query('//md:ContactPerson');
        $types     = [];
        foreach ($contacts as $c) {
            $types[] = $c->getAttribute('contactType');
        }

        expect($types)->toContain('technical');
        // 'security' is mapped to 'other' in XML — SAML2 schema only allows the five base contactType values.
        expect($types)->toContain('other');
    });

    it('XML contains mdrpi:RegistrationInfo when registration_authority is set', function () {
        $dom  = assertWellFormedXml($this->service->renderXml(buildTestIdp()));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('mdrpi', 'urn:oasis:names:tc:SAML:metadata:rpi');

        $ri = $xpath->query('//mdrpi:RegistrationInfo');
        expect($ri->length)->toBe(1);
        expect($ri->item(0)->getAttribute('registrationAuthority'))->toBe('https://www.heanet.ie/');
    });

    it('XML contains mdui:UIInfo with DisplayName and Description', function () {
        $dom  = assertWellFormedXml($this->service->renderXml(buildTestIdp()));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('mdui', 'urn:oasis:names:tc:SAML:metadata:ui');

        $dn   = $xpath->query('//mdui:DisplayName');
        $desc = $xpath->query('//mdui:Description');
        expect($dn->length)->toBeGreaterThanOrEqual(1);
        expect($desc->length)->toBeGreaterThanOrEqual(1);
        expect($dn->item(0)->textContent)->toBe('Test Identity Provider');
    });
});
