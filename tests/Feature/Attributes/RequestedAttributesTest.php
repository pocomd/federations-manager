<?php

declare(strict_types=1);

use App\Models\AttributeDefinition;
use App\Models\Entity;
use App\Models\EntityRequestedAttribute;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sp = Entity::factory()->sp()->create();

    $this->attr = AttributeDefinition::create([
        'name'      => 'mail',
        'full_name' => 'Email Address',
        'saml2_oid' => 'urn:oid:0.9.2342.19200300.100.1.3',
        'is_active' => true,
    ]);
});

it('index shows SP requested attributes', function () {
    $this->actingAs($this->admin)
        ->get(route('entities.requested-attributes', $this->sp))
        ->assertOk()
        ->assertSee('Requested Attributes');
});

it('store adds attribute to SP', function () {
    $this->actingAs($this->admin)
        ->post(route('entities.requested-attributes.store', $this->sp), [
            'attribute_definition_id' => $this->attr->id,
            'is_required'             => '0',
            'reason'                  => 'Needed for login.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(
        EntityRequestedAttribute::where('entity_id', $this->sp->id)
            ->where('attribute_definition_id', $this->attr->id)
            ->exists()
    )->toBeTrue();
});

it('store prevents duplicate attribute', function () {
    EntityRequestedAttribute::create([
        'entity_id'               => $this->sp->id,
        'attribute_definition_id' => $this->attr->id,
        'is_required'             => false,
    ]);

    $this->actingAs($this->admin)
        ->post(route('entities.requested-attributes.store', $this->sp), [
            'attribute_definition_id' => $this->attr->id,
            'is_required'             => '0',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('destroy removes attribute from SP', function () {
    $ra = EntityRequestedAttribute::create([
        'entity_id'               => $this->sp->id,
        'attribute_definition_id' => $this->attr->id,
        'is_required'             => false,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('entities.requested-attributes.destroy', [$this->sp, $ra]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(EntityRequestedAttribute::find($ra->id))->toBeNull();
});
