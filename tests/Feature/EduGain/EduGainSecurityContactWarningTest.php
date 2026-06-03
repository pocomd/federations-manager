<?php

declare(strict_types=1);

use App\Livewire\EntityForm;
use App\Models\Entity;
use App\Models\EntityContact;
use App\Models\User;
use Database\Seeders\NotificationTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(NotificationTypesSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->withoutVite();
});

// ── Show page badges ───────────────────────────────────────────────────────────

it('show page displays security contact badge for eduGAIN entity without security contact', function () {
    $entity = Entity::factory()->sp()->create(['edugain' => true]);
    // Factory creates only technical + support contacts — no security contact.

    $this->actingAs($this->admin)
        ->get(route('entities.show', $entity))
        ->assertOk()
        ->assertSee('No security contact — excluded from feed');
});

it('show page does not display security contact badge when security contact is present', function () {
    $entity = Entity::factory()->sp()->create(['edugain' => true]);

    EntityContact::create([
        'entity_id'  => $entity->id,
        'type'       => 'security',
        'given_name' => 'Security',
        'sur_name'   => 'Contact',
        'email'      => 'security@example.com',
    ]);

    $this->actingAs($this->admin)
        ->get(route('entities.show', $entity))
        ->assertOk()
        ->assertDontSee('No security contact — excluded from feed');
});

it('show page does not display security contact badge for non-eduGAIN entity', function () {
    $entity = Entity::factory()->sp()->create(['edugain' => false]);
    // No security contact, but not in eduGAIN — no badge expected.

    $this->actingAs($this->admin)
        ->get(route('entities.show', $entity))
        ->assertOk()
        ->assertDontSee('No security contact — excluded from feed');
});

// ── EntityForm inline warning ──────────────────────────────────────────────────

it('entity form shows security contact warning when eduGAIN is enabled and no security contact', function () {
    $entity = Entity::factory()->sp()->create(['edugain' => true]);
    // Factory adds only technical + support contacts.

    Livewire::actingAs($this->admin)
        ->test(EntityForm::class, ['entity' => $entity])
        ->assertSee('no security contact')
        ->assertSee('excluded from the eduGAIN metadata feed');
});

it('entity form hides security contact warning when security contact is present', function () {
    $entity = Entity::factory()->sp()->create(['edugain' => true]);

    EntityContact::create([
        'entity_id'  => $entity->id,
        'type'       => 'security',
        'given_name' => 'Security',
        'sur_name'   => 'Contact',
        'email'      => 'security@example.com',
    ]);

    Livewire::actingAs($this->admin)
        ->test(EntityForm::class, ['entity' => $entity])
        ->assertDontSee('no security contact');
});

it('entity form hides security contact warning when eduGAIN flag is off', function () {
    $entity = Entity::factory()->sp()->create(['edugain' => false]);
    // No security contact, but eduGAIN is off — no warning expected.

    Livewire::actingAs($this->admin)
        ->test(EntityForm::class, ['entity' => $entity])
        ->assertDontSee('no security contact');
});
