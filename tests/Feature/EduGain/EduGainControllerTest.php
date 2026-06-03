<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\SystemPreference;
use App\Models\User;
use App\Services\EduGain\EduGainApiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('entityStatus returns enabled=false when preference off', function () {
    SystemPreference::firstOrCreate(
        ['key' => 'edugain_checks_enabled'],
        ['value' => '0', 'type' => 'boolean', 'label' => 'eduGAIN', 'category' => 'edugain_checks']
    );

    $entity = Entity::factory()->idp()->create();

    $this->actingAs($this->admin)
        ->getJson(route('edugain.entity.status', $entity))
        ->assertOk()
        ->assertJson(['enabled' => false]);
});

it('entityStatus returns JSON for authenticated user', function () {
    SystemPreference::firstOrCreate(
        ['key' => 'edugain_checks_enabled'],
        ['value' => '1', 'type' => 'boolean', 'label' => 'eduGAIN', 'category' => 'edugain_checks']
    );
    SystemPreference::firstOrCreate(
        ['key' => 'eccs_check_enabled'],
        ['value' => '0', 'type' => 'boolean', 'label' => 'ECCS', 'category' => 'edugain_checks']
    );
    SystemPreference::firstOrCreate(
        ['key' => 'edugain_entity_check_enabled'],
        ['value' => '0', 'type' => 'boolean', 'label' => 'Entity', 'category' => 'edugain_checks']
    );

    $entity = Entity::factory()->idp()->create();

    $this->actingAs($this->admin)
        ->getJson(route('edugain.entity.status', $entity))
        ->assertOk()
        ->assertJsonStructure(['enabled', 'entity_id']);
});

it('entityStatus returns 403 for unauthenticated request', function () {
    $entity = Entity::factory()->idp()->create();

    $this->get(route('edugain.entity.status', $entity))
        ->assertRedirect(route('login'));
});

it('federationStatus returns enabled=false when preference off', function () {
    SystemPreference::firstOrCreate(
        ['key' => 'edugain_checks_enabled'],
        ['value' => '0', 'type' => 'boolean', 'label' => 'eduGAIN', 'category' => 'edugain_checks']
    );

    $this->actingAs($this->admin)
        ->getJson(route('edugain.federation.status'))
        ->assertOk()
        ->assertJson(['enabled' => false]);
});

it('federationStatus returns error when federation code not set', function () {
    SystemPreference::firstOrCreate(
        ['key' => 'edugain_checks_enabled'],
        ['value' => '1', 'type' => 'boolean', 'label' => 'eduGAIN', 'category' => 'edugain_checks']
    );
    SystemPreference::firstOrCreate(
        ['key' => 'edugain_federation_code'],
        ['value' => '', 'type' => 'string', 'label' => 'Code', 'category' => 'edugain_checks']
    );

    $this->actingAs($this->admin)
        ->getJson(route('edugain.federation.status'))
        ->assertOk()
        ->assertJson(['error' => 'Federation code not configured']);
});
