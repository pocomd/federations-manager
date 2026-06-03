<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Entity\CertificateService;
use App\Services\HealthChecks\CertificateParserCheck;
use App\Services\HealthChecks\CheckStatus;
use App\Services\HealthChecks\DatabaseCheck;
use App\Services\HealthChecks\HealthCheckRunner;
use App\Services\HealthChecks\QueueCheck;
use App\Services\HealthChecks\RedisCheck;
use App\Services\HealthChecks\SchedulerHeartbeatCheck;
use App\Services\HealthChecks\SigningHealthCheck;
use Illuminate\Console\Command;

class SelfTest extends Command
{
    protected $signature   = 'app:selftest {--skip-queue : Skip the queue round-trip check}';
    protected $description = 'Run all application health checks and print a status report';

    public function handle(CertificateService $certificates): int
    {
        $this->info('');
        $this->info('══════════════════════════════════════════');
        $this->info('  Application Self-Test');
        $this->info('══════════════════════════════════════════');
        $this->newLine();

        $runner = (new HealthCheckRunner())
            ->register(new DatabaseCheck())
            ->register(new RedisCheck())
            ->register(new SigningHealthCheck())
            ->register(new SchedulerHeartbeatCheck())
            ->register(new CertificateParserCheck($certificates));

        if (! $this->option('skip-queue')) {
            $runner->register(new QueueCheck());
        }

        $results = $runner->run();
        $overall = $runner->overallStatus($results);

        foreach ($results as $r) {
            $icon  = match ($r->status) {
                CheckStatus::Ok   => '<fg=green>✓</>',
                CheckStatus::Warn => '<fg=yellow>!</>',
                CheckStatus::Fail => '<fg=red>✗</>',
            };
            $label = str_pad($r->name, 14);
            $this->line("  {$icon} {$label} {$r->message}  <fg=gray>({$r->durationMs}ms)</>");
        }

        $this->newLine();

        return match ($overall) {
            CheckStatus::Ok   => $this->printOverall('OK',   'green',  self::SUCCESS),
            CheckStatus::Warn => $this->printOverall('WARN', 'yellow', self::SUCCESS),
            CheckStatus::Fail => $this->printOverall('FAIL', 'red',    self::FAILURE),
        };
    }

    private function printOverall(string $label, string $color, int $code): int
    {
        $this->line("  Overall: <fg={$color};options=bold>{$label}</>");
        $this->newLine();
        return $code;
    }
}
