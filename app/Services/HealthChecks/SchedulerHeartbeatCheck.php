<?php

declare(strict_types=1);

namespace App\Services\HealthChecks;

use App\Services\HealthChecks\Contracts\HealthCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class SchedulerHeartbeatCheck implements HealthCheck
{
    public function run(): HealthCheckResult
    {
        $start = hrtime(true);

        $raw = Cache::get('scheduler:heartbeat');
        $ms  = (int) ((hrtime(true) - $start) / 1_000_000);

        if ($raw === null) {
            return HealthCheckResult::fail('scheduler', 'No heartbeat — cron or queue worker may not be running', $ms);
        }

        $lastBeat  = Carbon::parse((string) $raw);
        $ageMin    = (int) $lastBeat->diffInMinutes(now());

        if ($ageMin <= 2) {
            return HealthCheckResult::ok('scheduler', "Last heartbeat {$ageMin}m ago", $ms);
        }

        if ($ageMin <= 5) {
            return HealthCheckResult::warn('scheduler', "Heartbeat is {$ageMin}m old (delayed?)", $ms);
        }

        return HealthCheckResult::fail('scheduler', "Heartbeat is {$ageMin}m old — cron likely stopped", $ms);
    }
}
