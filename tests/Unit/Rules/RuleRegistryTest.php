<?php

declare(strict_types=1);

use App\Services\Metadata\RuleRegistry;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use Illuminate\Support\Facades\Cache;

test('registry discovers all 31 rule classes', function () {
    Cache::flush();

    $registry = new RuleRegistry(
        rulesPath:      app_path('Services/Metadata/Rules'),
        rulesNamespace: 'App\\Services\\Metadata\\Rules',
    );

    $rules = $registry->all();

    expect($rules)->toHaveCount(34)
        ->and($rules)->each->toBeInstanceOf(MetadataRule::class);
});

test('registry returns unique rule IDs', function () {
    Cache::flush();

    $registry = new RuleRegistry(
        rulesPath:      app_path('Services/Metadata/Rules'),
        rulesNamespace: 'App\\Services\\Metadata\\Rules',
    );

    $ids = array_map(fn($r) => $r->id(), $registry->all());

    expect($ids)->toHaveCount(count(array_unique($ids)));
});

test('registry find returns correct rule by ID', function () {
    Cache::flush();

    $registry = new RuleRegistry(
        rulesPath:      app_path('Services/Metadata/Rules'),
        rulesNamespace: 'App\\Services\\Metadata\\Rules',
    );

    $rule = $registry->find('S01');
    expect($rule)->not->toBeNull()
        ->and($rule->id())->toBe('S01');
});

test('registry find returns null for unknown ID', function () {
    Cache::flush();

    $registry = new RuleRegistry(
        rulesPath:      app_path('Services/Metadata/Rules'),
        rulesNamespace: 'App\\Services\\Metadata\\Rules',
    );

    expect($registry->find('UNKNOWN'))->toBeNull();
});

test('registry flush clears cached results', function () {
    Cache::flush();

    $registry = new RuleRegistry(
        rulesPath:      app_path('Services/Metadata/Rules'),
        rulesNamespace: 'App\\Services\\Metadata\\Rules',
    );

    $registry->all(); // populate cache
    $registry->flush();

    expect(Cache::has('metadata_rule_registry'))->toBeFalse();
});

test('all rules have required metadata', function () {
    Cache::flush();

    $registry = new RuleRegistry(
        rulesPath:      app_path('Services/Metadata/Rules'),
        rulesNamespace: 'App\\Services\\Metadata\\Rules',
    );

    foreach ($registry->all() as $rule) {
        expect($rule->id())->toMatch('/^[SCRXO]\d{2}$/')
            ->and($rule->name())->not->toBeEmpty()
            ->and($rule->group())->toBeIn(['structural', 'certificate', 'refeds', 'xsd', 'oidc'])
            ->and($rule->appliesTo())->toBeArray()->not->toBeEmpty()
            ->and($rule->defaultSeverity())->toBeIn(['error', 'warning'])
            ->and($rule->description())->not->toBeEmpty();
    }
});
