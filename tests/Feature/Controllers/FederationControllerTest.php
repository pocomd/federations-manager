<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\Federation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = User::factory()->create();
    $this->user->assignRole('Admin');

    $this->withoutVite();
});

// ── index ──────────────────────────────────────────────────────────────────────

it('index returns 200 for an authenticated admin', function () {
    $this->actingAs($this->user)
        ->get(route('federations.index'))
        ->assertOk();
});

it('index redirects unauthenticated users', function () {
    $this->get(route('federations.index'))
        ->assertRedirect();
});

it('index lists existing federations', function () {
    Federation::factory()->create(['name' => 'HEAnet Federation']);

    $this->actingAs($this->user)
        ->get(route('federations.index'))
        ->assertOk()
        ->assertSee('HEAnet Federation');
});

// ── store ──────────────────────────────────────────────────────────────────────

it('store creates a federation and redirects to show', function () {
    $response = $this->actingAs($this->user)
        ->post(route('federations.store'), [
            'name'        => 'Test Federation',
            'uri'         => 'https://federation.test.example.com',
            'description' => 'A test federation.',
        ]);

    $response->assertRedirect();

    expect(Federation::where('uri', 'https://federation.test.example.com')->exists())->toBeTrue();
});

it('store validates required fields', function () {
    $this->actingAs($this->user)
        ->postJson(route('federations.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'uri']);
});

it('store rejects a duplicate URI', function () {
    Federation::factory()->create(['uri' => 'https://federation.dup.example.com']);

    $this->actingAs($this->user)
        ->postJson(route('federations.store'), [
            'name' => 'Duplicate',
            'uri'  => 'https://federation.dup.example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['uri']);
});

// ── show ───────────────────────────────────────────────────────────────────────

it('show returns 200 for an existing federation', function () {
    $federation = Federation::factory()->create();

    $this->actingAs($this->user)
        ->get(route('federations.show', $federation))
        ->assertOk()
        ->assertSee($federation->name);
});

// ── update ─────────────────────────────────────────────────────────────────────

it('update changes the federation name', function () {
    $federation = Federation::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->user)
        ->patch(route('federations.update', $federation), [
            'name' => 'New Name',
            'uri'  => $federation->uri,
        ])
        ->assertRedirect(route('federations.show', $federation->fresh()));

    expect($federation->fresh()->name)->toBe('New Name');
});

// ── destroy ────────────────────────────────────────────────────────────────────

it('destroy soft-deletes an inactive federation', function () {
    $federation = Federation::factory()->inactive()->create();

    $this->actingAs($this->user)
        ->delete(route('federations.destroy', $federation))
        ->assertRedirect(route('federations.index'));

    expect(Federation::withTrashed()->find($federation->id)?->deleted_at)->not->toBeNull();
});

// ── addEntity ──────────────────────────────────────────────────────────────────

it('addEntity attaches entity with pending pivot status', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create();

    $this->actingAs($this->user)
        ->post(route('federations.entities.add', [$federation, $entity]))
        ->assertRedirect();

    expect(
        $federation->entities()
            ->wherePivot('status', 'pending')
            ->where('entities.id', $entity->id)
            ->exists()
    )->toBeTrue();
});

it('addEntity is idempotent — duplicate returns error', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create();

    $federation->entities()->attach($entity->id, ['status' => 'pending']);

    $response = $this->actingAs($this->user)
        ->post(route('federations.entities.add', [$federation, $entity]));

    $response->assertRedirect();

    // Pivot should still have exactly one row
    expect(
        $federation->entities()->where('entities.id', $entity->id)->count()
    )->toBe(1);
});

// ── removeEntity ───────────────────────────────────────────────────────────────

it('removeEntity detaches the entity from the federation', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create();

    $federation->entities()->attach($entity->id, ['status' => 'active']);

    $this->actingAs($this->user)
        ->delete(route('federations.entities.remove', [$federation, $entity]))
        ->assertRedirect();

    expect(
        $federation->entities()->where('entities.id', $entity->id)->exists()
    )->toBeFalse();
});

// ── approveEntity ──────────────────────────────────────────────────────────────

it('approveEntity sets pivot status to active', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create();

    $federation->entities()->attach($entity->id, ['status' => 'pending']);

    $this->actingAs($this->user)
        ->patch(route('federations.entities.approve', [$federation, $entity]))
        ->assertRedirect();

    expect(
        $federation->entities()
            ->wherePivot('status', 'active')
            ->where('entities.id', $entity->id)
            ->exists()
    )->toBeTrue();
});

it('approveEntity promotes entity status from draft to active', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->draft()->create();

    $federation->entities()->attach($entity->id, ['status' => 'pending']);

    $this->actingAs($this->user)
        ->patch(route('federations.entities.approve', [$federation, $entity]));

    expect($entity->fresh()->status)->toBe('active');
});

it('approveEntity does not change entity status when already active', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create(); // defaults to active

    $federation->entities()->attach($entity->id, ['status' => 'pending']);

    $this->actingAs($this->user)
        ->patch(route('federations.entities.approve', [$federation, $entity]));

    expect($entity->fresh()->status)->toBe('active');
});

it('approveEntity records approved_by and approved_at', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create();

    $federation->entities()->attach($entity->id, ['status' => 'pending']);

    $this->actingAs($this->user)
        ->patch(route('federations.entities.approve', [$federation, $entity]));

    $pivot = $federation->entities()
        ->withPivot(['status', 'approved_by', 'approved_at'])
        ->where('entities.id', $entity->id)
        ->first()
        ?->pivot;

    expect($pivot?->approved_by)->toBe($this->user->id);
    expect($pivot?->approved_at)->not->toBeNull();
});

// ── rejectEntity ───────────────────────────────────────────────────────────────

it('rejectEntity sets pivot status to rejected', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create();

    $federation->entities()->attach($entity->id, ['status' => 'pending']);

    $this->actingAs($this->user)
        ->patch(route('federations.entities.reject', [$federation, $entity]))
        ->assertRedirect();

    expect(
        $federation->entities()
            ->wherePivot('status', 'rejected')
            ->where('entities.id', $entity->id)
            ->exists()
    )->toBeTrue();
});

// ── metadata.feed (public endpoint) ───────────────────────────────────────────

it('metadata feed returns a 200 response with XML content type for active federation', function () {
    $federation = Federation::factory()->create(['status' => 'active']);

    $response = $this->get(route('metadata.feed', $federation));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/samlmetadata+xml');
});

it('metadata feed returns an EntitiesDescriptor document', function () {
    $federation = Federation::factory()->create(['status' => 'active']);

    $response = $this->get(route('metadata.feed', $federation));

    $response->assertOk();

    $xml = $response->getContent();

    expect($xml)->toContain('EntitiesDescriptor');
});

it('metadata feed includes active member entities in the XML', function () {
    $federation = Federation::factory()->create(['status' => 'active']);
    $entity     = Entity::factory()->create(['status' => 'active']);

    $federation->entities()->attach($entity->id, ['status' => 'active']);

    $response = $this->get(route('metadata.feed', $federation));

    $response->assertOk();

    $xml = $response->getContent();

    expect($xml)->toContain('EntityDescriptor');
    expect($xml)->toContain($entity->entity_id);
});

it('metadata feed returns 404 for inactive federation', function () {
    $federation = Federation::factory()->create(['status' => 'inactive']);

    $this->get(route('metadata.feed', $federation))
        ->assertNotFound();
});

it('metadata download requires authentication', function () {
    $federation = Federation::factory()->create(['status' => 'active']);

    $this->get(route('metadata.download', $federation))
        ->assertRedirect();
});
