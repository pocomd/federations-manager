<?php

declare(strict_types=1);

use App\Models\AttributeDefinition;
use App\Models\Entity;
use App\Models\EntityArp;
use App\Models\Federation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->idp = Entity::factory()->idp()->create();
    $this->sp  = Entity::factory()->sp()->create();

    $this->attr = AttributeDefinition::create([
        'name'      => 'mail',
        'full_name' => 'Email Address',
        'saml2_oid' => 'urn:oid:0.9.2342.19200300.100.1.3',
        'is_active' => true,
    ]);

    // Put both in the same federation
    $federation = Federation::factory()->create();
    $this->idp->federations()->attach($federation->id, ['status' => 'active']);
    $this->sp->federations()->attach($federation->id, ['status' => 'active']);
});

it('index returns 403 for SP entity', function () {
    $this->actingAs($this->admin)
        ->get(route('entities.arp', $this->sp))
        ->assertForbidden();
});

it('index shows ARP editor for IdP', function () {
    $this->actingAs($this->admin)
        ->get(route('entities.arp', $this->idp))
        ->assertOk()
        ->assertSee('Attribute Release Policy');
});

it('store saves ARP rule', function () {
    $this->actingAs($this->admin)
        ->post(route('entities.arp.store', $this->idp), [
            'sp_entity_id'            => $this->sp->id,
            'attribute_definition_id' => $this->attr->id,
            'is_permitted'            => '1',
            'notes'                   => 'Permitted for federated login',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(
        EntityArp::where('idp_entity_id', $this->idp->id)
            ->where('sp_entity_id', $this->sp->id)
            ->where('attribute_definition_id', $this->attr->id)
            ->where('is_permitted', true)
            ->exists()
    )->toBeTrue();
});

it('store updates existing ARP rule', function () {
    EntityArp::create([
        'idp_entity_id'           => $this->idp->id,
        'sp_entity_id'            => $this->sp->id,
        'attribute_definition_id' => $this->attr->id,
        'is_permitted'            => true,
    ]);

    $this->actingAs($this->admin)
        ->post(route('entities.arp.store', $this->idp), [
            'sp_entity_id'            => $this->sp->id,
            'attribute_definition_id' => $this->attr->id,
            'is_permitted'            => '0',
            'notes'                   => 'Now blocked',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $rule = EntityArp::where('idp_entity_id', $this->idp->id)
        ->where('sp_entity_id', $this->sp->id)
        ->where('attribute_definition_id', $this->attr->id)
        ->first();

    expect($rule->is_permitted)->toBeFalse();
    expect($rule->notes)->toBe('Now blocked');
});
