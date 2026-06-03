<?php

declare(strict_types=1);

namespace App\Services\HealthChecks;

enum CheckStatus: string
{
    case Ok   = 'ok';
    case Warn = 'warn';
    case Fail = 'fail';
}
