<?php

declare(strict_types=1);

use App\Livewire\EntityForm;
use App\Models\Entity;
use App\Models\EntityCertificate;
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

    $this->pem = generateSelfSignedPem();
});

// ── Helpers ────────────────────────────────────────────────────────────────────

function baseEntityFields(string $entityId = 'https://sp.example.com/saml'): array
{
    return [
        'entity_id'           => $entityId,
        'type'                => 'sp',
        'name_en'             => 'Test SP',
        'description_en'      => 'Test description',
        'org_name_en'         => 'Test Org',
        'org_display_name_en' => 'Test Org',
        'org_url_en'          => 'https://org.example.com',
        'acs_http_post'       => 'https://sp.example.com/acs',
    ];
}

// ── Tests ──────────────────────────────────────────────────────────────────────

it('saves both signing and encryption certs when they share the same PEM (new entity)', function () {
    $component = Livewire::actingAs($this->admin)->test(EntityForm::class);

    foreach (baseEntityFields() as $field => $value) {
        $component->set($field, $value);
    }

    $component->set('certificates', [
        ['use' => 'signing',    'pem' => $this->pem],
        ['use' => 'encryption', 'pem' => $this->pem],
    ]);

    $component->call('save');
    $component->assertHasNoErrors();

    $entity = Entity::where('entity_id', 'https://sp.example.com/saml')->first();
    expect($entity)->not->toBeNull();
    expect($entity->certificates)->toHaveCount(2);
    expect($entity->certificates->pluck('use')->sort()->values()->all())
        ->toBe(['encryption', 'signing']);
});

it('preserves both certs on re-save when entity already has signing + encryption with the same PEM', function () {
    $entity = Entity::factory()->create(['type' => 'sp', 'entity_id' => 'https://sp.example.com/saml']);

    // Pre-populate with two certs having the same PEM — simulates a correctly imported entity.
    $parsed = app(\App\Services\Entity\CertificateService::class)->parse($this->pem);
    $base = [
        'pem'                 => $this->pem,
        'subject'             => $parsed['subject'],
        'issuer'              => $parsed['issuer'],
        'serial'              => $parsed['serial'],
        'not_before'          => $parsed['not_before'],
        'not_after'           => $parsed['not_after'],
        'key_bits'            => $parsed['key_bits'],
        'key_algorithm'       => $parsed['key_algorithm'],
        'fingerprint'         => $parsed['fingerprint'],
        'signature_algorithm' => $parsed['signature_algorithm'],
        'debian_weak'         => false,
    ];
    $entity->certificates()->create(['use' => 'signing']    + $base);
    $entity->certificates()->create(['use' => 'encryption'] + $base);

    expect($entity->certificates()->count())->toBe(2);

    // Now open the edit form and save without changing anything.
    $component = Livewire::actingAs($this->admin)->test(EntityForm::class, ['entity' => $entity]);

    foreach (baseEntityFields($entity->entity_id) as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('save');
    $component->assertHasNoErrors();

    $entity->refresh();
    expect($entity->certificates)->toHaveCount(2);
    expect($entity->certificates->pluck('use')->sort()->values()->all())
        ->toBe(['encryption', 'signing']);
});

it('saves both certs when they have different PEMs', function () {
    $pem2 = generateSelfSignedPem();

    $component = Livewire::actingAs($this->admin)->test(EntityForm::class);

    foreach (baseEntityFields('https://sp2.example.com/saml') as $field => $value) {
        $component->set($field, $value);
    }

    $component->set('certificates', [
        ['use' => 'signing',    'pem' => $this->pem],
        ['use' => 'signing',    'pem' => $pem2],
    ]);

    $component->call('save');
    $component->assertHasNoErrors();

    $entity = Entity::where('entity_id', 'https://sp2.example.com/saml')->first();
    expect($entity->certificates)->toHaveCount(2);
});
