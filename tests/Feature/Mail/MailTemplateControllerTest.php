<?php

declare(strict_types=1);

use App\Models\MailTemplate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->guest = User::factory()->create();
    $this->guest->assignRole('Guest');
});

it('index returns 200 for Admin', function () {
    $this->actingAs($this->admin)
        ->get(route('mail.templates.index'))
        ->assertOk()
        ->assertSee('Mail Templates');
});

it('index returns 403 for Guest', function () {
    $this->actingAs($this->guest)
        ->get(route('mail.templates.index'))
        ->assertForbidden();
});

it('store creates template', function () {
    $this->actingAs($this->admin)
        ->post(route('mail.templates.store'), [
            'name'      => 'Welcome email',
            'group'     => 'general',
            'subject'   => 'Welcome to [[federation_name]]',
            'body'      => 'Dear [[contact_name]],',
            'lang'      => 'en',
            'is_active' => '1',
        ])
        ->assertRedirect(route('mail.templates.index'))
        ->assertSessionHas('success');

    expect(MailTemplate::where('name', 'Welcome email')->exists())->toBeTrue();
});

it('store rejects invalid group', function () {
    $this->actingAs($this->admin)
        ->post(route('mail.templates.store'), [
            'name'    => 'Bad group',
            'group'   => 'nonexistent_group',
            'subject' => 'Test',
            'body'    => 'Body',
            'lang'    => 'en',
        ])
        ->assertSessionHasErrors('group');
});

it('destroy deletes template', function () {
    $template = MailTemplate::create([
        'name'      => 'To delete',
        'group'     => 'general',
        'subject'   => 'Subject',
        'body'      => 'Body',
        'lang'      => 'en',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('mail.templates.destroy', $template))
        ->assertRedirect(route('mail.templates.index'))
        ->assertSessionHas('success');

    expect(MailTemplate::find($template->id))->toBeNull();
});
