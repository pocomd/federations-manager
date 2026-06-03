<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Entity;
use App\Services\Entity\CertificateService;
use App\Services\Entity\EntityImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchedulerSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Import tests hit the DB — opt in to RefreshDatabase for this file
uses(RefreshDatabase::class);

// ── Shared XML fixtures ───────────────────────────────────────────────────────

/** Minimal but complete IdP EntityDescriptor XML */
function idpXml(string $certBase64 = 'AAAA'): string
{
    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <md:EntityDescriptor
        entityID="https://idp.example.org/saml/metadata"
        xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
        xmlns:mdui="urn:oasis:names:tc:SAML:metadata:ui"
        xmlns:mdrpi="urn:oasis:names:tc:SAML:metadata:rpi"
        xmlns:mdattr="urn:oasis:names:tc:SAML:metadata:attribute"
        xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
        xmlns:shibmd="urn:mace:shibboleth:metadata:1.0"
        xmlns:ds="http://www.w3.org/2000/09/xmldsig#">

        <md:Extensions>
            <mdrpi:RegistrationInfo registrationAuthority="https://registry.example.org"/>
            <mdattr:EntityAttributes>
                <saml:Attribute Name="http://macedir.org/entity-category">
                    <saml:AttributeValue>http://refeds.org/category/research-and-scholarship</saml:AttributeValue>
                </saml:Attribute>
                <saml:Attribute Name="urn:oasis:names:tc:SAML:attribute:assurance-certification">
                    <saml:AttributeValue>https://refeds.org/sirtfi</saml:AttributeValue>
                </saml:Attribute>
            </mdattr:EntityAttributes>
        </md:Extensions>

        <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
            <md:Extensions>
                <mdui:UIInfo>
                    <mdui:DisplayName xml:lang="en">Test University</mdui:DisplayName>
                    <mdui:DisplayName xml:lang="ro">Universitatea de Test</mdui:DisplayName>
                    <mdui:Description xml:lang="en">Identity Provider for Test University</mdui:Description>
                    <mdui:InformationURL xml:lang="en">https://idp.example.org/info</mdui:InformationURL>
                    <mdui:PrivacyStatementURL xml:lang="en">https://idp.example.org/privacy</mdui:PrivacyStatementURL>
                    <mdui:Logo width="80" height="60" xml:lang="en">https://idp.example.org/logo.png</mdui:Logo>
                </mdui:UIInfo>
                <shibmd:Scope regexp="false">example.org</shibmd:Scope>
            </md:Extensions>
            <md:KeyDescriptor use="signing">
                <ds:KeyInfo>
                    <ds:X509Data>
                        <ds:X509Certificate>{$certBase64}</ds:X509Certificate>
                    </ds:X509Data>
                </ds:KeyInfo>
            </md:KeyDescriptor>
            <md:NameIDFormat>urn:oasis:names:tc:SAML:2.0:nameid-format:persistent</md:NameIDFormat>
            <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
                Location="https://idp.example.org/saml/sso/post"/>
            <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
                Location="https://idp.example.org/saml/sso/redirect"/>
        </md:IDPSSODescriptor>

        <md:Organization>
            <md:OrganizationName xml:lang="en">Test University</md:OrganizationName>
            <md:OrganizationDisplayName xml:lang="en">Test University — Official</md:OrganizationDisplayName>
            <md:OrganizationURL xml:lang="en">https://www.example.org</md:OrganizationURL>
        </md:Organization>

        <md:ContactPerson contactType="technical">
            <md:GivenName>Tech</md:GivenName>
            <md:SurName>Admin</md:SurName>
            <md:EmailAddress>mailto:tech@example.org</md:EmailAddress>
        </md:ContactPerson>
        <md:ContactPerson contactType="security">
            <md:EmailAddress>security@example.org</md:EmailAddress>
        </md:ContactPerson>
    </md:EntityDescriptor>
    XML;
}

/** Minimal SP EntityDescriptor XML */
function spXml(): string
{
    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <md:EntityDescriptor
        entityID="https://sp.example.org/saml/metadata"
        xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
        xmlns:mdui="urn:oasis:names:tc:SAML:metadata:ui"
        xmlns:mdrpi="urn:oasis:names:tc:SAML:metadata:rpi"
        xmlns:ds="http://www.w3.org/2000/09/xmldsig#">

        <md:SPSSODescriptor
            AuthnRequestsSigned="true"
            WantAssertionsSigned="true"
            protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
            <md:AssertionConsumerService
                Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
                Location="https://sp.example.org/saml/acs"
                index="1"
                isDefault="true"/>
            <md:AssertionConsumerService
                Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
                Location="https://sp.example.org/saml/acs/redirect"
                index="2"/>
            <md:SingleLogoutService
                Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
                Location="https://sp.example.org/saml/slo"/>
            <md:AttributeConsumingService index="1">
                <md:ServiceName xml:lang="en">Test SP</md:ServiceName>
                <md:RequestedAttribute Name="urn:oid:1.3.6.1.4.1.5923.1.1.1.7" isRequired="true"/>
            </md:AttributeConsumingService>
        </md:SPSSODescriptor>

        <md:Organization>
            <md:OrganizationName xml:lang="en">Test SP Org</md:OrganizationName>
            <md:OrganizationDisplayName xml:lang="en">Test SP Organisation</md:OrganizationDisplayName>
            <md:OrganizationURL xml:lang="en">https://www.example.org</md:OrganizationURL>
        </md:Organization>

        <md:ContactPerson contactType="technical">
            <md:EmailAddress>mailto:sp-tech@example.org</md:EmailAddress>
        </md:ContactPerson>
    </md:EntityDescriptor>
    XML;
}

// ── Tests ─────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->service = new EntityImportService(new CertificateService());
});

// ── fromXml tests ──────────────────────────────────────────────────────────

it('parses valid IdP XML and extracts entity_id', function () {
    $result = $this->service->fromXml(idpXml());

    expect($result['entity_id'])->toBe('https://idp.example.org/saml/metadata');
    expect($result['type'])->toBe('idp');
    expect($result['registration_authority'])->toBe('https://registry.example.org');
    expect($result['scope'])->toBe('example.org');
});

it('parses valid SP XML and extracts ACS endpoints', function () {
    $result = $this->service->fromXml(spXml());

    expect($result['entity_id'])->toBe('https://sp.example.org/saml/metadata');
    expect($result['type'])->toBe('sp');

    $acs = collect($result['endpoints'])->where('type', 'acs');
    expect($acs)->toHaveCount(2);

    $first = $acs->first();
    expect($first['binding'])->toContain('HTTP-POST');
    expect($first['location'])->toBe('https://sp.example.org/saml/acs');
    expect($first['index'])->toBe(1);
    expect($first['is_default'])->toBeTrue();

    $slo = collect($result['endpoints'])->where('type', 'slo');
    expect($slo)->toHaveCount(1);
    expect($slo->first()['location'])->toBe('https://sp.example.org/saml/slo');
});

it('extracts all mdui fields from XML', function () {
    $result  = $this->service->fromXml(idpXml());
    $uiInfo  = collect($result['ui_info']);

    // DisplayName in two languages
    $names = $uiInfo->where('field', 'display_name');
    expect($names)->toHaveCount(2);
    expect($names->firstWhere('lang', 'en')['value'])->toBe('Test University');
    expect($names->firstWhere('lang', 'ro')['value'])->toBe('Universitatea de Test');

    // Description
    expect($uiInfo->firstWhere('field', 'description')['value'])
        ->toBe('Identity Provider for Test University');

    // Privacy URL
    expect($uiInfo->firstWhere('field', 'privacy_url')['value'])
        ->toBe('https://idp.example.org/privacy');

    // Logo with dimensions
    $logo = $uiInfo->firstWhere('field', 'logo_url');
    expect($logo['value'])->toBe('https://idp.example.org/logo.png');
    expect($logo['logo_width'])->toBe(80);
    expect($logo['logo_height'])->toBe(60);

    // Organization fields
    expect($uiInfo->firstWhere('field', 'org_name')['value'])->toBe('Test University');
    expect($uiInfo->firstWhere('field', 'org_url')['value'])->toBe('https://www.example.org');
});

it('extracts certificates from XML and wraps PEM headers', function () {
    $fakeBase64 = base64_encode('fake-cert-bytes-for-structure-test');
    $result     = $this->service->fromXml(idpXml($fakeBase64));

    expect($result['certificates'])->toHaveCount(1);

    $cert = $result['certificates'][0];
    expect($cert['use'])->toBe('signing');
    expect($cert['pem'])->toStartWith('-----BEGIN CERTIFICATE-----');
    expect($cert['pem'])->toEndWith("-----END CERTIFICATE-----\n");
    expect($cert['pem'])->toContain($fakeBase64);
});

it('extracts entity categories from EntityAttributes', function () {
    $result = $this->service->fromXml(idpXml());

    expect($result['entity_categories'])
        ->toContain('http://refeds.org/category/research-and-scholarship');

    expect($result['assurance_profiles'])
        ->toContain('https://refeds.org/sirtfi');
});

it('throws InvalidArgumentException for malformed XML', function () {
    $this->service->fromXml('this is not xml <<>>');
})->throws(InvalidArgumentException::class, 'XML is not well-formed');

it('throws InvalidArgumentException when entityID missing', function () {
    $xml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata">
        <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol"/>
    </md:EntityDescriptor>
    XML;

    $this->service->fromXml($xml);
})->throws(InvalidArgumentException::class, 'missing entityID');

// ── fromArray tests ────────────────────────────────────────────────────────

it('fromArray normalises flat structure to nested format', function () {
    $flat = [
        'entity_id' => 'https://sp.example.org',
        'type'      => 'sp',
        // optional keys omitted — should default to empty arrays
    ];

    $result = $this->service->fromArray($flat);

    expect($result['entity_id'])->toBe('https://sp.example.org');
    expect($result['type'])->toBe('sp');
    expect($result['ui_info'])->toBeArray()->toBeEmpty();
    expect($result['contacts'])->toBeArray()->toBeEmpty();
    expect($result['endpoints'])->toBeArray()->toBeEmpty();
    expect($result['certificates'])->toBeArray()->toBeEmpty();
    expect($result['entity_categories'])->toBeArray()->toBeEmpty();
    expect($result['assurance_profiles'])->toBeArray()->toBeEmpty();
    expect($result['nameid_formats'])->toBeArray()->toBeEmpty();
    expect($result['registration_authority'])->toBe('');
    expect($result['scope'])->toBeNull();
});

it('fromArray throws InvalidArgumentException when entity_id is missing', function () {
    $this->service->fromArray(['type' => 'idp']);
})->throws(InvalidArgumentException::class, 'entity_id is required');

it('fromArray throws InvalidArgumentException for invalid type', function () {
    $this->service->fromArray(['entity_id' => 'https://x.org', 'type' => 'unknown']);
})->throws(InvalidArgumentException::class, 'type is required');

// ── import() tests (require DB) ────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SchedulerSettingsSeeder::class);
});

it('import() creates entity with all child records in transaction', function () {
    $pem = generateSelfSignedPem();
    // Strip headers to simulate XML cert format, then re-wrap via fromXml
    $base64 = trim(
        preg_replace('/-----[A-Z ]+-----/', '', $pem)
    );
    $base64 = preg_replace('/\s+/', '', $base64);

    $parsed = $this->service->fromXml(idpXml($base64));

    $entity = $this->service->import($parsed);

    expect($entity)->toBeInstanceOf(Entity::class);
    expect($entity->entity_id)->toBe('https://idp.example.org/saml/metadata');
    expect($entity->type)->toBe('idp');
    expect($entity->status)->toBe('draft');

    // UI info persisted
    expect($entity->uiInfo()->count())->toBeGreaterThan(0);

    // Contacts
    expect($entity->contacts()->count())->toBe(2);
    expect($entity->contacts()->where('type', 'technical')->first()->email)->toBe('tech@example.org');
    expect($entity->contacts()->where('type', 'security')->first()->email)->toBe('security@example.org');

    // Endpoints
    expect($entity->endpoints()->where('type', 'sso')->count())->toBe(2);

    // Certificate
    expect($entity->certificates()->count())->toBe(1);
    expect($entity->certificates()->first()->use)->toBe('signing');

    // Entity categories
    expect(
        $entity->attributes()
            ->where('attribute_name', 'entity_category')
            ->where('attribute_value', 'http://refeds.org/category/research-and-scholarship')
            ->exists()
    )->toBeTrue();
});

it('import() logs to AuditLog with action entity_imported', function () {
    $data   = ['entity_id' => 'https://log-test.example.org', 'type' => 'sp'];
    $entity = $this->service->import($data);

    $log = AuditLog::where('entity_id', $entity->id)
        ->where('action', 'entity_imported')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->new_values['entity_id'])->toBe('https://log-test.example.org');
    expect($log->new_values['type'])->toBe('sp');
    expect($log->user_id)->toBeNull();
});
