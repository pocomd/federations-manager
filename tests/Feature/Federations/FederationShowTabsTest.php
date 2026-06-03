<?php

declare(strict_types=1);

use App\Models\AttributeDefinition;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\FederationRequiredAttribute;
use App\Models\FederationValidator;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->federation = Federation::factory()->create(['name' => 'Test Federation']);

    $this->withoutVite();
});

// ── Show page view data ────────────────────────────────────────────────────────

it('show page returns 200 with all required view data', function () {
    $this->actingAs($this->admin)
        ->get(route('federations.show', $this->federation))
        ->assertOk()
        ->assertViewHas('federation')
        ->assertViewHas('idps')
        ->assertViewHas('sps')
        ->assertViewHas('pendingEntities')
        ->assertViewHas('allAttributes')
        ->assertViewHas('addedAttributeIds');
});

it('show page passes idpCount and spCount to view', function () {
    $idp = Entity::factory()->idp()->create();
    $sp  = Entity::factory()->sp()->create();

    $this->federation->entities()->attach($idp->id, ['status' => 'active']);
    $this->federation->entities()->attach($sp->id,  ['status' => 'active']);

    $this->actingAs($this->admin)
        ->get(route('federations.show', $this->federation))
        ->assertOk()
        ->assertViewHas('idpCount', 1)
        ->assertViewHas('spCount', 1);
});

it('show page passes pendingCount to view', function () {
    $entity1 = Entity::factory()->idp()->create();
    $entity2 = Entity::factory()->sp()->create();

    $this->federation->entities()->attach($entity1->id, ['status' => 'pending']);
    $this->federation->entities()->attach($entity2->id, ['status' => 'active']);

    $this->actingAs($this->admin)
        ->get(route('federations.show', $this->federation))
        ->assertOk()
        ->assertViewHas('pendingCount', 1);
});

it('show page loads requiredAttributes relationship', function () {
    $attr = AttributeDefinition::create([
        'name'      => 'eduPersonEntitlement',
        'full_name' => 'urn:oid:1.3.6.1.4.1.5923.1.1.1.7',
        'is_active' => true,
    ]);

    FederationRequiredAttribute::create([
        'federation_id'           => $this->federation->id,
        'attribute_definition_id' => $attr->id,
        'is_required'             => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('federations.show', $this->federation))
        ->assertOk();

    $federation = $response->viewData('federation');
    expect($federation->requiredAttributes)->toHaveCount(1);
    expect($response->viewData('addedAttributeIds'))->toContain($attr->id);
});

it('show page loads validators relationship', function () {
    FederationValidator::create([
        'federation_id' => $this->federation->id,
        'name'          => 'Schema Check',
        'url'           => 'https://validator.example.com/check',
        'http_method'   => 'GET',
        'enabled'       => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('federations.show', $this->federation))
        ->assertOk();

    $federation = $response->viewData('federation');
    expect($federation->validators)->toHaveCount(1);
});

// ── Contacts download ─────────────────────────────────────────────────────────

it('contacts download requires authentication', function () {
    $this->get(route('federations.contacts.download', $this->federation))
        ->assertRedirect(route('login'));
});

it('contacts download returns txt for all entities', function () {
    $idp = Entity::factory()->idp()->create();
    $sp  = Entity::factory()->sp()->create();

    $this->federation->entities()->attach($idp->id, ['status' => 'active']);
    $this->federation->entities()->attach($sp->id,  ['status' => 'active']);

    $this->actingAs($this->admin)
        ->get(route('federations.contacts.download', $this->federation))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');
});

it('contacts download returns txt for idp type only', function () {
    $idp = Entity::factory()->idp()->create();
    $sp  = Entity::factory()->sp()->create();

    $this->federation->entities()->attach($idp->id, ['status' => 'active']);
    $this->federation->entities()->attach($sp->id,  ['status' => 'active']);

    $response = $this->actingAs($this->admin)
        ->get(route('federations.contacts.download', $this->federation) . '?type=idp')
        ->assertOk();

    $content = $response->getContent();
    expect($content)->toContain('IDP:');
    expect($content)->not->toContain('SP:');
});

it('contacts download returns txt for sp type only', function () {
    $idp = Entity::factory()->idp()->create();
    $sp  = Entity::factory()->sp()->create();

    $this->federation->entities()->attach($idp->id, ['status' => 'active']);
    $this->federation->entities()->attach($sp->id,  ['status' => 'active']);

    $response = $this->actingAs($this->admin)
        ->get(route('federations.contacts.download', $this->federation) . '?type=sp')
        ->assertOk();

    $content = $response->getContent();
    expect($content)->toContain('SP:');
    expect($content)->not->toContain('IDP:');
});

it('contacts download includes entity name and email', function () {
    $idp = Entity::factory()->idp()->create();
    $this->federation->entities()->attach($idp->id, ['status' => 'active']);

    $domain = parse_url($idp->entity_id, PHP_URL_HOST);

    $response = $this->actingAs($this->admin)
        ->get(route('federations.contacts.download', $this->federation))
        ->assertOk();

    $content = $response->getContent();
    expect($content)->toContain("technical@{$domain}");
});
