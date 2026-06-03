<?php

declare(strict_types=1);

namespace App\Services\HealthChecks;

use App\Models\Federation;
use App\Services\HealthChecks\Contracts\HealthCheck;
use App\Services\Signing\SigningDriverFactory;

final class SigningHealthCheck implements HealthCheck
{
    public function run(): HealthCheckResult
    {
        $start   = hrtime(true);
        $factory = app(SigningDriverFactory::class);
        $active  = $factory->activeDrivers();

        if (empty($active)) {
            return HealthCheckResult::warn(
                'signing',
                'No signing drivers are active — metadata will be unsigned',
                (int) ((hrtime(true) - $start) / 1_000_000)
            );
        }

        $messages = [];
        $worst    = CheckStatus::Ok;

        foreach ($active as $name => $label) {
            $stub   = new Federation(['signing_driver' => $name]);
            $result = $factory->make($stub)->healthCheck();

            $messages[] = "[{$label}] " . $result->message;

            if ($result->status === CheckStatus::Fail) {
                $worst = CheckStatus::Fail;
            } elseif ($result->status === CheckStatus::Warn && $worst !== CheckStatus::Fail) {
                $worst = CheckStatus::Warn;
            }
        }

        $combined = implode('; ', $messages);
        $ms       = (int) ((hrtime(true) - $start) / 1_000_000);

        return match ($worst) {
            CheckStatus::Fail => HealthCheckResult::fail('signing', $combined, $ms),
            CheckStatus::Warn => HealthCheckResult::warn('signing', $combined, $ms),
            default           => HealthCheckResult::ok('signing', $combined, $ms),
        };
    }
}
