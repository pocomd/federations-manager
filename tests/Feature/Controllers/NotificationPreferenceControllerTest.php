<?php

declare(strict_types=1);

use App\Models\NotificationType;
use App\Models\User;
use App\Models\UserNotificationPreference;
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

it('index returns 200 with all active notification types', function () {
    $response = $this->actingAs($this->user)
        ->get(route('profile.notifications.index'));

    $response->assertOk();

    $activeCount = NotificationType::where('is_active', true)->count();
    expect($activeCount)->toBe(12);
});

it('update upserts preference rows for authenticated user', function () {
    $type = NotificationType::where('is_active', true)->first();

    $this->actingAs($this->user)
        ->post(route('profile.notifications.update'), [
            "via_ui_{$type->id}"    => '1',
            "via_email_{$type->id}" => '1',
        ])
        ->assertRedirect();

    expect(
        UserNotificationPreference::where('user_id', $this->user->id)
            ->where('notification_type', $type->id)
            ->where('via_ui', true)
            ->exists()
    )->toBeTrue();
});

it('update only affects the authenticated users own preferences', function () {
    $other = User::factory()->create();
    $other->assignRole('Guest');

    $type = NotificationType::where('is_active', true)->first();

    $this->actingAs($this->user)
        ->post(route('profile.notifications.update'), [
            "via_ui_{$type->id}" => '1',
        ])
        ->assertRedirect();

    expect(
        UserNotificationPreference::where('user_id', $other->id)->exists()
    )->toBeFalse();
});
