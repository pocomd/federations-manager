<?php

declare(strict_types=1);

use App\Jobs\SendFederationMailJob;
use App\Models\Federation;
use App\Models\MailLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->guest = User::factory()->create();
    $this->guest->assignRole('Guest');

    $this->federation = Federation::factory()->create([
        'name'   => 'Test Federation',
        'status' => 'active',
    ]);
});

it('compose page returns 200 for Admin', function () {
    $this->actingAs($this->admin)
        ->get(route('federations.mail.compose', $this->federation))
        ->assertOk()
        ->assertSee('Send Email');
});

it('send dispatches SendFederationMailJob', function () {
    Queue::fake();

    $this->actingAs($this->admin)
        ->post(route('federations.mail.send', $this->federation), [
            'subject'       => 'Test Subject',
            'body'          => 'Test Body',
            'entity_type'   => 'all',
            'contact_types' => ['technical'],
        ])
        ->assertRedirect(route('federations.show', $this->federation))
        ->assertSessionHas('success');

    Queue::assertPushed(SendFederationMailJob::class);
});

it('send validates required fields', function () {
    $this->actingAs($this->admin)
        ->post(route('federations.mail.send', $this->federation), [])
        ->assertSessionHasErrors(['subject', 'body', 'entity_type', 'contact_types']);
});

it('send requires at least one contact type', function () {
    $this->actingAs($this->admin)
        ->post(route('federations.mail.send', $this->federation), [
            'subject'       => 'Test Subject',
            'body'          => 'Test Body',
            'entity_type'   => 'all',
            'contact_types' => [],
        ])
        ->assertSessionHasErrors('contact_types');
});

it('mail log returns 200 for Admin', function () {
    $this->actingAs($this->admin)
        ->get(route('federations.mail.log', $this->federation))
        ->assertOk()
        ->assertSee('Mail Log');
});
