<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\FakeSamlService;
use App\Services\Auth\SamlServiceInterface;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();
});

// ── Unauthenticated access ──────────────────────────────────────────────────────

it('unauthenticated request to /entities redirects to /login', function () {
    // No FakeSamlService bound — EnsureAuthenticated finds neither Laravel nor SAML session
    $this->get(route('entities.index'))
        ->assertRedirect(route('login'));
});

// ── Authenticated access ────────────────────────────────────────────────────────

it('authenticated user can access /entities', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    // actingAs() sets the Laravel guard → EnsureAuthenticated passes via Auth::check()
    $this->actingAs($user)
        ->get(route('entities.index'))
        ->assertOk();
});

// ── Login page ──────────────────────────────────────────────────────────────────

it('login page shows both SAML button and local form', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Login with institutional account')
        ->assertSee('Sign in');
});

// ── SAML: new user gets Guest role ──────────────────────────────────────────────

it('new SAML user is created with Guest role on first login', function () {
    // Bind a fake SAML service that reports an authenticated session
    app()->bind(SamlServiceInterface::class, fn () => new FakeSamlService([
        'mail'        => ['newoperator@university.ie'],
        'displayName' => ['New Operator'],
    ]));

    $this->post(route('saml.acs'))
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'newoperator@university.ie')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('Guest'))->toBeTrue();
});

// ── SAML: existing user role is preserved ───────────────────────────────────────

it('existing user role is preserved on subsequent SAML login', function () {
    // Create user with Admin role before first SAML login
    $existing = User::factory()->create(['email' => 'admin@university.ie']);
    $existing->assignRole('Admin');

    app()->bind(SamlServiceInterface::class, fn () => new FakeSamlService([
        'mail'        => ['admin@university.ie'],
        'displayName' => ['Existing Admin'],
    ]));

    $this->post(route('saml.acs'))
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'admin@university.ie')->first();

    // Role should still be Admin — not downgraded to Guest
    expect($user->hasRole('Admin'))->toBeTrue();
    expect($user->hasRole('Guest'))->toBeFalse();
});
