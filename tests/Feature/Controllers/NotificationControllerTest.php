<?php

declare(strict_types=1);

use App\Models\AppNotification;
use App\Models\NotificationArchive;
use App\Models\NotificationType;
use App\Models\User;
use Database\Seeders\NotificationTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(NotificationTypesSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = User::factory()->create();
    $this->user->assignRole('Admin');

    $this->withoutVite();
});

it('index returns 200 with the user notifications', function () {
    AppNotification::create([
        'user_id' => $this->user->id,
        'type'    => 'entity_approved',
        'title'   => 'Test notification',
        'body'    => 'Test body',
    ]);

    $this->actingAs($this->user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Test notification');
});

it('markRead sets read_at on the notification', function () {
    $notification = AppNotification::create([
        'user_id' => $this->user->id,
        'type'    => 'entity_approved',
        'title'   => 'Unread',
        'body'    => 'Body',
    ]);

    $this->actingAs($this->user)
        ->patch(route('notifications.read', $notification))
        ->assertRedirect();

    expect(AppNotification::find($notification->id)?->read_at)->not->toBeNull();
});

it('markAllRead clears all unread notifications', function () {
    AppNotification::create(['user_id' => $this->user->id, 'type' => 'entity_approved', 'title' => 'A', 'body' => 'B']);
    AppNotification::create(['user_id' => $this->user->id, 'type' => 'entity_rejected', 'title' => 'C', 'body' => 'D']);

    $this->actingAs($this->user)
        ->post(route('notifications.read-all'))
        ->assertRedirect();

    $unread = AppNotification::where('user_id', $this->user->id)->whereNull('read_at')->count();
    expect($unread)->toBe(0);
});

it('archiveNotification moves notification to notification_archive table', function () {
    $notification = AppNotification::create([
        'user_id' => $this->user->id,
        'type'    => 'entity_approved',
        'title'   => 'To archive',
        'body'    => 'Archive body',
    ]);

    $id = $notification->id;

    $this->actingAs($this->user)
        ->delete(route('notifications.destroy', $notification))
        ->assertRedirect();

    expect(AppNotification::find($id))->toBeNull();
    expect(NotificationArchive::find($id))->not->toBeNull();
});

it('user cannot mark another users notification as read', function () {
    $other        = User::factory()->create();
    $notification = AppNotification::create([
        'user_id' => $other->id,
        'type'    => 'entity_approved',
        'title'   => 'Other notification',
        'body'    => 'Body',
    ]);

    $this->actingAs($this->user)
        ->patch(route('notifications.read', $notification))
        ->assertForbidden();
});
