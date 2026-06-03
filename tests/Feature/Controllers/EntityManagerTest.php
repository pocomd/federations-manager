<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\EntityManager;
use App\Models\User;
use App\Policies\EntityPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = User::factory()->create();
    $this->user->assignRole('Admin');

    $this->withoutVite();
});

// ── store() ────────────────────────────────────────────────────────────────────

it('store() sets created_by to the authenticated user', function () {
    $pem = generateSelfSignedPem();

    $this->actingAs($this->user)
        ->post(route('entities.store'), [
            'entity_id'           => 'https://idp.manager-test.example.org/saml2/idp',
            'type'                => 'idp',
            'name_en'             => 'Manager Test IdP',
            'description_en'      => 'Testing entity manager assignment',
            'org_name_en'         => 'Test Org',
            'org_display_name_en' => 'Test Org',
            'org_url_en'          => 'https://www.testorg.ie',
            'sso_http_redirect'   => 'https://idp.manager-test.example.org/saml2/sso/redirect',
            'certificates'        => [
                ['use' => 'signing', 'pem' => $pem],
            ],
        ]);

    $entity = Entity::where('entity_id', 'https://idp.manager-test.example.org/saml2/idp')->firstOrFail();

    expect($entity->created_by)->toBe($this->user->id);
});

it('store() creates an entity_managers owner record for the authenticated user', function () {
    $pem = generateSelfSignedPem();

    $this->actingAs($this->user)
        ->post(route('entities.store'), [
            'entity_id'           => 'https://idp.owner-test.example.org/saml2/idp',
            'type'                => 'idp',
            'name_en'             => 'Owner Test IdP',
            'description_en'      => 'Testing owner record creation',
            'org_name_en'         => 'Test Org',
            'org_display_name_en' => 'Test Org',
            'org_url_en'          => 'https://www.testorg.ie',
            'sso_http_redirect'   => 'https://idp.owner-test.example.org/saml2/sso/redirect',
            'certificates'        => [
                ['use' => 'signing', 'pem' => $pem],
            ],
        ]);

    $entity = Entity::where('entity_id', 'https://idp.owner-test.example.org/saml2/idp')->firstOrFail();

    $manager = EntityManager::where('entity_id', $entity->id)
        ->where('user_id', $this->user->id)
        ->first();

    expect($manager)->not->toBeNull();
    expect($manager->role)->toBe('owner');
    expect($manager->added_by)->toBe($this->user->id);
});

// ── update() ───────────────────────────────────────────────────────────────────

it('update() sets last_updated_by to the authenticated user', function () {
    $pem = generateSelfSignedPem();

    $entity = Entity::factory()->idp()->create([
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->put(route('entities.update', $entity), [
            'edugain'      => false,
            'name_en'      => 'Updated Name',
            'description_en' => 'Updated description',
            'org_name_en'  => 'Updated Org',
            'org_display_name_en' => 'Updated Org',
            'org_url_en'   => 'https://updated.ie',
            'sso_http_redirect' => 'https://idp.example.org/sso/redirect',
        ]);

    expect($entity->fresh()->last_updated_by)->toBe($this->user->id);
});

// ── EntityPolicy ───────────────────────────────────────────────────────────────

it('EntityPolicy::isManager() returns true for an owner', function () {
    $entity = Entity::factory()->idp()->create();
    $user   = User::factory()->create();

    EntityManager::create([
        'entity_id' => $entity->id,
        'user_id'   => $user->id,
        'role'      => 'owner',
        'added_by'  => $user->id,
        'added_at'  => now(),
    ]);

    expect(EntityPolicy::isManager($user, $entity))->toBeTrue();
});

it('EntityPolicy::isManager() returns false for an unrelated user', function () {
    $entity      = Entity::factory()->idp()->create();
    $unrelated   = User::factory()->create();

    expect(EntityPolicy::isManager($unrelated, $entity))->toBeFalse();
});
