<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\EntityEndpoint;
use App\Services\Metadata\Rules\Structural\S01_EntityIdUri;
use App\Services\Metadata\Rules\Structural\S02_EntityIdUnique;
use App\Services\Metadata\Rules\Structural\S03_RoleDescriptor;
use App\Services\Metadata\Rules\Structural\S04_ProtocolSupport;
use App\Services\Metadata\Rules\Structural\S05_CertificatePresent;
use App\Services\Metadata\Rules\Structural\S06_IdpSsoEndpoint;
use App\Services\Metadata\Rules\Structural\S07_SpAcsEndpoint;
use App\Services\Metadata\Rules\Structural\S08_AcsIndexUnique;
use App\Services\Metadata\Rules\Structural\S09_BindingUrns;
use App\Services\Metadata\Rules\Structural\S10_HttpsEndpoints;

// ── Helpers ────────────────────────────────────────────────────────────────

function makeIdpEntity(string $entityId = 'https://idp.example.org/saml2'): Entity
{
    $entity = new Entity();
    $entity->entity_id = $entityId;
    $entity->type      = 'idp';
    $entity->setRelation('certificates', collect([]));
    $entity->setRelation('endpoints',    collect([]));
    $entity->setRelation('uiInfo',       collect([]));
    $entity->setRelation('contacts',     collect([]));
    $entity->setRelation('attributes',   collect([]));
    return $entity;
}

function makeSpEntity(string $entityId = 'https://sp.example.org/saml2'): Entity
{
    $entity            = makeIdpEntity($entityId);
    $entity->type      = 'sp';
    return $entity;
}

function makeEndpointStub(string $type, string $binding, string $location, ?int $index = null): object
{
    return new class($type, $binding, $location, $index) {
        public function __construct(
            public string $type,
            public string $binding,
            public string $location,
            public ?int   $index,
        ) {}
    };
}

// ── S01 ────────────────────────────────────────────────────────────────────

test('S01 passes for valid https entityID', function () {
    $entity = makeIdpEntity('https://idp.example.org/saml2');
    $result = (new S01_EntityIdUri())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('S01 passes for valid http entityID', function () {
    $entity = makeIdpEntity('http://idp.example.org/saml2');
    $result = (new S01_EntityIdUri())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('S01 fails for non-URI entityID', function () {
    $entity = makeIdpEntity('not a uri');
    $result = (new S01_EntityIdUri())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

test('S01 fails for empty entityID', function () {
    $entity = makeIdpEntity('');
    $result = (new S01_EntityIdUri())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

// ── S03 ────────────────────────────────────────────────────────────────────

test('S03 passes for IdP with SSO endpoint', function () {
    $entity = makeIdpEntity();
    $entity->setRelation('endpoints', collect([
        makeEndpointStub('sso', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect', 'https://idp.example.org/sso', null),
    ]));
    $result = (new S03_RoleDescriptor())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('S03 fails for IdP without SSO endpoint', function () {
    $entity = makeIdpEntity();
    $result = (new S03_RoleDescriptor())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

test('S03 passes for SP with ACS endpoint', function () {
    $entity = makeSpEntity();
    $entity->setRelation('endpoints', collect([
        makeEndpointStub('acs', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', 'https://sp.example.org/acs', 1),
    ]));
    $result = (new S03_RoleDescriptor())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

// ── S04 ────────────────────────────────────────────────────────────────────

test('S04 passes for idp type', function () {
    $result = (new S04_ProtocolSupport())->evaluate(makeIdpEntity());
    expect($result->status)->toBe('pass');
});

test('S04 passes for sp type', function () {
    $result = (new S04_ProtocolSupport())->evaluate(makeSpEntity());
    expect($result->status)->toBe('pass');
});

// ── S05 ────────────────────────────────────────────────────────────────────

test('S05 passes when certificate is present', function () {
    $entity = makeIdpEntity();
    $cert   = new \App\Models\EntityCertificate();
    $entity->setRelation('certificates', collect([$cert]));
    $result = (new S05_CertificatePresent())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('S05 fails when no certificate', function () {
    $result = (new S05_CertificatePresent())->evaluate(makeIdpEntity());
    expect($result->status)->toBe('fail');
});

// ── S06 ────────────────────────────────────────────────────────────────────

test('S06 passes for IdP with SSO endpoint', function () {
    $entity = makeIdpEntity();
    $entity->setRelation('endpoints', collect([
        makeEndpointStub('sso', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect', 'https://idp.example.org/sso', null),
    ]));
    $result = (new S06_IdpSsoEndpoint())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('S06 fails for IdP without SSO endpoint', function () {
    $result = (new S06_IdpSsoEndpoint())->evaluate(makeIdpEntity());
    expect($result->status)->toBe('fail');
});

test('S06 is not_applicable for SP', function () {
    expect((new S06_IdpSsoEndpoint())->appliesTo())->toBe(['idp']);
});

// ── S07 ────────────────────────────────────────────────────────────────────

test('S07 passes for SP with ACS endpoint', function () {
    $entity = makeSpEntity();
    $entity->setRelation('endpoints', collect([
        makeEndpointStub('acs', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', 'https://sp.example.org/acs', 1),
    ]));
    $result = (new S07_SpAcsEndpoint())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('S07 fails for SP without ACS endpoint', function () {
    $result = (new S07_SpAcsEndpoint())->evaluate(makeSpEntity());
    expect($result->status)->toBe('fail');
});

test('S07 is not_applicable for IdP', function () {
    expect((new S07_SpAcsEndpoint())->appliesTo())->toBe(['sp']);
});

// ── S08 ────────────────────────────────────────────────────────────────────

test('S08 passes when ACS indexes are unique', function () {
    $entity = makeSpEntity();
    $entity->setRelation('endpoints', collect([
        makeEndpointStub('acs', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', 'https://sp.example.org/acs1', 0),
        makeEndpointStub('acs', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', 'https://sp.example.org/acs2', 1),
    ]));
    $result = (new S08_AcsIndexUnique())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('S08 fails when ACS indexes are duplicate', function () {
    $entity = makeSpEntity();
    $entity->setRelation('endpoints', collect([
        makeEndpointStub('acs', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', 'https://sp.example.org/acs1', 1),
        makeEndpointStub('acs', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', 'https://sp.example.org/acs2', 1),
    ]));
    $result = (new S08_AcsIndexUnique())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

// ── S09 ────────────────────────────────────────────────────────────────────

test('S09 passes for valid SAML2 binding', function () {
    $entity = makeIdpEntity();
    $entity->setRelation('endpoints', collect([
        makeEndpointStub('sso', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect', 'https://idp.example.org/sso', null),
    ]));
    $result = (new S09_BindingUrns())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('S09 fails for invalid binding', function () {
    $entity = makeIdpEntity();
    $entity->setRelation('endpoints', collect([
        makeEndpointStub('sso', 'urn:oasis:names:tc:SAML:2.0:bindings:INVALID', 'https://idp.example.org/sso', null),
    ]));
    $result = (new S09_BindingUrns())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

// ── S10 ────────────────────────────────────────────────────────────────────

test('S10 passes when all endpoints use HTTPS', function () {
    $entity = makeIdpEntity();
    $entity->setRelation('endpoints', collect([
        makeEndpointStub('sso', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect', 'https://idp.example.org/sso', null),
    ]));
    $result = (new S10_HttpsEndpoints())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('S10 fails when an endpoint uses HTTP', function () {
    $entity = makeIdpEntity();
    $entity->setRelation('endpoints', collect([
        makeEndpointStub('sso', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect', 'http://idp.example.org/sso', null),
    ]));
    $result = (new S10_HttpsEndpoints())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

test('S10 passes when there are no endpoints', function () {
    $result = (new S10_HttpsEndpoints())->evaluate(makeIdpEntity());
    expect($result->status)->toBe('pass');
});
