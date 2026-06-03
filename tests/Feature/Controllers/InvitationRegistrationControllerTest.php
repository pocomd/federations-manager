<?php

declare(strict_types=1);

use App\Models\AppNotification;
use App\Models\Entity;
use App\Models\EntityManager;
use App\Models\Federation;
use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\NotificationTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(NotificationTypesSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Mail::fake();

    $this->inviter    = User::factory()->create();
    $this->federation = Federation::factory()->create();
});

// ── Helper to build a minimal Invitation ──────────────────────────────────────

function makeInvitation(array $overrides = []): Invitation
{
    return Invitation::create(array_merge([
        'email'         => 'invited@example.org',
        'token'         => \Illuminate\Support\Str::random(64),
        'invited_by'    => test()->inviter->id,
        'federation_id' => test()->federation->id,
        'expires_at'    => now()->addHours(72),
    ], $overrides));
}

// ── show() ────────────────────────────────────────────────────────────────────

it('show() returns 200 for a valid pending invitation', function () {
    $invitation = makeInvitation();

    $this->get(route('register.invitation.show', $invitation->token))
        ->assertOk();
});

it('show() renders invitation-error view for an expired invitation', function () {
    $invitation = makeInvitation(['expires_at' => now()->subHour()]);

    $this->get(route('register.invitation.show', $invitation->token))
        ->assertOk()
        ->assertViewIs('auth.invitation-error');
});

it('show() renders invitation-error view for an already-accepted invitation', function () {
    $invitation = makeInvitation(['accepted_at' => now()->subMinutes(5)]);

    $this->get(route('register.invitation.show', $invitation->token))
        ->assertOk()
        ->assertViewIs('auth.invitation-error');
});

it('show() renders invitation-error view for a revoked invitation', function () {
    $invitation = makeInvitation(['revoked_at' => now()->subMinutes(5)]);

    $this->get(route('register.invitation.show', $invitation->token))
        ->assertOk()
        ->assertViewIs('auth.invitation-error');
});

// ── register() ────────────────────────────────────────────────────────────────

it('register() creates a user with Guest role', function () {
    $invitation = makeInvitation();

    $this->post(route('register.invitation.register', $invitation->token), [
        'name'                  => 'New User',
        'password'              => 'secret1234',
        'password_confirmation' => 'secret1234',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'invited@example.org')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('Guest'))->toBeTrue();
    expect($user->name)->toBe('New User');
});

it('register() creates an entity_managers record when entity_id is set', function () {
    $entity     = Entity::factory()->idp()->create();
    $invitation = makeInvitation(['entity_id' => $entity->id]);

    $this->post(route('register.invitation.register', $invitation->token), [
        'name'                  => 'Entity Co-Manager',
        'password'              => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $user = User::where('email', 'invited@example.org')->firstOrFail();

    $manager = EntityManager::where('entity_id', $entity->id)
        ->where('user_id', $user->id)
        ->first();

    expect($manager)->not->toBeNull();
    expect($manager->role)->toBe('manager');
    expect($user->hasRole('Entity Manager'))->toBeTrue();
});

it('register() marks the invitation as accepted', function () {
    $invitation = makeInvitation();

    $this->post(route('register.invitation.register', $invitation->token), [
        'name'                  => 'New User',
        'password'              => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('register() dispatches user_registered notification to admins', function () {
    // Create an admin to receive the notification
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $invitation = makeInvitation();

    $this->post(route('register.invitation.register', $invitation->token), [
        'name'                  => 'Notified User',
        'password'              => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    expect(
        AppNotification::where('user_id', $admin->id)
            ->where('type', 'user_registered')
            ->exists()
    )->toBeTrue();
});
