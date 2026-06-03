<?php

declare(strict_types=1);

use App\Models\AppNotification;
use App\Models\Entity;
use App\Models\EntityManager;
use App\Models\Federation;
use App\Models\NotificationType;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Notification\NotificationService;
use Database\Seeders\NotificationTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(NotificationTypesSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->service = app(NotificationService::class);
});

it('dispatch creates AppNotification for entity owner when notify_submitter=true', function () {
    $owner  = User::factory()->create();
    $entity = Entity::factory()->create();

    EntityManager::create([
        'entity_id' => $entity->id,
        'user_id'   => $owner->id,
        'role'      => 'owner',
        'added_by'  => $owner->id,
        'added_at'  => now(),
    ]);

    NotificationType::where('id', 'entity_approved')
        ->update(['notify_submitter' => true, 'notify_admins' => false, 'notify_federation_managers' => false]);

    $this->service->dispatch('entity_approved', ['entity_name' => $entity->entity_id], $entity);

    expect(AppNotification::where('user_id', $owner->id)->where('type', 'entity_approved')->exists())
        ->toBeTrue();
});

it('dispatch deduplicates recipients when Admin is also a federation manager', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $federation = Federation::factory()->create();
    $federation->managers()->attach($admin->id, ['assigned_by' => $admin->id, 'assigned_at' => now()]);

    NotificationType::where('id', 'metadata_generated')
        ->update(['notify_admins' => true, 'notify_federation_managers' => true, 'notify_submitter' => false]);

    $this->service->dispatch('metadata_generated', ['federation_name' => $federation->name], null, $federation);

    $count = AppNotification::where('user_id', $admin->id)->where('type', 'metadata_generated')->count();
    expect($count)->toBe(1);
});

it('dispatch respects user preference via_ui=false and skips notification row', function () {
    $owner  = User::factory()->create();
    $entity = Entity::factory()->create();

    EntityManager::create([
        'entity_id' => $entity->id,
        'user_id'   => $owner->id,
        'role'      => 'owner',
        'added_by'  => $owner->id,
        'added_at'  => now(),
    ]);

    NotificationType::where('id', 'entity_approved')
        ->update(['notify_submitter' => true, 'notify_admins' => false, 'notify_federation_managers' => false]);

    UserNotificationPreference::create([
        'user_id'           => $owner->id,
        'notification_type' => 'entity_approved',
        'via_ui'            => false,
        'via_email'         => false,
    ]);

    $this->service->dispatch('entity_approved', ['entity_name' => $entity->entity_id], $entity);

    expect(AppNotification::where('user_id', $owner->id)->where('type', 'entity_approved')->exists())
        ->toBeFalse();
});

it('dispatch skips inactive notification type', function () {
    $owner  = User::factory()->create();
    $entity = Entity::factory()->create();

    EntityManager::create([
        'entity_id' => $entity->id,
        'user_id'   => $owner->id,
        'role'      => 'owner',
        'added_by'  => $owner->id,
        'added_at'  => now(),
    ]);

    NotificationType::where('id', 'entity_approved')->update(['is_active' => false]);

    $this->service->dispatch('entity_approved', [], $entity);

    expect(AppNotification::where('user_id', $owner->id)->exists())->toBeFalse();
});
