<?php

declare(strict_types=1);

namespace App\Services\HealthChecks\Contracts;

use App\Services\HealthChecks\HealthCheckResult;

interface HealthCheck
{
    public function run(): HealthCheckResult;
}
