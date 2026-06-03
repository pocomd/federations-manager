<?php

declare(strict_types=1);

use App\Livewire\FederationManager;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('approveEntity promotes a draft entity to active', function () {
    $this->actingAs($this->admin);

    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->draft()->create();

    $federation->entities()->attach($entity->id, ['status' => 'pending']);

    Livewire::test(FederationManager::class)
        ->call('approveEntity', (string) $federation->id, (string) $entity->id);

    expect($entity->fresh()->status)->toBe('active');
});

it('approveEntity promotes a pending entity to active', function () {
    $this->actingAs($this->admin);

    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->state(['status' => 'pending'])->create();

    $federation->entities()->attach($entity->id, ['status' => 'pending']);

    Livewire::test(FederationManager::class)
        ->call('approveEntity', (string) $federation->id, (string) $entity->id);

    expect($entity->fresh()->status)->toBe('active');
});

it('approveEntity does not downgrade an already-active entity', function () {
    $this->actingAs($this->admin);

    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create(); // default status is active

    $federation->entities()->attach($entity->id, ['status' => 'pending']);

    Livewire::test(FederationManager::class)
        ->call('approveEntity', (string) $federation->id, (string) $entity->id);

    expect($entity->fresh()->status)->toBe('active');
});

it('approveEntity sets pivot status to active', function () {
    $this->actingAs($this->admin);

    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create();

    $federation->entities()->attach($entity->id, ['status' => 'pending']);

    Livewire::test(FederationManager::class)
        ->call('approveEntity', (string) $federation->id, (string) $entity->id);

    expect(
        $federation->entities()
            ->wherePivot('status', 'active')
            ->where('entities.id', $entity->id)
            ->exists()
    )->toBeTrue();
});
