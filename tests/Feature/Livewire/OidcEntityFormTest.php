<?php

declare(strict_types=1);

use App\Livewire\EntityForm;
use App\Models\Entity;
use App\Models\EntityOidcConfig;
use App\Models\User;
use Database\Seeders\NotificationTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(NotificationTypesSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

it('saveOidcConfig creates entity_oidc_config row', function () {
    $admin  = User::factory()->create();
    $admin->assignRole('Admin');

    $entity = Entity::factory()->create(['type' => 'oidc']);

    Livewire::actingAs($admin)
        ->test(EntityForm::class, ['entity' => $entity])
        ->set('oidcRedirectUris', 'https://app.example.com/callback')
        ->set('oidcGrantTypes', ['authorization_code'])
        ->set('oidcScopes', 'openid profile')
        ->set('oidcApplicationType', 'web')
        ->set('oidcTokenEndpointAuthMethod', 'client_secret_basic')
        ->call('saveOidcConfig');

    $config = EntityOidcConfig::where('entity_id', $entity->id)->first();
    expect($config)->not->toBeNull();
    expect($config->redirect_uris)->toBe(['https://app.example.com/callback']);
    expect($config->grant_types)->toBe(['authorization_code']);
    expect($config->scopes)->toBe(['openid', 'profile']);
});

it('saveOidcConfig stores redirect_uris as array from newline-separated string', function () {
    $admin  = User::factory()->create();
    $admin->assignRole('Admin');

    $entity = Entity::factory()->create(['type' => 'oidc']);

    Livewire::actingAs($admin)
        ->test(EntityForm::class, ['entity' => $entity])
        ->set('oidcRedirectUris', "https://app.example.com/callback\nhttps://app.example.com/silent-renew")
        ->set('oidcGrantTypes', ['authorization_code'])
        ->set('oidcScopes', 'openid')
        ->call('saveOidcConfig');

    $config = EntityOidcConfig::where('entity_id', $entity->id)->first();
    expect($config->redirect_uris)->toBe([
        'https://app.example.com/callback',
        'https://app.example.com/silent-renew',
    ]);
});
