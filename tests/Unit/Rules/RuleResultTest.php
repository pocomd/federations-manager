<?php

declare(strict_types=1);

use App\Services\Metadata\Rules\Contracts\RuleResult;

test('pass factory creates pass status', function () {
    $result = RuleResult::pass('S01', 'All good.');
    expect($result->status)->toBe('pass')
        ->and($result->ruleId)->toBe('S01')
        ->and($result->message)->toBe('All good.')
        ->and($result->detail)->toBe('');
});

test('fail factory creates fail status', function () {
    $result = RuleResult::fail('S01', 'Failed.', 'Detail here.');
    expect($result->status)->toBe('fail')
        ->and($result->detail)->toBe('Detail here.');
});

test('warning factory creates warning status', function () {
    $result = RuleResult::warning('C05', 'Weak algo.', 'sha1');
    expect($result->status)->toBe('warning');
});

test('notApplicable factory creates not_applicable status', function () {
    $result = RuleResult::notApplicable('R07');
    expect($result->status)->toBe('not_applicable')
        ->and($result->ruleId)->toBe('R07');
});

test('toArray returns correct shape', function () {
    $result = RuleResult::fail('S02', 'Duplicate entityID.', 'https://idp.example.org');
    $arr    = $result->toArray();
    expect($arr)->toHaveKeys(['id', 'status', 'message', 'detail'])
        ->and($arr['id'])->toBe('S02')
        ->and($arr['status'])->toBe('fail')
        ->and($arr['message'])->toBe('Duplicate entityID.')
        ->and($arr['detail'])->toBe('https://idp.example.org');
});
