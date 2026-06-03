<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

it('index returns 200 for Admin', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user)
        ->get(route('statistics.index'))
        ->assertOk()
        ->assertViewIs('statistics.index');
});

it('index returns 403 for Guest', function () {
    $user = User::factory()->create();
    $user->assignRole('Guest');

    $this->actingAs($user)
        ->get(route('statistics.index'))
        ->assertForbidden();
});

it('exportEntities returns 200 with csv content-type', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $response = $this->actingAs($user)
        ->get(route('statistics.export.entities'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('exportCertificates returns 200 with csv content-type', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $response = $this->actingAs($user)
        ->get(route('statistics.export.certificates'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});
