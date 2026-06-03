<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Services\Metadata\Rules\Refeds\R01_DisplayName;
use App\Services\Metadata\Rules\Refeds\R06_ContactPerson;
use App\Services\Metadata\Rules\Refeds\R07_SirtfiSecurityContact;
use App\Services\Metadata\Rules\Refeds\R08_CocoPrivacyUrl;
use App\Services\Metadata\Rules\Refeds\R09_RsCanonicalUri;
use App\Services\Metadata\Rules\Refeds\R10_ShibScope;
use App\Services\Metadata\Rules\Refeds\R11_ScopeMatchesDomain;
use App\Services\Metadata\Rules\Refeds\R12_RegistrationInfo;
use App\Services\Metadata\Rules\Refeds\R15_NameidFormatUnspecified;

function makeRefedsEntity(string $type = 'idp'): Entity
{
    $entity = new Entity();
    $entity->entity_id              = 'https://idp.example.org/saml2';
    $entity->type                   = $type;
    $entity->scope                  = null;
    $entity->registration_authority = '';
    $entity->nameid_formats         = [];
    $entity->setRelation('uiInfo',     collect([]));
    $entity->setRelation('contacts',   collect([]));
    $entity->setRelation('attributes', collect([]));
    $entity->setRelation('endpoints',  collect([]));
    $entity->setRelation('certificates', collect([]));
    return $entity;
}

function makeUiInfoStub(string $field, string $lang, string $value): object
{
    return new class($field, $lang, $value) {
        public function __construct(
            public string $field,
            public string $lang,
            public string $value,
        ) {}
    };
}

function makeContactStub(string $type, string $email = 'test@example.org'): object
{
    return new class($type, $email) {
        public function __construct(
            public string $type,
            public string $email,
        ) {}
    };
}

function makeAttributeStub(string $name, string $value): object
{
    return new class($name, $value) {
        public string $attribute_name;
        public string $attribute_value;
        public function __construct(string $name, string $value) {
            $this->attribute_name  = $name;
            $this->attribute_value = $value;
        }
    };
}

// ── R01 ────────────────────────────────────────────────────────────────────

test('R01 passes when English display_name present', function () {
    $entity = makeRefedsEntity();
    $entity->setRelation('uiInfo', collect([
        makeUiInfoStub('display_name', 'en', 'Test IdP'),
    ]));
    $result = (new R01_DisplayName())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('R01 warns when English display_name missing', function () {
    $result = (new R01_DisplayName())->evaluate(makeRefedsEntity());
    expect($result->status)->toBe('warning');
});

test('R01 warns when display_name only in non-English', function () {
    $entity = makeRefedsEntity();
    $entity->setRelation('uiInfo', collect([
        makeUiInfoStub('display_name', 'de', 'Test IdP'),
    ]));
    $result = (new R01_DisplayName())->evaluate($entity);
    expect($result->status)->toBe('warning');
});

// ── R06 ────────────────────────────────────────────────────────────────────

test('R06 passes when technical contact is present', function () {
    $entity = makeRefedsEntity();
    $entity->setRelation('contacts', collect([makeContactStub('technical')]));
    $result = (new R06_ContactPerson())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('R06 passes when support contact is present', function () {
    $entity = makeRefedsEntity();
    $entity->setRelation('contacts', collect([makeContactStub('support')]));
    $result = (new R06_ContactPerson())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('R06 warns when no technical or support contact', function () {
    $entity = makeRefedsEntity();
    $entity->setRelation('contacts', collect([makeContactStub('administrative')]));
    $result = (new R06_ContactPerson())->evaluate($entity);
    expect($result->status)->toBe('warning');
});

// ── R07 ────────────────────────────────────────────────────────────────────

test('R07 is not_applicable when SIRTFI not asserted', function () {
    $result = (new R07_SirtfiSecurityContact())->evaluate(makeRefedsEntity());
    expect($result->status)->toBe('not_applicable');
});

test('R07 fails when SIRTFI asserted but no security contact', function () {
    $entity = makeRefedsEntity();
    $entity->setRelation('attributes', collect([
        makeAttributeStub('entity_category', 'https://refeds.org/sirtfi'),
    ]));
    $result = (new R07_SirtfiSecurityContact())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

test('R07 passes when SIRTFI asserted and security contact present', function () {
    $entity = makeRefedsEntity();
    $entity->setRelation('attributes', collect([
        makeAttributeStub('entity_category', 'https://refeds.org/sirtfi'),
    ]));
    $entity->setRelation('contacts', collect([makeContactStub('security')]));
    $result = (new R07_SirtfiSecurityContact())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

// ── R08 ────────────────────────────────────────────────────────────────────

test('R08 is not_applicable when CoCo v2 not asserted', function () {
    $result = (new R08_CocoPrivacyUrl())->evaluate(makeRefedsEntity());
    expect($result->status)->toBe('not_applicable');
});

test('R08 fails when CoCo v2 asserted but no privacy URL', function () {
    $entity = makeRefedsEntity();
    $entity->setRelation('attributes', collect([
        makeAttributeStub('entity_category', 'https://refeds.org/category/code-of-conduct/v2'),
    ]));
    $result = (new R08_CocoPrivacyUrl())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

// ── R09 ────────────────────────────────────────────────────────────────────

test('R09 is not_applicable when R&S not asserted', function () {
    $result = (new R09_RsCanonicalUri())->evaluate(makeRefedsEntity());
    expect($result->status)->toBe('not_applicable');
});

test('R09 passes when canonical R&S URI used', function () {
    $entity = makeRefedsEntity();
    $entity->setRelation('attributes', collect([
        makeAttributeStub('entity_category', 'http://refeds.org/category/research-and-scholarship'),
    ]));
    $result = (new R09_RsCanonicalUri())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('R09 fails when non-canonical R&S URI used', function () {
    $entity = makeRefedsEntity();
    $entity->setRelation('attributes', collect([
        makeAttributeStub('entity_category', 'https://refeds.org/category/research-and-scholarship'),
    ]));
    $result = (new R09_RsCanonicalUri())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

// ── R10 ────────────────────────────────────────────────────────────────────

test('R10 passes when scope is set for IdP', function () {
    $entity        = makeRefedsEntity('idp');
    $entity->scope = 'example.org';
    $result        = (new R10_ShibScope())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('R10 warns when scope is empty for IdP', function () {
    $result = (new R10_ShibScope())->evaluate(makeRefedsEntity('idp'));
    expect($result->status)->toBe('warning');
});

// ── R11 ────────────────────────────────────────────────────────────────────

test('R11 is not_applicable when scope is empty', function () {
    $result = (new R11_ScopeMatchesDomain())->evaluate(makeRefedsEntity('idp'));
    expect($result->status)->toBe('not_applicable');
});

test('R11 passes when scope matches entityID domain', function () {
    $entity            = makeRefedsEntity('idp');
    $entity->entity_id = 'https://idp.example.org/saml2';
    $entity->scope     = 'example.org';
    $result            = (new R11_ScopeMatchesDomain())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('R11 warns when scope does not match entityID domain', function () {
    $entity            = makeRefedsEntity('idp');
    $entity->entity_id = 'https://idp.example.org/saml2';
    $entity->scope     = 'other.university.edu';
    $result            = (new R11_ScopeMatchesDomain())->evaluate($entity);
    expect($result->status)->toBe('warning');
});

// ── R12 ────────────────────────────────────────────────────────────────────

test('R12 passes when registration_authority is set', function () {
    $entity                        = makeRefedsEntity();
    $entity->registration_authority = 'https://www.heanet.ie/';
    $result                        = (new R12_RegistrationInfo())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

test('R12 fails when registration_authority is empty', function () {
    $result = (new R12_RegistrationInfo())->evaluate(makeRefedsEntity());
    expect($result->status)->toBe('fail');
});

// ── R15 ────────────────────────────────────────────────────────────────────

test('R15 passes when unspecified NameIDFormat is not listed', function () {
    $result = (new R15_NameidFormatUnspecified())->evaluate(makeRefedsEntity());
    expect($result->status)->toBe('pass');
});

test('R15 warns when unspecified NameIDFormat is listed', function () {
    $entity                 = makeRefedsEntity();
    $entity->nameid_formats = ['urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified'];
    $result                 = (new R15_NameidFormatUnspecified())->evaluate($entity);
    expect($result->status)->toBe('warning');
});
