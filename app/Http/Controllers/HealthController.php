<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Entity\CertificateService;
use App\Services\HealthChecks\CertificateParserCheck;
use App\Services\HealthChecks\CheckStatus;
use App\Services\HealthChecks\DatabaseCheck;
use App\Services\HealthChecks\HealthCheckRunner;
use App\Services\HealthChecks\QueueCheck;
use App\Services\HealthChecks\RedisCheck;
use App\Services\HealthChecks\SchedulerHeartbeatCheck;
use App\Services\HealthChecks\SigningHealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function __invoke(Request $request, CertificateService $certificates): JsonResponse
    {
        $token = config('app.health_check_token');

        if ($token) {
            $provided = $request->bearerToken();
            if (! hash_equals((string) $token, (string) $provided)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $runner = (new HealthCheckRunner())
            ->register(new DatabaseCheck())
            ->register(new RedisCheck())
            ->register(new SigningHealthCheck())
            ->register(new SchedulerHeartbeatCheck())
            ->register(new CertificateParserCheck($certificates))
            ->register(new QueueCheck());

        $results = $runner->run();
        $overall = $runner->overallStatus($results);

        $statusCode = $overall === CheckStatus::Fail ? 503 : 200;

        return response()->json([
            'status'  => $overall->value,
            'checks'  => array_map(fn ($r) => $r->toArray(), $results),
            'time'    => now()->toISOString(),
        ], $statusCode);
    }
}
