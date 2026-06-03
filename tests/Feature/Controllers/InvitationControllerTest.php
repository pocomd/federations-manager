<?php

declare(strict_types=1);

use App\Models\Federation;
use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Mail::fake();

    $this->admin     = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->federation = Federation::factory()->create();

    $this->withoutVite();
});

function makeTestInvitation(array $overrides = []): Invitation
{
    $inviter = test()->admin;
    return Invitation::create(array_merge([
        'email'         => 'test-inv@example.org',
        'token'         => Str::random(64),
        'invited_by'    => $inviter->id,
        'federation_id' => test()->federation->id,
        'expires_at'    => now()->addHours(72),
    ], $overrides));
}

// ── index ─────────────────────────────────────────────────────────────────────

it('index returns 200 for Admin', function () {
    $this->actingAs($this->admin)
        ->get(route('invitations.index'))
        ->assertOk();
});

it('FM sees only their own federations invitations', function () {
    $fm = User::factory()->create();
    $fm->assignRole('Federation Manager');

    $otherFederation = Federation::factory()->create();
    $fm->managedFederations()->attach($this->federation->id);

    $myInvitation    = makeTestInvitation(['email' => 'mine@example.org']);
    $otherInvitation = makeTestInvitation([
        'email'         => 'other@example.org',
        'federation_id' => $otherFederation->id,
    ]);

    $response = $this->actingAs($fm)
        ->get(route('invitations.index'))
        ->assertOk();

    $response->assertSee('mine@example.org');
    $response->assertDontSee('other@example.org');
});

// ── store ─────────────────────────────────────────────────────────────────────

it('store creates an invitation record', function () {
    $this->actingAs($this->admin)
        ->post(route('invitations.store'), [
            'email'         => 'newuser@example.org',
            'federation_id' => $this->federation->id,
            'entity_id'     => null,
        ])
        ->assertRedirect(route('invitations.index'));

    expect(
        Invitation::where('email', 'newuser@example.org')
            ->where('federation_id', $this->federation->id)
            ->exists()
    )->toBeTrue();
});

// ── revoke ────────────────────────────────────────────────────────────────────

it('revoke sets revoked_at', function () {
    $invitation = makeTestInvitation();

    $this->actingAs($this->admin)
        ->delete(route('invitations.revoke', $invitation))
        ->assertRedirect();

    expect($invitation->fresh()->revoked_at)->not->toBeNull();
});

// ── reissue ───────────────────────────────────────────────────────────────────

it('reissue pending invitation requires no comment and creates new token', function () {
    $invitation  = makeTestInvitation();
    $oldToken    = $invitation->token;
    $invitationId = $invitation->id;

    $this->actingAs($this->admin)
        ->post(route('invitations.reissue', $invitation))
        ->assertRedirect();

    // Old invitation deleted
    expect(Invitation::find($invitationId))->toBeNull();

    // New invitation with previous_token set
    $new = Invitation::where('email', 'test-inv@example.org')
        ->where('previous_token', $oldToken)
        ->first();

    expect($new)->not->toBeNull();
    expect($new->token)->not->toBe($oldToken);
});

it('reissue revoked invitation requires a comment', function () {
    $invitation = makeTestInvitation([
        'revoked_at' => now()->subMinutes(10),
        'revoked_by' => $this->admin->id,
    ]);

    // Without comment → validation error
    $this->actingAs($this->admin)
        ->post(route('invitations.reissue', $invitation))
        ->assertSessionHasErrors('reissue_comment');
});
