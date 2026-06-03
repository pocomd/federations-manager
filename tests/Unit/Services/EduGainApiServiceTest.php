<?php

declare(strict_types=1);

use App\Services\EduGain\EduGainApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->service = new EduGainApiService();
});

// ── getEccsStatus ─────────────────────────────────────────────────────────────

it('getEccsStatus returns not_in_eccs when entity not in list', function () {
    Http::fake(['*' => Http::response([
        ['entityID' => 'https://other.idp.org', 'status' => 'OK'],
    ], 200)]);

    $result = $this->service->getEccsStatus('https://idp.example.org');

    expect($result['status'])->toBe('not_in_eccs');
});

it('getEccsStatus returns OK status when entity found', function () {
    Http::fake(['*' => Http::response([
        [
            'entityID'    => 'https://idp.example.org',
            'status'      => 'OK',
            'displayname' => 'Example IdP',
            'Location'    => 'https://idp.example.org/sso',
        ],
    ], 200)]);

    $result = $this->service->getEccsStatus('https://idp.example.org');

    expect($result['status'])->toBe('OK')
        ->and($result['entity_id'])->toBe('https://idp.example.org');
});

it('getEccsStatus returns unknown when API unreachable', function () {
    Http::fake(['*' => Http::response('', 503)]);

    $result = $this->service->getEccsStatus('https://idp.example.org');

    expect($result['status'])->toBe('unknown');
});

// ── getEntityPresence ─────────────────────────────────────────────────────────

it('getEntityPresence returns present=false when not found', function () {
    Http::fake(['*' => Http::response(['error' => 'not found'], 200)]);

    $result = $this->service->getEntityPresence('https://sp.example.org');

    expect($result['present'])->toBeFalse();
});

it('getEntityPresence returns present=true when found', function () {
    Http::fake(['*' => Http::response([
        'registrationAuthority' => 'https://www.heanet.ie/',
        'federations'           => ['LEAF'],
    ], 200)]);

    $result = $this->service->getEntityPresence('https://sp.example.org');

    expect($result['present'])->toBeTrue()
        ->and($result['registration_authority'])->toBe('https://www.heanet.ie/');
});

// ── getFederationStatus ───────────────────────────────────────────────────────

it('getFederationStatus returns data for valid federation code', function () {
    Http::fake(['*' => Http::response([
        'entitycount' => '150',
        'idpcount'    => '80',
        'spcount'     => '70',
    ], 200)]);

    $result = $this->service->getFederationStatus('LEAF');

    expect($result)->toHaveKey('entitycount');
});

// ── caching ───────────────────────────────────────────────────────────────────

it('results are cached — second call does not hit API', function () {
    Http::fake(['*' => Http::response([
        ['entityID' => 'https://idp.example.org', 'status' => 'OK'],
    ], 200)]);

    $this->service->getEccsStatus('https://idp.example.org');
    $this->service->getEccsStatus('https://idp.example.org');

    Http::assertSentCount(1);
});
