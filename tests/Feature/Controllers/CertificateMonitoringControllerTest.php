<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\EntityCertificate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = User::factory()->create();
    $this->user->assignRole('Admin');
});

// ── index ──────────────────────────────────────────────────────────────────────

it('index returns dashboard data structure as JSON', function () {
    $this->actingAs($this->user)
        ->getJson(route('certificates.monitor'))
        ->assertOk()
        ->assertJsonStructure([
            'summary' => ['expired', 'critical', 'warning', 'advisory', 'healthy', 'checked_at'],
            'expired',
            'critical',
            'warning',
            'advisory',
        ]);
});

it('index summary counts are zero when no entities exist', function () {
    $result = $this->actingAs($this->user)
        ->getJson(route('certificates.monitor'))
        ->assertOk()
        ->json('summary');

    expect($result['expired'])->toBe(0);
    expect($result['critical'])->toBe(0);
    expect($result['warning'])->toBe(0);
});

it('index requires authentication', function () {
    $this->getJson(route('certificates.monitor'))
        ->assertUnauthorized();
});

// ── entityCertificates ─────────────────────────────────────────────────────────

it('entityCertificates returns certificate list for a given entity', function () {
    $entity = Entity::factory()->create(['status' => 'active']);
    EntityCertificate::factory()->create(['entity_id' => $entity->id]);

    $response = $this->actingAs($this->user)
        ->getJson(route('entities.certificates', $entity))
        ->assertOk()
        ->json();

    expect($response['entity_id'])->toBe($entity->entity_id);
    expect($response['certificates'])->toHaveCount(1);
});

it('entityCertificates response includes severity and days_remaining', function () {
    $entity = Entity::factory()->create(['status' => 'active']);
    EntityCertificate::factory()->create([
        'entity_id' => $entity->id,
        'not_after' => CarbonImmutable::now()->addDays(20),
    ]);

    $cert = $this->actingAs($this->user)
        ->getJson(route('entities.certificates', $entity))
        ->assertOk()
        ->json('certificates.0');

    expect($cert)->toHaveKey('severity');
    expect($cert)->toHaveKey('days_remaining');
    expect($cert['severity'])->toBe('warning'); // 20 days → warning (≤30, >14)
});

// ── dashboard reflects expiring certs ──────────────────────────────────────────

it('index dashboard shows expired cert when one exists for an active entity', function () {
    $entity = Entity::factory()->create(['status' => 'active']);
    EntityCertificate::factory()->create([
        'entity_id' => $entity->id,
        'not_after' => CarbonImmutable::now()->subDay(),
    ]);

    $summary = $this->actingAs($this->user)
        ->getJson(route('certificates.monitor'))
        ->assertOk()
        ->json('summary');

    expect($summary['expired'])->toBeGreaterThanOrEqual(1);
});

// ── severity bucket correctness ────────────────────────────────────────────────

it('dashboard counts one cert per severity bucket correctly', function () {
    $entity = Entity::factory()->create(['status' => 'active']);

    // expired (< 0 days)
    EntityCertificate::factory()->create(['entity_id' => $entity->id, 'not_after' => CarbonImmutable::now()->subDays(1)]);
    // critical (≤ 14 days)
    EntityCertificate::factory()->create(['entity_id' => $entity->id, 'not_after' => CarbonImmutable::now()->addDays(7)]);
    // warning (≤ 30 days)
    EntityCertificate::factory()->create(['entity_id' => $entity->id, 'not_after' => CarbonImmutable::now()->addDays(20)]);
    // advisory (≤ 60 days)
    EntityCertificate::factory()->create(['entity_id' => $entity->id, 'not_after' => CarbonImmutable::now()->addDays(45)]);

    $summary = $this->actingAs($this->user)
        ->getJson(route('certificates.monitor'))
        ->assertOk()
        ->json('summary');

    expect($summary['expired'])->toBeGreaterThanOrEqual(1);
    expect($summary['critical'])->toBeGreaterThanOrEqual(1);
    expect($summary['warning'])->toBeGreaterThanOrEqual(1);
    expect($summary['advisory'])->toBeGreaterThanOrEqual(1);
});

// ── entityCertificates severity bucket placement ───────────────────────────────

it('a cert expiring in 7 days appears in the critical bucket', function () {
    $entity = Entity::factory()->create(['status' => 'active']);
    EntityCertificate::factory()->create([
        'entity_id' => $entity->id,
        'not_after' => CarbonImmutable::now()->addDays(7),
    ]);

    $cert = $this->actingAs($this->user)
        ->getJson(route('entities.certificates', $entity))
        ->assertOk()
        ->json('certificates.0');

    expect($cert['severity'])->toBe('critical');
    expect($cert['days_remaining'])->toBeLessThanOrEqual(14);
});

it('a cert expiring in 45 days appears in the advisory bucket', function () {
    $entity = Entity::factory()->create(['status' => 'active']);
    EntityCertificate::factory()->create([
        'entity_id' => $entity->id,
        'not_after' => CarbonImmutable::now()->addDays(45),
    ]);

    $cert = $this->actingAs($this->user)
        ->getJson(route('entities.certificates', $entity))
        ->assertOk()
        ->json('certificates.0');

    expect($cert['severity'])->toBe('advisory');
});

it('a cert expiring in more than 90 days is healthy', function () {
    $entity = Entity::factory()->create(['status' => 'active']);
    EntityCertificate::factory()->create([
        'entity_id' => $entity->id,
        // Use 100 days — well above the 90-day info/healthy boundary,
        // so MySQL TIMESTAMP second-precision truncation cannot flip it.
        'not_after' => CarbonImmutable::now()->addDays(100),
    ]);

    $cert = $this->actingAs($this->user)
        ->getJson(route('entities.certificates', $entity))
        ->assertOk()
        ->json('certificates.0');

    expect($cert['severity'])->toBe('healthy');
});
