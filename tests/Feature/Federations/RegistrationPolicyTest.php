<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\Federation;
use App\Models\FederationRegistrationPolicy;
use App\Models\User;
use App\Services\Entity\EntityMetadataService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->guest = User::factory()->create();
    $this->guest->assignRole('Guest');

    $this->federation = Federation::factory()->create([
        'name'   => 'Test Federation',
        'uri'    => 'https://federation.example.org',
        'status' => 'active',
    ]);
});

it('index returns 200 for Admin', function () {
    $this->actingAs($this->admin)
        ->get(route('federations.policies.index', $this->federation))
        ->assertOk()
        ->assertSee('Registration Policies');
});

it('index returns 403 for Guest', function () {
    $this->actingAs($this->guest)
        ->get(route('federations.policies.index', $this->federation))
        ->assertForbidden();
});

it('store creates policy with valid data', function () {
    $this->actingAs($this->admin)
        ->post(route('federations.policies.store', $this->federation), [
            'display_name' => 'Federation Registration Policy',
            'lang'         => 'en',
            'url'          => 'https://federation.example.org/policy/2024',
            'enabled'      => '1',
        ])
        ->assertRedirect(route('federations.policies.index', $this->federation))
        ->assertSessionHas('success');

    expect(
        FederationRegistrationPolicy::where('federation_id', $this->federation->id)
            ->where('lang', 'en')
            ->exists()
    )->toBeTrue();
});

it('store rejects URL without https', function () {
    $this->actingAs($this->admin)
        ->post(route('federations.policies.store', $this->federation), [
            'display_name' => 'Policy',
            'lang'         => 'en',
            'url'          => 'http://insecure.example.org/policy',
        ])
        ->assertSessionHasErrors('url');
});

it('store rejects duplicate language for same federation', function () {
    FederationRegistrationPolicy::create([
        'federation_id' => $this->federation->id,
        'display_name'  => 'Existing Policy',
        'lang'          => 'en',
        'url'           => 'https://federation.example.org/policy',
        'enabled'       => true,
    ]);

    $this->actingAs($this->admin)
        ->post(route('federations.policies.store', $this->federation), [
            'display_name' => 'Duplicate',
            'lang'         => 'en',
            'url'          => 'https://federation.example.org/policy2',
        ])
        ->assertSessionHasErrors('lang');
});

it('store allows same language for different federation', function () {
    $otherFed = Federation::factory()->create();

    FederationRegistrationPolicy::create([
        'federation_id' => $otherFed->id,
        'display_name'  => 'Other Fed Policy',
        'lang'          => 'en',
        'url'           => 'https://other.example.org/policy',
        'enabled'       => true,
    ]);

    $this->actingAs($this->admin)
        ->post(route('federations.policies.store', $this->federation), [
            'display_name' => 'My Policy',
            'lang'         => 'en',
            'url'          => 'https://federation.example.org/policy',
        ])
        ->assertRedirect(route('federations.policies.index', $this->federation))
        ->assertSessionHas('success');
});

it('update changes policy URL', function () {
    $policy = FederationRegistrationPolicy::create([
        'federation_id' => $this->federation->id,
        'display_name'  => 'Original',
        'lang'          => 'en',
        'url'           => 'https://federation.example.org/policy/2023',
        'enabled'       => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('federations.policies.update', [$this->federation, $policy]), [
            'display_name' => 'Updated',
            'lang'         => 'en',
            'url'          => 'https://federation.example.org/policy/2024',
            'enabled'      => '1',
        ])
        ->assertRedirect(route('federations.policies.index', $this->federation))
        ->assertSessionHas('success');

    expect($policy->fresh()->url)->toBe('https://federation.example.org/policy/2024');
});

it('destroy deletes policy', function () {
    $policy = FederationRegistrationPolicy::create([
        'federation_id' => $this->federation->id,
        'display_name'  => 'To Delete',
        'lang'          => 'en',
        'url'           => 'https://federation.example.org/policy',
        'enabled'       => true,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('federations.policies.destroy', [$this->federation, $policy]))
        ->assertRedirect(route('federations.policies.index', $this->federation))
        ->assertSessionHas('success');

    expect(FederationRegistrationPolicy::find($policy->id))->toBeNull();
});

it('renderXml includes mdrpi:RegistrationPolicy when policy exists', function () {
    $entity = Entity::factory()->idp()->create([
        'registration_authority' => 'https://federation.example.org',
    ]);

    $this->federation->entities()->attach($entity->id, ['status' => 'active']);

    FederationRegistrationPolicy::create([
        'federation_id' => $this->federation->id,
        'display_name'  => 'Registration Policy',
        'lang'          => 'en',
        'url'           => 'https://federation.example.org/policy/2024',
        'enabled'       => true,
    ]);

    $service = app(EntityMetadataService::class);
    $xml     = $service->renderXml($entity);

    expect($xml)->toContain('mdrpi:RegistrationPolicy')
        ->and($xml)->toContain('xml:lang="en"')
        ->and($xml)->toContain('https://federation.example.org/policy/2024');
});

it('renderXml omits mdrpi:RegistrationPolicy when no policies', function () {
    $entity = Entity::factory()->idp()->create([
        'registration_authority' => 'https://federation.example.org',
    ]);

    $this->federation->entities()->attach($entity->id, ['status' => 'active']);

    $service = app(EntityMetadataService::class);
    $xml     = $service->renderXml($entity);

    expect($xml)->toContain('mdrpi:RegistrationInfo')
        ->and($xml)->not->toContain('mdrpi:RegistrationPolicy');
});
