<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\EntityOidcConfig;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

it('API returns 404 for SAML entity', function () {
    $entity = Entity::factory()->idp()->create(['status' => 'active']);

    $this->getJson(route('api.entities.oidc-configuration', $entity))
        ->assertNotFound();
});

it('API returns 404 when OIDC entity has no config', function () {
    $entity = Entity::factory()->create(['type' => 'oidc', 'status' => 'active']);

    $this->getJson(route('api.entities.oidc-configuration', $entity))
        ->assertStatus(404);
});

it('API returns correct RFC 7591 JSON for OIDC entity', function () {
    $entity = Entity::factory()->create(['type' => 'oidc', 'status' => 'active']);

    EntityOidcConfig::create([
        'entity_id'                  => $entity->id,
        'redirect_uris'              => ['https://app.example.com/callback'],
        'grant_types'                => ['authorization_code'],
        'response_types'             => ['code'],
        'scopes'                     => ['openid', 'profile'],
        'application_type'           => 'web',
        'token_endpoint_auth_method' => 'client_secret_basic',
    ]);

    $this->getJson(route('api.entities.oidc-configuration', $entity))
        ->assertOk()
        ->assertJsonFragment([
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types'   => ['authorization_code'],
            'scope'         => 'openid profile',
        ])
        ->assertJsonStructure(['client_id', 'redirect_uris', 'grant_types', 'response_types', 'scope', 'application_type', 'token_endpoint_auth_method']);
});

it('API endpoint is accessible without authentication', function () {
    $entity = Entity::factory()->create(['type' => 'oidc', 'status' => 'active']);

    EntityOidcConfig::create([
        'entity_id'      => $entity->id,
        'redirect_uris'  => ['https://app.example.com/callback'],
        'grant_types'    => ['authorization_code'],
        'response_types' => ['code'],
        'scopes'         => ['openid'],
    ]);

    $this->getJson(route('api.entities.oidc-configuration', $entity))
        ->assertOk();
});
