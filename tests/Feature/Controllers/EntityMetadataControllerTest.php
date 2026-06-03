<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = User::factory()->create();
    $this->user->assignRole('Admin');
});

// ── validate ───────────────────────────────────────────────────────────────────

it('validate returns a structured JSON result', function () {
    // factory afterCreating() creates uiInfo, contacts, and endpoints automatically
    $entity = Entity::factory()->idp()->create([
        'entity_id' => 'https://idp.test.example.org/saml2/idp',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('entities.validate', $entity))
        ->assertOk()
        ->assertJsonStructure([
            'entity_id',
            'entity_type',
            'passed',
            'checked_at',
            'summary' => ['total', 'passed', 'errors', 'warnings'],
            'errors',
            'warnings',
            'checks',
        ]);
});

it('validate result contains entity_id matching the queried entity', function () {
    $entity = Entity::factory()->sp()->create([
        'entity_id' => 'https://sp.test.example.org/saml2/metadata',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('entities.validate', $entity))
        ->assertOk()
        ->json();

    expect($response['entity_id'])->toBe('https://sp.test.example.org/saml2/metadata');
    expect($response['entity_type'])->toBe('sp');
});

it('validate returns passed=false for an entity missing required fields', function () {
    // Create a valid IdP, then delete its endpoints — C1 (no cert) + S3 (no SSO) will fail
    $entity = Entity::factory()->idp()->create();
    $entity->endpoints()->delete();
    // Also no certificate → C2/C3/C4/C5 produce 'fail' status → passed() returns false

    $result = $this->actingAs($this->user)
        ->getJson(route('entities.validate', $entity))
        ->assertOk()
        ->json();

    expect($result['passed'])->toBeFalse();
    expect($result['summary']['errors'])->toBeGreaterThan(0);
});

it('validate checks array contains at least the structural checks', function () {
    $entity = Entity::factory()->create();

    $result = $this->actingAs($this->user)
        ->getJson(route('entities.validate', $entity))
        ->assertOk()
        ->json();

    $checkCodes = collect($result['checks'])->pluck('code')->toArray();

    expect($checkCodes)->toContain('S1')
        ->toContain('S3')
        ->toContain('C1')
        ->toContain('R1');
});

it('validate requires authentication', function () {
    $entity = Entity::factory()->create();

    $this->getJson(route('entities.validate', $entity))
        ->assertUnauthorized();
});

// ── rawXml ─────────────────────────────────────────────────────────────────────

it('metadata xml endpoint returns XML content type', function () {
    $entity = Entity::factory()->idp()->create();

    $this->actingAs($this->user)
        ->get(route('entities.metadata', $entity))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8');
});

// ── health check ───────────────────────────────────────────────────────────────

it('/up health endpoint returns 200 without authentication', function () {
    $this->get('/up')->assertOk();
});

// ── MDQ endpoint ───────────────────────────────────────────────────────────────

it('MDQ endpoint returns XML for an active entity looked up by SHA-1 hash', function () {
    // Swap in a no-op signer — xmlsectool is not available in the test environment.
    $this->app->bind(\App\Services\Metadata\XmlsectoolSigner::class, function () {
        return new class extends \App\Services\Metadata\XmlsectoolSigner {
            public function sign(string $xml): string
            {
                return $xml; // Return unsigned XML as-is
            }
        };
    });

    $entity = Entity::factory()->idp()->create(['status' => 'active']);
    $hash   = sha1($entity->entity_id);

    $this->get(route('api.mdq', ['hash' => $hash]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/samlmetadata+xml');
});

it('MDQ endpoint returns 404 for an unknown entity hash', function () {
    $this->get(route('api.mdq', ['hash' => sha1('https://unknown.entity.example.org/saml2')]))
        ->assertNotFound();
});

// ── OIDC rule filtering ────────────────────────────────────────────────────────

it('validate returns 200 for SAML IdP entity and OIDC rules are not in checks', function () {
    $entity = Entity::factory()->idp()->create([
        'entity_id' => 'https://idp.oidctest.example.org/saml2',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('entities.validate', $entity))
        ->assertOk();

    $codes = collect($response->json('checks'))->pluck('code')->toArray();

    expect($codes)->not->toContain('O01');
    expect($codes)->not->toContain('O02');
    expect($codes)->not->toContain('O03');
});
