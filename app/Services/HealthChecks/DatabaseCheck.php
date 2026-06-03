<?php

declare(strict_types=1);

namespace App\Services\HealthChecks;

use App\Services\HealthChecks\Contracts\HealthCheck;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabaseCheck implements HealthCheck
{
    public function run(): HealthCheckResult
    {
        $start = hrtime(true);

        try {
            DB::select('SELECT 1');
            $ms = (int) ((hrtime(true) - $start) / 1_000_000);
            return HealthCheckResult::ok('database', 'MySQL reachable', $ms);
        } catch (Throwable $e) {
            $ms = (int) ((hrtime(true) - $start) / 1_000_000);
            return HealthCheckResult::fail('database', 'MySQL error: ' . $e->getMessage(), $ms);
        }
    }
}
