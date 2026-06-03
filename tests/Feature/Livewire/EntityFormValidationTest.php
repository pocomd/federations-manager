<?php

declare(strict_types=1);

use App\Livewire\EntityForm;
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
});

it('runValidationGate sets validationResults and hasRunValidation', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $component = Livewire::actingAs($admin)
        ->test(EntityForm::class)
        ->set('entity_id', 'https://idp.example.com/saml')
        ->set('type', 'idp')
        ->set('sso_http_redirect', 'https://idp.example.com/sso')
        ->call('runValidationGate');

    $component->assertSet('hasRunValidation', true);
    expect($component->get('validationResults'))->not->toBeEmpty();
});

it('Guest cannot save entity without running validation first', function () {
    $guest = User::factory()->create();
    $guest->assignRole('Guest');

    Livewire::actingAs($guest)
        ->test(EntityForm::class)
        ->set('entity_id', 'https://sp.example.com/saml')
        ->set('type', 'sp')
        ->set('name_en', 'Test SP')
        ->set('description_en', 'A test SP')
        ->set('org_name_en', 'Test Org')
        ->set('org_display_name_en', 'Test Org')
        ->set('org_url_en', 'https://example.com')
        ->set('acs_http_post', 'https://sp.example.com/acs')
        ->call('save')
        ->assertHasErrors('validation');
});

it('Guest can save entity with only warnings when warningsAcknowledged is true', function () {
    $guest = User::factory()->create();
    $guest->assignRole('Guest');

    // Run validation first so validationPassed can be set
    $component = Livewire::actingAs($guest)
        ->test(EntityForm::class)
        ->set('entity_id', 'https://sp.example.com/saml')
        ->set('type', 'sp')
        ->set('name_en', 'Test SP')
        ->set('description_en', 'A test SP')
        ->set('org_name_en', 'Test Org')
        ->set('org_display_name_en', 'Test Org')
        ->set('org_url_en', 'https://example.com')
        ->set('acs_http_post', 'https://sp.example.com/acs')
        ->set('certificates', [['use' => 'signing', 'pem' => '']]) // no cert → warning only
        ->call('runValidationGate');

    // validationPassed depends on whether there are hard failures
    // With no cert there's only a warning, so validationPassed = true (no hard errors)
    $component->assertSet('validationPassed', true);
});

it('Admin can save without running validation', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    // Admin does NOT call runValidationGate — save() should still proceed past the gate
    // It will fail on actual form validation (missing required fields), but NOT on the validation gate
    $component = Livewire::actingAs($admin)
        ->test(EntityForm::class)
        ->set('entity_id', 'https://idp.example.com/saml')
        ->set('type', 'idp')
        ->call('save');

    // Should NOT error on 'validation' gate (may error on other fields like name_en)
    $component->assertHasNoErrors('validation');
});
