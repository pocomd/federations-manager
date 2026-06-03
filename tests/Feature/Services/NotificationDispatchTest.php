<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\EntityManager;
use App\Models\Federation;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Database\Seeders\NotificationTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(NotificationTypesSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->withoutVite();
});

it('approveEntity dispatches entity_approved notification', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create(['status' => 'pending']);

    $entity->federations()->attach($federation->id, [
        'status'      => 'pending',
        'approved_by' => null,
        'approved_at' => null,
    ]);

    $dispatched = [];
    $mock = Mockery::mock(NotificationService::class);
    $mock->shouldReceive('dispatch')
        ->once()
        ->with('entity_approved', Mockery::any(), Mockery::type(Entity::class), Mockery::type(Federation::class))
        ->andReturnUsing(function ($type) use (&$dispatched) {
            $dispatched[] = $type;
        });

    app()->instance(NotificationService::class, $mock);

    $this->actingAs($this->admin)
        ->patch(route('federations.entities.approve', [$federation, $entity]))
        ->assertRedirect();

    expect($dispatched)->toContain('entity_approved');
});

it('rejectEntity dispatches entity_rejected notification', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create(['status' => 'pending']);

    $entity->federations()->attach($federation->id, [
        'status'      => 'pending',
        'approved_by' => null,
        'approved_at' => null,
    ]);

    $dispatched = [];
    $mock = Mockery::mock(NotificationService::class);
    $mock->shouldReceive('dispatch')
        ->once()
        ->with('entity_rejected', Mockery::any(), Mockery::type(Entity::class), Mockery::type(Federation::class))
        ->andReturnUsing(function ($type) use (&$dispatched) {
            $dispatched[] = $type;
        });

    app()->instance(NotificationService::class, $mock);

    $this->actingAs($this->admin)
        ->patch(route('federations.entities.reject', [$federation, $entity]))
        ->assertRedirect();

    expect($dispatched)->toContain('entity_rejected');
});

it('new user creation via SAML dispatches user_registered notification', function () {
    $dispatched = [];
    $mock = Mockery::mock(NotificationService::class);
    $mock->shouldReceive('dispatch')
        ->once()
        ->with('user_registered', Mockery::any())
        ->andReturnUsing(function ($type) use (&$dispatched) {
            $dispatched[] = $type;
        });

    app()->instance(NotificationService::class, $mock);

    $controller = app(\App\Http\Controllers\Auth\SamlAuthController::class);
    $controller->findOrCreateUser([
        'mail'        => ['testuser@example.org'],
        'displayName' => ['Test User'],
    ]);

    expect($dispatched)->toContain('user_registered');
});

it('CheckCertificateExpiryJob dispatches certificate_expiring notification', function () {
    $this->seed(\Database\Seeders\SchedulerSettingsSeeder::class);
    \Illuminate\Support\Facades\Mail::fake();

    $entity = Entity::factory()->create(['status' => 'active']);

    \App\Models\EntityCertificate::factory()->expiringSoon(10)->create(['entity_id' => $entity->id]);

    $owner = User::factory()->create();
    EntityManager::create([
        'entity_id' => $entity->id,
        'user_id'   => $owner->id,
        'role'      => 'owner',
        'added_by'  => $owner->id,
        'added_at'  => now(),
    ]);

    \App\Jobs\CheckCertificateExpiryJob::dispatchSync();

    expect(\App\Models\AppNotification::where('user_id', $owner->id)
        ->where('type', 'certificate_expiring')
        ->exists()
    )->toBeTrue();
});
