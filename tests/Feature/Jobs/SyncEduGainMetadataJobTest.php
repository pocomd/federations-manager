<?php

declare(strict_types=1);

use App\Jobs\SyncEduGainMetadataJob;
use App\Models\Entity;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->fixtureXml = file_get_contents(base_path('tests/Fixtures/edugain-sample.xml'));
});

it('handle creates entities from the fixture XML', function () {
    Http::fake([
        '*' => Http::response($this->fixtureXml, 200),
    ]);

    (new SyncEduGainMetadataJob())->handle();

    expect(Entity::where('source', 'edugain')->count())->toBe(3);
});

it('handle sets source=edugain on each imported entity', function () {
    Http::fake([
        '*' => Http::response($this->fixtureXml, 200),
    ]);

    (new SyncEduGainMetadataJob())->handle();

    $sources = Entity::where('source', 'edugain')->pluck('source')->unique()->values()->all();
    expect($sources)->toBe(['edugain']);
});

it('handle marks removed entity as inactive on second run', function () {
    // A minimal 2-entity feed — idp2 is not present
    $twoEntityXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<md:EntitiesDescriptor
    xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
    xmlns:mdrpi="urn:oasis:names:tc:SAML:metadata:rpi"
    xmlns:mdui="urn:oasis:names:tc:SAML:metadata:ui"
    xmlns:shibmd="urn:mace:shibboleth:metadata:1.0"
    Name="https://edugain.org">
    <md:EntityDescriptor entityID="https://idp1.edugain-test.example.com/shibboleth">
        <md:Extensions>
            <mdrpi:RegistrationInfo registrationAuthority="https://www.edugain.org/"/>
        </md:Extensions>
        <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
            <md:SingleSignOnService
                Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
                Location="https://idp1.edugain-test.example.com/idp/sso"/>
        </md:IDPSSODescriptor>
    </md:EntityDescriptor>
    <md:EntityDescriptor entityID="https://sp1.edugain-test.example.com/shibboleth">
        <md:Extensions>
            <mdrpi:RegistrationInfo registrationAuthority="https://www.edugain.org/"/>
        </md:Extensions>
        <md:SPSSODescriptor
            AuthnRequestsSigned="true"
            WantAssertionsSigned="true"
            protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
            <md:AssertionConsumerService
                Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
                Location="https://sp1.edugain-test.example.com/Shibboleth.sso/SAML2/POST"
                index="1"/>
        </md:SPSSODescriptor>
    </md:EntityDescriptor>
</md:EntitiesDescriptor>
XML;

    // Use a response sequence: first call returns 3 entities, second returns 2
    Http::fake([
        '*' => Http::sequence()
            ->push($this->fixtureXml, 200)
            ->push($twoEntityXml, 200),
    ]);

    // First run — 3 entities created
    (new SyncEduGainMetadataJob())->handle();
    expect(Entity::where('source', 'edugain')->count())->toBe(3);

    // Second run — idp2 no longer in feed → should be marked inactive
    (new SyncEduGainMetadataJob())->handle();

    $removed = Entity::where('entity_id', 'https://idp2.edugain-test.example.com/shibboleth')->first();
    expect($removed)->not->toBeNull();
    expect($removed->status)->toBe('suspended');

    expect(Entity::where('source', 'edugain')->where('status', 'active')->count())->toBe(2);
});

it('handle logs error and exits gracefully on HTTP failure', function () {
    Http::fake([
        '*' => Http::response('', 503),
    ]);

    Log::shouldReceive('info')->once();
    Log::shouldReceive('error')->once()->with('SyncEduGainMetadataJob: fetch failed', \Mockery::any());

    (new SyncEduGainMetadataJob())->handle();

    expect(Entity::where('source', 'edugain')->count())->toBe(0);
});
