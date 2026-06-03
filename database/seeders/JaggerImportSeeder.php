<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Jagger\JaggerImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use PDOException;

/**
 * JaggerImportSeeder
 *
 * Reads Jagger DB credentials from .env and runs JaggerImporter.
 *
 * Standard run (writes data):
 *   php artisan db:seed --class=JaggerImportSeeder
 *
 * Dry-run (logs only, no writes):
 *   JAGGER_DRY_RUN=true php artisan db:seed --class=JaggerImportSeeder
 *
 * Or use the dedicated Artisan command which supports --dry-run natively:
 *   php artisan jagger:import
 *   php artisan jagger:import --dry-run
 *
 * Required .env keys:
 *   JAGGER_DB_HOST      (default: 127.0.0.1)
 *   JAGGER_DB_PORT      (default: 3306)
 *   JAGGER_DB_NAME      (required)
 *   JAGGER_DB_USER      (required)
 *   JAGGER_DB_PASSWORD  (required)
 */
class JaggerImportSeeder extends Seeder
{
    public function run(): void
    {
        // JaggerImporter lives in a non-autoloaded directory — require it explicitly
        require_once database_path('migrations/jagger/JaggerImporter.php');

        $dryRun = (bool) env('JAGGER_DRY_RUN', false);

        $config = [
            'host'     => env('JAGGER_DB_HOST',     '127.0.0.1'),
            'port'     => (int) env('JAGGER_DB_PORT', 3306),
            'database' => env('JAGGER_DB_NAME',     ''),
            'username' => env('JAGGER_DB_USER',     ''),
            'password' => env('JAGGER_DB_PASSWORD',  ''),
        ];

        if (empty($config['database'])) {
            $this->command?->error('JAGGER_DB_NAME is not set in .env — aborting.');
            return;
        }

        if (empty($config['username'])) {
            $this->command?->error('JAGGER_DB_USER is not set in .env — aborting.');
            return;
        }

        if ($dryRun) {
            $this->command?->warn('[DRY-RUN] No data will be written to the database.');
        }

        $this->command?->info("Connecting to Jagger database: {$config['host']}/{$config['database']}");

        $importer = new JaggerImporter($dryRun);

        try {
            $importer->connectToJagger($config);
        } catch (PDOException $e) {
            $this->command?->error('Cannot connect to Jagger database: ' . $e->getMessage());
            Log::error('JaggerImportSeeder: connection failed', ['error' => $e->getMessage()]);
            return;
        }

        $counts = $importer->run();

        $this->printSummary($counts, $dryRun);
    }

    private function printSummary(array $counts, bool $dryRun): void
    {
        $this->command?->newLine();
        $this->command?->info('╔═══════════════════════════════════╗');
        $this->command?->info('║      Jagger Import Summary        ║');
        $this->command?->info('╠═══════════════════════════════════╣');
        $this->command?->info(sprintf('║  Federations  : %17d  ║', $counts['federations']));
        $this->command?->info(sprintf('║  Entities     : %17d  ║', $counts['entities']));
        $this->command?->info(sprintf('║  Certificates : %17d  ║', $counts['certificates']));
        $this->command?->info(sprintf('║  Endpoints    : %17d  ║', $counts['endpoints']));
        $this->command?->info(sprintf('║  Contacts     : %17d  ║', $counts['contacts']));
        $this->command?->info(sprintf('║  UI Info rows : %17d  ║', $counts['ui_info']));
        $this->command?->info(sprintf('║  Attributes   : %17d  ║', $counts['attributes']));
        $this->command?->info(sprintf('║  Memberships  : %17d  ║', $counts['memberships']));
        $this->command?->info('╠═══════════════════════════════════╣');
        $this->command?->info(sprintf('║  Skipped      : %17d  ║', $counts['skipped']));
        $this->command?->info(sprintf('║  Errors       : %17d  ║', $counts['errors']));
        $this->command?->info('╚═══════════════════════════════════╝');

        if ($dryRun) {
            $this->command?->newLine();
            $this->command?->warn('[DRY-RUN] No data was written. Remove JAGGER_DRY_RUN or use --dry-run=false to import.');
        }

        if ($counts['errors'] > 0) {
            $this->command?->warn("Import completed with {$counts['errors']} error(s). Check laravel.log for details.");
        }
    }
}
