<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemPreferencesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SystemPreferencesSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('switches locale to ro via GET request', function () {
    $this->get(route('language.switch', 'ro'))
        ->assertRedirect();

    expect(session('app_locale'))->toBe('ro');
});

it('switches locale to en via GET request', function () {
    session(['app_locale' => 'ro']);

    $this->get(route('language.switch', 'en'))
        ->assertRedirect();

    expect(session('app_locale'))->toBe('en');
});

it('rejects unsupported locale', function () {
    $this->get('/language/fr')
        ->assertNotFound();
});

it('persists locale in session after switch', function () {
    $this->get(route('language.switch', 'ro'));

    expect(session('app_locale'))->toBe('ro');
});

it('stores preferred_locale on user when authenticated', function () {
    $this->actingAs($this->admin)
        ->get(route('language.switch', 'ro'));

    expect($this->admin->fresh()->preferred_locale)->toBe('ro');
});

it('SetLocale middleware applies session locale', function () {
    $this->withSession(['app_locale' => 'ro'])
        ->actingAs($this->admin)
        ->get(route('dashboard'));

    expect(App::getLocale())->toBe('ro');
});

it('SetLocale middleware falls back to user profile locale', function () {
    $this->admin->update(['preferred_locale' => 'ro']);

    $this->actingAs($this->admin)
        ->get(route('dashboard'));

    expect(App::getLocale())->toBe('ro');
});

it('SetLocale middleware falls back to default en', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'));

    expect(App::getLocale())->toBe('en');
});
