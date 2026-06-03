<?php

declare(strict_types=1);

namespace App\Services\HealthChecks;

use App\Jobs\HealthCheckPingJob;
use App\Services\HealthChecks\Contracts\HealthCheck;
use Illuminate\Support\Facades\Cache;

final class QueueCheck implements HealthCheck
{
    private const POLL_INTERVAL_MS = 500;
    private const MAX_WAIT_MS      = 5_000;

    public function run(): HealthCheckResult
    {
        $start = hrtime(true);

        $defaultKey = 'health:queue:default:' . uniqid('', true);
        $highKey    = 'health:queue:high:' . uniqid('', true);

        HealthCheckPingJob::dispatch($defaultKey);
        HealthCheckPingJob::dispatch($highKey)->onQueue('high');

        $waited    = 0;
        $defaultOk = false;
        $highOk    = false;

        while ($waited < self::MAX_WAIT_MS) {
            usleep(self::POLL_INTERVAL_MS * 1_000);
            $waited += self::POLL_INTERVAL_MS;

            $defaultOk = $defaultOk || Cache::get($defaultKey) === true;
            $highOk    = $highOk    || Cache::get($highKey)    === true;

            if ($defaultOk && $highOk) {
                break;
            }
        }

        Cache::forget($defaultKey);
        Cache::forget($highKey);

        $ms = (int) ((hrtime(true) - $start) / 1_000_000);

        if ($defaultOk && $highOk) {
            return HealthCheckResult::ok('queue', "Both queues processed jobs within {$ms}ms", $ms);
        }

        $failed = collect([
            $defaultOk ? null : 'default',
            $highOk    ? null : 'high',
        ])->filter()->implode(', ');

        return HealthCheckResult::fail('queue', "No response within 5s on queue(s): {$failed} — worker may be down", $ms);
    }
}
