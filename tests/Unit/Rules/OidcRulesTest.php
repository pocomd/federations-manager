<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\EntityOidcConfig;
use App\Services\Metadata\Rules\Oidc\O01_RedirectUriHttps;
use App\Services\Metadata\Rules\Oidc\O02_GrantTypeValid;
use App\Services\Metadata\Rules\Oidc\O03_ScopeContainsOpenid;

// ── Helpers ────────────────────────────────────────────────────────────────

function makeOidcEntity(array $configData = []): Entity
{
    $entity            = new Entity();
    $entity->entity_id = 'https://rp.example.com/';
    $entity->type      = 'oidc';
    $entity->setRelation('certificates', collect([]));
    $entity->setRelation('endpoints',    collect([]));
    $entity->setRelation('uiInfo',       collect([]));
    $entity->setRelation('contacts',     collect([]));
    $entity->setRelation('attributes',   collect([]));

    $cfg = new EntityOidcConfig();
    foreach ($configData as $key => $value) {
        $cfg->$key = $value;
    }

    $entity->setRelation('oidcConfig', empty($configData) ? null : $cfg);
    return $entity;
}

// ── O01: Redirect URI HTTPS ────────────────────────────────────────────────

it('O01 passes for https:// URIs', function () {
    $entity = makeOidcEntity(['redirect_uris' => ['https://app.example.com/callback']]);
    $result = (new O01_RedirectUriHttps())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

it('O01 passes for http://localhost URIs', function () {
    $entity = makeOidcEntity(['redirect_uris' => ['http://localhost:8080/callback']]);
    $result = (new O01_RedirectUriHttps())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

it('O01 fails for http:// non-localhost URIs', function () {
    $entity = makeOidcEntity(['redirect_uris' => ['http://example.com/callback']]);
    $result = (new O01_RedirectUriHttps())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

it('O01 fails when redirect_uris is empty', function () {
    $entity = makeOidcEntity(['redirect_uris' => []]);
    $result = (new O01_RedirectUriHttps())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

// ── O02: Grant Type Valid ──────────────────────────────────────────────────

it('O02 passes for authorization_code', function () {
    $entity = makeOidcEntity(['grant_types' => ['authorization_code']]);
    $result = (new O02_GrantTypeValid())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

it('O02 fails for unsupported implicit grant type', function () {
    $entity = makeOidcEntity(['grant_types' => ['implicit']]);
    $result = (new O02_GrantTypeValid())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

it('O02 fails when grant_types is empty', function () {
    $entity = makeOidcEntity(['grant_types' => []]);
    $result = (new O02_GrantTypeValid())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

// ── O03: Scope Contains openid ─────────────────────────────────────────────

it('O03 passes when openid scope is present', function () {
    $entity = makeOidcEntity(['scopes' => ['openid', 'profile', 'email']]);
    $result = (new O03_ScopeContainsOpenid())->evaluate($entity);
    expect($result->status)->toBe('pass');
});

it('O03 fails when openid scope is missing', function () {
    $entity = makeOidcEntity(['scopes' => ['profile', 'email']]);
    $result = (new O03_ScopeContainsOpenid())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

it('O03 fails when no oidc config exists', function () {
    $entity = makeOidcEntity();
    $result = (new O03_ScopeContainsOpenid())->evaluate($entity);
    expect($result->status)->toBe('fail');
});

// ── Rule metadata ──────────────────────────────────────────────────────────

it('OIDC rules appliesTo returns [oidc]', function () {
    expect((new O01_RedirectUriHttps())->appliesTo())->toBe(['oidc']);
    expect((new O02_GrantTypeValid())->appliesTo())->toBe(['oidc']);
    expect((new O03_ScopeContainsOpenid())->appliesTo())->toBe(['oidc']);
});
