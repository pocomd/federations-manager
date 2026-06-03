<?php

declare(strict_types=1);

use App\Models\SystemPreference;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemPreferencesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SystemPreferencesSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->guest = User::factory()->create();
    $this->guest->assignRole('Guest');
});

it('index returns 200 for Admin', function () {
    $this->actingAs($this->admin)
        ->get(route('preferences.index'))
        ->assertOk()
        ->assertSee('System Preferences');
});

it('index returns 403 for Guest', function () {
    $this->actingAs($this->guest)
        ->get(route('preferences.index'))
        ->assertForbidden();
});

it('update saves valid preferences', function () {
    $this->actingAs($this->admin)
        ->post(route('preferences.update'), [
            'app_name'                => 'Test Registry',
            'app_url'                 => 'https://registry.test.example.org',
            'federation_name'         => 'Test Federation',
            'support_email'           => 'support@example.org',
            'mail_from_name'          => 'Test Mailer',
            'mail_from_address'       => 'noreply@example.org',
            'default_saml_role'       => 'Entity Manager',
            'session_timeout_minutes' => '60',
            'max_login_attempts'      => '3',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SystemPreference::get('app_name'))->toBe('Test Registry');
    expect(SystemPreference::get('federation_name'))->toBe('Test Federation');
    expect(SystemPreference::get('default_saml_role'))->toBe('Entity Manager');
    expect(SystemPreference::get('session_timeout_minutes'))->toBe(60);
});

it('update rejects invalid email for support_email', function () {
    $this->actingAs($this->admin)
        ->post(route('preferences.update'), [
            'app_name'          => 'Valid',
            'app_url'           => 'https://example.org',
            'federation_name'   => 'Valid',
            'mail_from_name'    => 'Valid',
            'mail_from_address' => 'valid@example.org',
            'support_email'     => 'not-an-email',
        ])
        ->assertSessionHasErrors('support_email');
});

it('update rejects URL without https', function () {
    $this->actingAs($this->admin)
        ->post(route('preferences.update'), [
            'app_name'          => 'Valid',
            'app_url'           => 'http://insecure.example.org',
            'federation_name'   => 'Valid',
            'mail_from_name'    => 'Valid',
            'mail_from_address' => 'valid@example.org',
        ])
        ->assertSessionHasErrors('app_url');
});

it('update rejects session_timeout below minimum', function () {
    $this->actingAs($this->admin)
        ->post(route('preferences.update'), [
            'app_name'                => 'Valid',
            'app_url'                 => 'https://example.org',
            'federation_name'         => 'Valid',
            'mail_from_name'          => 'Valid',
            'mail_from_address'       => 'valid@example.org',
            'session_timeout_minutes' => '2',
        ])
        ->assertSessionHasErrors('session_timeout_minutes');
});

it('update rejects default_saml_role not in allowed list', function () {
    $this->actingAs($this->admin)
        ->post(route('preferences.update'), [
            'app_name'          => 'Valid',
            'app_url'           => 'https://example.org',
            'federation_name'   => 'Valid',
            'mail_from_name'    => 'Valid',
            'mail_from_address' => 'valid@example.org',
            'default_saml_role' => 'SuperAdmin',
        ])
        ->assertSessionHasErrors('default_saml_role');
});

it('SystemPreference::get returns default when key missing', function () {
    expect(SystemPreference::get('nonexistent_key', 'fallback'))->toBe('fallback');
    expect(SystemPreference::get('nonexistent_key'))->toBeNull();
});
