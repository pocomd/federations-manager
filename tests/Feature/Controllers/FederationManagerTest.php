<?php

declare(strict_types=1);

use App\Models\Federation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->fm = User::factory()->create();
    $this->fm->assignRole('Federation Manager');

    $this->federationA = Federation::factory()->create(['name' => 'Federation A']);
    $this->federationB = Federation::factory()->create(['name' => 'Federation B']);

    $this->withoutVite();
});

// ── index scoping ──────────────────────────────────────────────────────────────

it('Federation Manager sees only their assigned federations in index', function () {
    // Assign FM to federation A only
    $this->federationA->managers()->attach($this->fm->id, [
        'assigned_by' => $this->admin->id,
        'assigned_at' => now(),
    ]);

    $this->actingAs($this->fm)
        ->get(route('federations.index'))
        ->assertOk()
        ->assertSee('Federation A')
        ->assertDontSee('Federation B');
});

it('Admin sees all federations in index', function () {
    $this->actingAs($this->admin)
        ->get(route('federations.index'))
        ->assertOk()
        ->assertSee('Federation A')
        ->assertSee('Federation B');
});

// ── addManager ─────────────────────────────────────────────────────────────────

it('addManager attaches pivot record', function () {
    $this->actingAs($this->admin)
        ->post(route('federations.managers.add', $this->federationA), [
            'user_id' => $this->fm->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('federation_managers', [
        'federation_id' => $this->federationA->id,
        'user_id'       => $this->fm->id,
        'assigned_by'   => $this->admin->id,
    ]);
});

it('addManager requires federation.create permission', function () {
    $guest = User::factory()->create();
    $guest->assignRole('Guest');

    $this->actingAs($guest)
        ->post(route('federations.managers.add', $this->federationA), [
            'user_id' => $this->fm->id,
        ])
        ->assertForbidden();
});

// ── removeManager ──────────────────────────────────────────────────────────────

it('removeManager detaches pivot record', function () {
    $this->federationA->managers()->attach($this->fm->id, [
        'assigned_by' => $this->admin->id,
        'assigned_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->delete(route('federations.managers.remove', [$this->federationA, $this->fm]))
        ->assertRedirect();

    $this->assertDatabaseMissing('federation_managers', [
        'federation_id' => $this->federationA->id,
        'user_id'       => $this->fm->id,
    ]);
});

it('removeManager requires federation.create permission', function () {
    $this->federationA->managers()->attach($this->fm->id, [
        'assigned_by' => $this->admin->id,
        'assigned_at' => now(),
    ]);

    $guest = User::factory()->create();
    $guest->assignRole('Guest');

    $this->actingAs($guest)
        ->delete(route('federations.managers.remove', [$this->federationA, $this->fm]))
        ->assertForbidden();

    $this->assertDatabaseHas('federation_managers', [
        'federation_id' => $this->federationA->id,
        'user_id'       => $this->fm->id,
    ]);
});
