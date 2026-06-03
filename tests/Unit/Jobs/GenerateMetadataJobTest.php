<?php

declare(strict_types=1);

use App\Jobs\GenerateMetadataJob;
use App\Models\Federation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('GenerateMetadataJob XML contains a validUntil attribute on EntitiesDescriptor', function () {
    $federation = Federation::factory()->create(['status' => 'active']);

    GenerateMetadataJob::dispatchSync($federation->id);

    $xml = Cache::get("federation_metadata:{$federation->id}");

    expect($xml)->not->toBeNull();

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadXML($xml);
    libxml_clear_errors();
    libxml_use_internal_errors(false);

    $root = $dom->documentElement;
    expect($root->localName)->toBe('EntitiesDescriptor');
    expect($root->hasAttribute('validUntil'))->toBeTrue();

    // Must look like an ISO-8601 / Atom date: 2026-05-06T...
    expect($root->getAttribute('validUntil'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});

it('GenerateMetadataJob caches the metadata with the configured hours', function () {
    $federation = Federation::factory()->create(['status' => 'active']);

    GenerateMetadataJob::dispatchSync($federation->id);

    expect(Cache::has("federation_metadata:{$federation->id}"))->toBeTrue();
});
