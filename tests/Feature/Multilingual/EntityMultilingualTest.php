<?php

declare(strict_types=1);

use App\Livewire\EntityForm;
use App\Models\Entity;
use App\Models\EntityUiInfo;
use App\Models\User;
use App\Services\Entity\CertificateService;
use App\Services\Entity\EntityImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

// ── addLanguageVariant / removeLanguageVariant ────────────────────────────────

it('addLanguageVariant adds empty row to additionalLangs', function () {
    $this->actingAs($this->admin);

    Livewire::test(EntityForm::class)
        ->call('addLanguageVariant')
        ->assertSet('additionalLangs', fn ($val) => count($val) === 1
            && $val[0]['field'] === 'display_name'
            && $val[0]['value'] === '');
});

it('removeLanguageVariant removes correct index', function () {
    $this->actingAs($this->admin);

    Livewire::test(EntityForm::class)
        ->call('addLanguageVariant')
        ->call('addLanguageVariant')
        ->call('removeLanguageVariant', 0)
        ->assertSet('additionalLangs', fn ($val) => count($val) === 1);
});

// ── mount loads existing non-English rows ─────────────────────────────────────

it('EntityForm loads existing non-English ui_info on mount', function () {
    $this->actingAs($this->admin);

    $entity = Entity::factory()->idp()->create();

    EntityUiInfo::create([
        'entity_id' => $entity->id,
        'field'     => 'display_name',
        'lang'      => 'ro',
        'value'     => 'Universitatea de Test',
    ]);

    $component = Livewire::test(EntityForm::class, ['entity' => $entity]);

    $additionalLangs = $component->get('additionalLangs');
    expect($additionalLangs)->toHaveCount(1);
    expect($additionalLangs[0]['lang'])->toBe('ro');
    expect($additionalLangs[0]['field'])->toBe('display_name');
    expect($additionalLangs[0]['value'])->toBe('Universitatea de Test');
});

// ── EntityForm save tests ─────────────────────────────────────────────────────

it('EntityForm saves additional language variants', function () {
    $this->actingAs($this->admin);

    $entity = Entity::factory()->idp()->create();
    $pem    = generateSelfSignedPem();

    Livewire::test(EntityForm::class, ['entity' => $entity])
        ->set('certificates', [['use' => 'signing', 'pem' => $pem]])
        ->set('additionalLangs', [
            ['lang' => 'ro', 'field' => 'display_name', 'value' => 'Universitatea de Test'],
        ])
        ->call('save');

    expect(
        $entity->uiInfo()->where('lang', 'ro')->where('field', 'display_name')->exists()
    )->toBeTrue();
});

it('EntityForm deletes removed language variants on save', function () {
    $this->actingAs($this->admin);

    $entity = Entity::factory()->idp()->create();

    EntityUiInfo::create([
        'entity_id' => $entity->id,
        'field'     => 'description',
        'lang'      => 'ro',
        'value'     => 'Descriere în română',
    ]);

    $pem = generateSelfSignedPem();

    // Mount loads the ro row into additionalLangs; then clear it
    Livewire::test(EntityForm::class, ['entity' => $entity])
        ->set('certificates', [['use' => 'signing', 'pem' => $pem]])
        ->set('additionalLangs', [])   // operator removed the variant
        ->call('save');

    expect(
        $entity->uiInfo()->where('lang', 'ro')->where('field', 'description')->exists()
    )->toBeFalse();
});

// ── EntityImportService extracts all language variants ────────────────────────

it('EntityImportService fromXml extracts all language variants', function () {
    $pem  = base64_encode('fake-cert-data');
    $xml  = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <md:EntityDescriptor
        entityID="https://idp.multilang.test/metadata"
        xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
        xmlns:mdui="urn:oasis:names:tc:SAML:metadata:ui"
        xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
        <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
            <md:Extensions>
                <mdui:UIInfo>
                    <mdui:DisplayName xml:lang="en">Test University</mdui:DisplayName>
                    <mdui:DisplayName xml:lang="ro">Universitatea de Test</mdui:DisplayName>
                    <mdui:DisplayName xml:lang="de">Testuniversität</mdui:DisplayName>
                    <mdui:Description xml:lang="en">An identity provider</mdui:Description>
                </mdui:UIInfo>
            </md:Extensions>
            <md:SingleSignOnService
                Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
                Location="https://idp.multilang.test/sso"/>
            <md:KeyDescriptor use="signing">
                <ds:KeyInfo>
                    <ds:X509Data>
                        <ds:X509Certificate>{$pem}</ds:X509Certificate>
                    </ds:X509Data>
                </ds:KeyInfo>
            </md:KeyDescriptor>
        </md:IDPSSODescriptor>
        <md:Organization>
            <md:OrganizationName xml:lang="en">Test Org</md:OrganizationName>
            <md:OrganizationDisplayName xml:lang="en">Test Organisation</md:OrganizationDisplayName>
            <md:OrganizationURL xml:lang="en">https://www.example.org</md:OrganizationURL>
        </md:Organization>
    </md:EntityDescriptor>
    XML;

    $service = new EntityImportService(new CertificateService());
    $result  = $service->fromXml($xml);

    $uiInfo  = collect($result['ui_info']);

    $displayNames = $uiInfo->where('field', 'display_name')->pluck('lang')->sort()->values()->toArray();
    expect($displayNames)->toContain('en');
    expect($displayNames)->toContain('ro');
    expect($displayNames)->toContain('de');
});
