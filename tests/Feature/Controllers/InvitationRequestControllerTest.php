<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\EntityManager;
use App\Models\Federation;
use App\Models\InvitationRequest;
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

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->em = User::factory()->create();
    $this->em->assignRole('Entity Manager');

    $this->federation = Federation::factory()->create();
    $this->entity     = Entity::factory()->idp()->create();

    $this->withoutVite();
});

// ── store ─────────────────────────────────────────────────────────────────────

it('store creates a pending invitation request', function () {
    $this->actingAs($this->em)
        ->post(route('invitation-requests.store', $this->entity), [
            'contact_email' => 'contact@example.org',
            'contact_type'  => 'technical',
            'contact_name'  => 'Tech Contact',
            'federation_id' => $this->federation->id,
        ])
        ->assertRedirect();

    expect(
        InvitationRequest::where('entity_id', $this->entity->id)
            ->where('contact_email', 'contact@example.org')
            ->where('status', 'pending')
            ->exists()
    )->toBeTrue();
});

it('store prevents duplicate pending request for same contact', function () {
    InvitationRequest::create([
        'entity_id'     => $this->entity->id,
        'contact_email' => 'contact@example.org',
        'contact_type'  => 'technical',
        'requested_by'  => $this->em->id,
        'federation_id' => $this->federation->id,
        'status'        => 'pending',
    ]);

    $this->actingAs($this->em)
        ->post(route('invitation-requests.store', $this->entity), [
            'contact_email' => 'contact@example.org',
            'contact_type'  => 'technical',
            'federation_id' => $this->federation->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(
        InvitationRequest::where('entity_id', $this->entity->id)
            ->where('contact_email', 'contact@example.org')
            ->where('status', 'pending')
            ->count()
    )->toBe(1);
});

// ── approve ───────────────────────────────────────────────────────────────────

it('approve creates an invitation when email is not in system', function () {
    $invRequest = InvitationRequest::create([
        'entity_id'     => $this->entity->id,
        'contact_email' => 'newcontact@example.org',
        'contact_type'  => 'technical',
        'requested_by'  => $this->em->id,
        'federation_id' => $this->federation->id,
        'status'        => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->patch(route('invitation-requests.approve', $invRequest))
        ->assertRedirect();

    expect(
        Invitation::where('email', 'newcontact@example.org')
            ->where('invitation_request_id', $invRequest->id)
            ->exists()
    )->toBeTrue();

    expect($invRequest->fresh()->status)->toBe('approved');
});

it('approve creates entity_managers record when email is already in system', function () {
    $existingUser = User::factory()->create(['email' => 'existinguser@example.org']);
    $existingUser->assignRole('Guest');

    $invRequest = InvitationRequest::create([
        'entity_id'     => $this->entity->id,
        'contact_email' => 'existinguser@example.org',
        'contact_type'  => 'administrative',
        'requested_by'  => $this->em->id,
        'federation_id' => $this->federation->id,
        'status'        => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->patch(route('invitation-requests.approve', $invRequest))
        ->assertRedirect();

    expect(
        EntityManager::where('entity_id', $this->entity->id)
            ->where('user_id', $existingUser->id)
            ->exists()
    )->toBeTrue();

    expect($invRequest->fresh()->status)->toBe('approved');
});

// ── reject ────────────────────────────────────────────────────────────────────

it('reject sets status to rejected with fm_note', function () {
    $invRequest = InvitationRequest::create([
        'entity_id'     => $this->entity->id,
        'contact_email' => 'contact@example.org',
        'contact_type'  => 'support',
        'requested_by'  => $this->em->id,
        'federation_id' => $this->federation->id,
        'status'        => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->patch(route('invitation-requests.reject', $invRequest), [
            'fm_note' => 'Contact is not eligible.',
        ])
        ->assertRedirect();

    $fresh = $invRequest->fresh();
    expect($fresh->status)->toBe('rejected');
    expect($fresh->fm_note)->toBe('Contact is not eligible.');
    expect($fresh->reviewed_by)->toBe($this->admin->id);
});
