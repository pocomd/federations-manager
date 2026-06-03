<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class DeployCommand extends Command
{
    protected $signature   = 'app:deploy';
    protected $description = 'Run all post-release deployment steps (migrate, seed, cache)';

    private const STEPS = [
        ['cmd' => ['optimize:clear'],                                            'label' => 'Clearing all caches'],
        ['cmd' => ['migrate', '--force'],                                        'label' => 'Running database migrations'],
        ['cmd' => ['db:seed', '--class=RolesAndPermissionsSeeder', '--force'],   'label' => 'Seeding roles and permissions'],
        ['cmd' => ['db:seed', '--class=FederationSeeder', '--force'],            'label' => 'Seeding federation defaults'],
        ['cmd' => ['db:seed', '--class=SchedulerSettingsSeeder', '--force'],     'label' => 'Seeding scheduler settings'],
        ['cmd' => ['config:cache'],                                              'label' => 'Caching configuration'],
        ['cmd' => ['route:cache'],                                               'label' => 'Caching routes'],
        ['cmd' => ['view:cache'],                                                'label' => 'Caching views'],
        ['cmd' => ['event:cache'],                                               'label' => 'Caching events'],
        ['cmd' => ['storage:link', '--force'],                                   'label' => 'Linking storage'],
        ['cmd' => ['permission:cache-reset'],                                    'label' => 'Resetting permission cache'],
    ];

    public function handle(): int
    {
        $this->info('Starting deployment...');
        $this->newLine();

        $this->warnEnvDrift();

        $failed = false;

        foreach (self::STEPS as $step) {
            $this->output->write("  <fg=cyan>→</> {$step['label']}...");

            $result = Process::run([PHP_BINARY, base_path('artisan'), ...$step['cmd'], '--no-ansi']);

            if ($result->successful()) {
                $this->output->writeln(' <fg=green>done</>');
            } else {
                $this->output->writeln(' <fg=red>FAILED</>');
                $this->error($result->output() ?: $result->errorOutput());
                $failed = true;
                break;
            }
        }

        $this->newLine();

        if ($failed) {
            $this->error('Deployment failed. See output above.');
            return self::FAILURE;
        }

        $this->info('Deployment complete.');
        return self::SUCCESS;
    }

    private function warnEnvDrift(): void
    {
        $examplePath = base_path('.env.example');
        $envPath     = base_path('.env');

        if (! file_exists($examplePath) || ! file_exists($envPath)) {
            return;
        }

        $exampleKeys = $this->parseEnvKeys($examplePath);
        $envKeys     = $this->parseEnvKeys($envPath);
        $missing     = array_diff($exampleKeys, $envKeys);

        if (empty($missing)) {
            return;
        }

        $this->warn('  ⚠  The following keys are in .env.example but missing from .env:');
        foreach ($missing as $key) {
            $this->warn("       - {$key}");
        }
        $this->newLine();
    }

    private function parseEnvKeys(string $path): array
    {
        $keys = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = ltrim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $keys[] = strtok($line, '=');
        }
        return $keys;
    }
}
