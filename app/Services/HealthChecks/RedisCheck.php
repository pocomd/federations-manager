<?php

declare(strict_types=1);

namespace App\Services\HealthChecks;

use App\Services\HealthChecks\Contracts\HealthCheck;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class RedisCheck implements HealthCheck
{
    public function run(): HealthCheckResult
    {
        $start = hrtime(true);
        $key   = 'health:ping:' . uniqid('', true);

        try {
            Cache::put($key, 'pong', 10);
            $val = Cache::get($key);
            Cache::forget($key);

            $ms = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($val !== 'pong') {
                return HealthCheckResult::fail('redis', 'Cache read/write mismatch', $ms);
            }

            return HealthCheckResult::ok('redis', 'Redis reachable', $ms);
        } catch (Throwable $e) {
            $ms = (int) ((hrtime(true) - $start) / 1_000_000);
            return HealthCheckResult::fail('redis', 'Redis error: ' . $e->getMessage(), $ms);
        }
    }
}
