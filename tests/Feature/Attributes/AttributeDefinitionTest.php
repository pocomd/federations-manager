<?php

declare(strict_types=1);

use App\Models\AttributeDefinition;
use App\Models\EntityRequestedAttribute;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->guest = User::factory()->create();
    $this->guest->assignRole('Entity Manager');
});

it('index returns 200 for authenticated user', function () {
    $this->actingAs($this->guest)
        ->get(route('attributes.index'))
        ->assertOk()
        ->assertSee('Attribute Definitions');
});

it('store creates attribute definition', function () {
    $this->actingAs($this->admin)
        ->post(route('attributes.store'), [
            'name'        => 'testAttribute',
            'full_name'   => 'Test Attribute',
            'saml2_oid'   => 'urn:oid:1.2.3.4.5',
            'is_required' => '0',
            'is_active'   => '1',
        ])
        ->assertRedirect(route('attributes.index'))
        ->assertSessionHas('success');

    expect(AttributeDefinition::where('name', 'testAttribute')->exists())->toBeTrue();
});

it('store rejects invalid OID format', function () {
    $this->actingAs($this->admin)
        ->post(route('attributes.store'), [
            'name'      => 'badOid',
            'full_name' => 'Bad OID',
            'saml2_oid' => 'not-a-valid-oid',
        ])
        ->assertSessionHasErrors('saml2_oid');
});

it('store rejects invalid SAML1 URN format', function () {
    $this->actingAs($this->admin)
        ->post(route('attributes.store'), [
            'name'      => 'badUrn',
            'full_name' => 'Bad URN',
            'saml1_urn' => 'not-a-valid-urn',
        ])
        ->assertSessionHasErrors('saml1_urn');
});

it('destroy deactivates attribute in use instead of deleting', function () {
    $attr = AttributeDefinition::create([
        'name'      => 'inUseAttr',
        'full_name' => 'In Use Attribute',
        'is_active' => true,
    ]);

    $entity = \App\Models\Entity::factory()->sp()->create();

    EntityRequestedAttribute::create([
        'entity_id'               => $entity->id,
        'attribute_definition_id' => $attr->id,
        'is_required'             => false,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('attributes.destroy', $attr))
        ->assertRedirect(route('attributes.index'))
        ->assertSessionHas('error');

    expect(AttributeDefinition::find($attr->id))->not->toBeNull();
    expect(AttributeDefinition::find($attr->id)->is_active)->toBeTrue();
});
