<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\Federation;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

// ── WebFinger ─────────────────────────────────────────────────────────────────

it('webFinger returns 404 for unknown entity', function () {
    $this->getJson(route('webfinger', [], false) . '?resource=https://unknown.example.com/saml')
        ->assertStatus(404);
});

it('webFinger returns JSON with subject and links for active entity', function () {
    $entity = Entity::factory()->idp()->create(['status' => 'active']);

    $response = $this->getJson(route('webfinger', [], false) . '?resource=' . urlencode($entity->entity_id));

    $response->assertOk()
        ->assertJsonFragment(['subject' => $entity->entity_id])
        ->assertJsonStructure(['subject', 'links']);
});

it('webFinger returns 404 for suspended entity', function () {
    $entity = Entity::factory()->idp()->create(['status' => 'suspended']);

    $this->getJson(route('webfinger', [], false) . '?resource=' . urlencode($entity->entity_id))
        ->assertStatus(404);
});

it('webFinger returns 400 when resource parameter is missing', function () {
    $this->getJson(route('webfinger'))
        ->assertStatus(400);
});

// ── JEDI discovery ────────────────────────────────────────────────────────────

it('entities endpoint returns paginated array without auth', function () {
    Entity::factory()->idp()->count(3)->create(['status' => 'active']);

    $this->getJson(route('discovery.entities'))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

it('entities endpoint filters by type=idp', function () {
    Entity::factory()->idp()->create(['status' => 'active']);
    Entity::factory()->sp()->create(['status' => 'active']);

    $response = $this->getJson(route('discovery.entities') . '?type=idp');
    $response->assertOk();

    $data = $response->json('data');
    expect(count($data))->toBe(1);
});
