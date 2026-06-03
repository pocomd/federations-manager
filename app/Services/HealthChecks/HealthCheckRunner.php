<?php

declare(strict_types=1);

namespace App\Services\HealthChecks;

use App\Services\HealthChecks\Contracts\HealthCheck;

final class HealthCheckRunner
{
    /** @var HealthCheck[] */
    private array $checks = [];

    public function register(HealthCheck $check): self
    {
        $this->checks[] = $check;
        return $this;
    }

    /** @return HealthCheckResult[] */
    public function run(): array
    {
        return array_map(fn (HealthCheck $c) => $c->run(), $this->checks);
    }

    public function overallStatus(array $results): CheckStatus
    {
        foreach ($results as $r) {
            if ($r->status === CheckStatus::Fail) {
                return CheckStatus::Fail;
            }
        }
        foreach ($results as $r) {
            if ($r->status === CheckStatus::Warn) {
                return CheckStatus::Warn;
            }
        }
        return CheckStatus::Ok;
    }
}
