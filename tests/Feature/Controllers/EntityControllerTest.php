<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = User::factory()->create();
    $this->user->assignRole('Admin');

    // Prevent the @vite() directive in layouts/app.blade.php from throwing
    // when no built manifest or hot file is present during testing.
    $this->withoutVite();
});

// ── index ──────────────────────────────────────────────────────────────────────

it('index returns 200 for an authenticated admin', function () {
    $this->actingAs($this->user)
        ->get(route('entities.index'))
        ->assertOk();
});

it('index redirects unauthenticated users', function () {
    $this->get(route('entities.index'))
        ->assertRedirect();
});

// ── store ──────────────────────────────────────────────────────────────────────

it('store creates an entity and redirects', function () {
    $pem = generateSelfSignedPem();

    $response = $this->actingAs($this->user)
        ->post(route('entities.store'), [
            'entity_id'           => 'https://idp.test.example.org/saml2/idp',
            'type'                => 'idp',
            'name_en'             => 'Test IdP',
            'description_en'      => 'A test identity provider',
            'org_name_en'         => 'Test University',
            'org_display_name_en' => 'Test University',
            'org_url_en'          => 'https://www.testuniversity.ie',
            'sso_http_redirect'   => 'https://idp.test.example.org/saml2/sso/redirect',
            'certificates'        => [
                ['use' => 'signing', 'pem' => $pem],
            ],
        ]);

    $response->assertStatus(302);

    expect(Entity::where('entity_id', 'https://idp.test.example.org/saml2/idp')->exists())
        ->toBeTrue();
});

it('store persists key_algorithm and signature_algorithm from the parsed PEM', function () {
    $pem = generateSelfSignedPem();

    $this->actingAs($this->user)
        ->post(route('entities.store'), [
            'entity_id'           => 'https://idp2.test.example.org/saml2/idp',
            'type'                => 'idp',
            'name_en'             => 'Test IdP 2',
            'description_en'      => 'A second test identity provider',
            'org_name_en'         => 'Test University',
            'org_display_name_en' => 'Test University',
            'org_url_en'          => 'https://www.testuniversity.ie',
            'sso_http_redirect'   => 'https://idp2.test.example.org/saml2/sso/redirect',
            'certificates'        => [
                ['use' => 'signing', 'pem' => $pem],
            ],
        ]);

    $entity = Entity::where('entity_id', 'https://idp2.test.example.org/saml2/idp')->firstOrFail();

    expect($entity->certificates)->toHaveCount(1);

    $cert = $entity->certificates->first();
    expect($cert->key_algorithm)->toBe('RSA');
    expect($cert->key_bits)->toBe(2048);
    expect($cert->signature_algorithm)->not->toBeEmpty();
});

// ── validation ─────────────────────────────────────────────────────────────────

it('validation rejects a non-URL entityID', function () {
    $this->actingAs($this->user)
        ->postJson(route('entities.store'), [
            'entity_id'           => 'not-a-url',
            'type'                => 'idp',
            'name_en'             => 'Test',
            'description_en'      => 'Test',
            'org_name_en'         => 'Org',
            'org_display_name_en' => 'Org',
            'org_url_en'          => 'https://org.ie',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_id']);
});

it('validation rejects an IdP with no SSO endpoint', function () {
    $this->actingAs($this->user)
        ->postJson(route('entities.store'), [
            'entity_id'           => 'https://idp.test.org/saml2/idp',
            'type'                => 'idp',
            'name_en'             => 'Test',
            'description_en'      => 'Test',
            'org_name_en'         => 'Org',
            'org_display_name_en' => 'Org',
            'org_url_en'          => 'https://org.ie',
            'certificates'        => [['use' => 'signing', 'pem' => 'x']],
            // sso_http_post and sso_http_redirect deliberately absent
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sso_http_post']);
});

it('validation requires at least one certificate', function () {
    $this->actingAs($this->user)
        ->postJson(route('entities.store'), [
            'entity_id'           => 'https://idp.test.org/saml2/idp',
            'type'                => 'idp',
            'name_en'             => 'Test',
            'description_en'      => 'Test',
            'org_name_en'         => 'Org',
            'org_display_name_en' => 'Org',
            'org_url_en'          => 'https://org.ie',
            'sso_http_redirect'   => 'https://idp.test.org/saml2/sso',
            'certificates'        => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['certificates']);
});

// ── role-based access ──────────────────────────────────────────────────────────

it('guest cannot create an entity (403)', function () {
    $guest = User::factory()->create();
    $guest->assignRole('Guest');

    $response = $this->actingAs($guest)
        ->post(route('entities.store'), [
            'entity_id'           => 'https://idp.guest.example.org/saml2/idp',
            'type'                => 'idp',
            'name_en'             => 'Guest IdP',
            'description_en'      => 'Guest created',
            'org_name_en'         => 'Org',
            'org_display_name_en' => 'Org',
            'org_url_en'          => 'https://org.ie',
            'sso_http_redirect'   => 'https://idp.guest.example.org/saml2/sso',
            'certificates'        => [['use' => 'signing', 'pem' => generateSelfSignedPem()]],
        ]);

    $response->assertStatus(403);
});

it('operator can create an entity', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Entity Manager');

    $response = $this->actingAs($operator)
        ->post(route('entities.store'), [
            'entity_id'           => 'https://idp.operator.example.org/saml2/idp',
            'type'                => 'idp',
            'name_en'             => 'Operator IdP',
            'description_en'      => 'Created by operator',
            'org_name_en'         => 'Operator Org',
            'org_display_name_en' => 'Operator Org',
            'org_url_en'          => 'https://operatororg.ie',
            'sso_http_redirect'   => 'https://idp.operator.example.org/saml2/sso',
            'certificates'        => [['use' => 'signing', 'pem' => generateSelfSignedPem()]],
        ]);

    $response->assertStatus(302);
    expect(Entity::where('entity_id', 'https://idp.operator.example.org/saml2/idp')->exists())->toBeTrue();
});

// ── entityID uniqueness ────────────────────────────────────────────────────────

it('store rejects a duplicate entityID', function () {
    Entity::factory()->create(['entity_id' => 'https://idp.dupe.example.org/saml2/idp']);

    $this->actingAs($this->user)
        ->postJson(route('entities.store'), [
            'entity_id'           => 'https://idp.dupe.example.org/saml2/idp',
            'type'                => 'idp',
            'name_en'             => 'Duplicate',
            'description_en'      => 'Duplicate IdP',
            'org_name_en'         => 'Org',
            'org_display_name_en' => 'Org',
            'org_url_en'          => 'https://org.ie',
            'sso_http_redirect'   => 'https://idp.dupe.example.org/saml2/sso',
            'certificates'        => [['use' => 'signing', 'pem' => generateSelfSignedPem()]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_id']);
});

// ── destroy ────────────────────────────────────────────────────────────────────

it('destroy soft-deletes a suspended entity', function () {
    $entity = Entity::factory()->create(['status' => 'suspended']);

    $this->actingAs($this->user)
        ->delete(route('entities.destroy', $entity))
        ->assertRedirect(route('entities.index'));

    expect(Entity::withTrashed()->find($entity->id)?->deleted_at)->not->toBeNull();
});

it('destroy refuses to delete an entity that is active in a federation', function () {
    $entity     = Entity::factory()->create();
    $federation = \App\Models\Federation::factory()->create();

    // Attach with status=active so the guard fires
    $entity->federations()->attach($federation->id, [
        'status'      => 'active',
        'approved_by' => null,
        'approved_at' => null,
    ]);

    $this->actingAs($this->user)
        ->delete(route('entities.destroy', $entity))
        ->assertRedirect()
        ->assertSessionHas('error');

    // Entity must still exist (not soft-deleted)
    expect(Entity::find($entity->id))->not->toBeNull();
});
