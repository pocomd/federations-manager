<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class MigrateSyncExisting extends Command
{
    protected $signature   = 'migrate:sync-existing {--force : Run without confirmation}';
    protected $description = 'Run pending migrations; mark as done any that fail due to already-existing tables';

    public function handle(Migrator $migrator): int
    {
        $repo = $migrator->getRepository();

        if (! $repo->repositoryExists()) {
            $repo->createRepository();
        }

        $ran     = $repo->getRan();
        $files   = $migrator->getMigrationFiles([database_path('migrations')]);
        $pending = array_filter($files, fn($file, $name) => ! in_array($name, $ran), ARRAY_FILTER_USE_BOTH);

        if (empty($pending)) {
            $this->info('Nothing to migrate.');
            return self::SUCCESS;
        }

        $batch = $repo->getNextBatchNumber();

        foreach ($pending as $name => $file) {
            // Anonymous class migrations return the instance from require;
            // named class migrations return 1 and must be resolved by class name.
            $instance  = require $file;
            $migration = $instance instanceof \Illuminate\Database\Migrations\Migration
                ? $instance
                : $migrator->resolve($name);

            try {
                DB::transaction(fn () => $migration->up());
                $repo->log($name, $batch);
                $this->line("  <info>Migrated:</info>  {$name}");
            } catch (QueryException $e) {
                $msg = $e->getMessage();

                if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate')) {
                    $repo->log($name, $batch);
                    $this->line("  <comment>Skipped (exists):</comment>  {$name}");
                } else {
                    $this->error("  Failed: {$name}");
                    $this->error('  ' . $msg);
                    return self::FAILURE;
                }
            }
        }

        return self::SUCCESS;
    }
}
