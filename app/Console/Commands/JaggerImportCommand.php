<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Jagger\JaggerImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PDOException;

/**
 * JaggerImportCommand
 *
 * Artisan command to migrate data from a legacy Jagger (ResourceRegistry3)
 * database into the Federation Manager schema.
 *
 * Usage:
 *   php artisan jagger:import
 *   php artisan jagger:import --dry-run
 *   php artisan jagger:import --host=db.example.com --database=jagger --username=jagger
 *
 * Credentials are read from .env (JAGGER_DB_*) but can be overridden per-flag.
 */
class JaggerImportCommand extends Command
{
    protected $signature = 'jagger:import
        {--dry-run         : Log what would be imported without writing anything}
        {--host=           : Jagger DB host (overrides JAGGER_DB_HOST)}
        {--port=           : Jagger DB port (overrides JAGGER_DB_PORT, default 3306)}
        {--database=       : Jagger DB name (overrides JAGGER_DB_NAME)}
        {--username=       : Jagger DB username (overrides JAGGER_DB_USER)}
        {--password=       : Jagger DB password (overrides JAGGER_DB_PASSWORD)}
        {--force           : Skip confirmation prompt in production}';

    protected $description = 'Import entities, federations, and certificates from a Jagger (ResourceRegistry3) database';

    public function handle(): int
    {
        // JaggerImporter lives outside normal autoload paths — require explicitly
        require_once database_path('migrations/jagger/JaggerImporter.php');

        $dryRun = (bool) $this->option('dry-run');

        $config = [
            'host'     => $this->option('host')     ?: env('JAGGER_DB_HOST',    '127.0.0.1'),
            'port'     => (int) ($this->option('port')     ?: env('JAGGER_DB_PORT',    3306)),
            'database' => $this->option('database') ?: env('JAGGER_DB_NAME',    ''),
            'username' => $this->option('username') ?: env('JAGGER_DB_USER',    ''),
            'password' => $this->option('password') ?: env('JAGGER_DB_PASSWORD', ''),
        ];

        // ── Pre-flight checks ─────────────────────────────────────────────

        if (empty($config['database'])) {
            $this->error('Jagger database name is required. Set JAGGER_DB_NAME in .env or use --database.');
            return self::FAILURE;
        }

        if (empty($config['username'])) {
            $this->error('Jagger DB username is required. Set JAGGER_DB_USER in .env or use --username.');
            return self::FAILURE;
        }

        // ── Dry-run banner ────────────────────────────────────────────────

        if ($dryRun) {
            $this->warn('');
            $this->warn('┌────────────────────────────────────────────────────┐');
            $this->warn('│  DRY-RUN MODE — no data will be written            │');
            $this->warn('│  Import actions will be logged to laravel.log      │');
            $this->warn('└────────────────────────────────────────────────────┘');
            $this->warn('');
        } else {
            // Confirmation in production
            $appEnv = app()->environment();
            if ($appEnv === 'production' && !$this->option('force')) {
                if (!$this->confirm("You are in PRODUCTION ({$appEnv}). This will write to the live database. Continue?")) {
                    $this->line('Aborted.');
                    return self::SUCCESS;
                }
            }
        }

        // ── Connect ───────────────────────────────────────────────────────

        $this->info("Connecting to Jagger: {$config['host']}:{$config['port']}/{$config['database']}");

        $importer = new JaggerImporter($dryRun);

        try {
            $importer->connectToJagger($config);
            $this->info('Connection established.');
        } catch (PDOException $e) {
            $this->error('Cannot connect to Jagger database: ' . $e->getMessage());
            Log::error('jagger:import connection failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        // ── Run import ────────────────────────────────────────────────────

        $this->newLine();
        $this->info('Starting import…');
        $this->newLine();

        $counts = $this->runWithProgress($importer);

        // ── Summary table ─────────────────────────────────────────────────

        $this->newLine();
        $this->table(
            ['Phase', 'Count'],
            [
                ['Federations',  $counts['federations']],
                ['Entities',     $counts['entities']],
                ['Certificates', $counts['certificates']],
                ['Endpoints',    $counts['endpoints']],
                ['Contacts',     $counts['contacts']],
                ['UI Info rows', $counts['ui_info']],
                ['Attributes',   $counts['attributes']],
                ['Memberships',  $counts['memberships']],
                ['—',            '—'],
                ['Skipped',      $counts['skipped']],
                ['Errors',       $counts['errors']],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('[DRY-RUN] Nothing was written. Re-run without --dry-run to perform the import.');
        }

        if ($counts['errors'] > 0) {
            $this->warn("{$counts['errors']} error(s) occurred — check storage/logs/laravel.log for details.");
            return self::FAILURE;
        }

        $this->info('Import complete.');
        return self::SUCCESS;
    }

    /**
     * Run each phase sequentially, printing a progress line per phase.
     */
    private function runWithProgress(JaggerImporter $importer): array
    {
        $phases = [
            'Federations'  => 'importFederations',
            'Entities'     => 'importEntities',
            'Certificates' => 'importCertificates',
            'Endpoints'    => 'importEndpoints',
            'Contacts'     => 'importContacts',
            'Attributes'   => 'importAttributes',
            'Memberships'  => 'importEntityFederationRelationships',
        ];

        foreach ($phases as $label => $method) {
            $this->line("  → {$label}…");
            $importer->{$method}();
        }

        return $importer->getCounts();
    }
}
