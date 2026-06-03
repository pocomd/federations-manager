<?php

declare(strict_types=1);

use App\Models\Entity;
use App\Models\Federation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('invalidates federation metadata cache when entity is saved', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create(['status' => 'active']);

    $federation->entities()->attach($entity->id, ['status' => 'active']);

    $cacheKey = "federation_metadata:{$federation->id}";
    Cache::put($cacheKey, '<EntitiesDescriptor/>', now()->addHours(6));

    expect(Cache::has($cacheKey))->toBeTrue();

    $entity->update(['status' => 'suspended']);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('invalidates federation metadata cache when entity is deleted', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create();

    $federation->entities()->attach($entity->id, ['status' => 'active']);

    $cacheKey = "federation_metadata:{$federation->id}";
    Cache::put($cacheKey, '<EntitiesDescriptor/>', now()->addHours(6));

    $entity->delete();

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('invalidates edugain metadata cache when entity is saved', function () {
    $federation = Federation::factory()->create();
    $entity     = Entity::factory()->create(['status' => 'active', 'edugain' => true]);

    $federation->entities()->attach($entity->id, ['status' => 'active']);

    $eduGainKey = "federation_edugain_metadata:{$federation->id}";
    Cache::put($eduGainKey, '<EntitiesDescriptor/>', now()->addHours(6));

    $entity->update(['status' => 'suspended']);

    expect(Cache::has($eduGainKey))->toBeFalse();
});
