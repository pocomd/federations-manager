<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DownloadSamlSchemas extends Command
{
    protected $signature   = 'saml:download-schemas {--force : Re-download even if files already exist}';
    protected $description = 'Download SAML2 XSD schema files to storage/app/schemas/';

    private const SCHEMAS = [
        'saml-schema-metadata-2.0.xsd'  => 'https://docs.oasis-open.org/security/saml/v2.0/saml-schema-metadata-2.0.xsd',
        'saml-schema-assertion-2.0.xsd' => 'https://docs.oasis-open.org/security/saml/v2.0/saml-schema-assertion-2.0.xsd',
        'xmldsig-core-schema.xsd'        => 'https://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd',
        'xml.xsd'                        => 'https://www.w3.org/2001/xml.xsd',
    ];

    public function handle(): int
    {
        $dir = storage_path('app/schemas');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            $this->line("Created directory: {$dir}");
        }

        $force = $this->option('force');

        foreach (self::SCHEMAS as $filename => $url) {
            $path = $dir . DIRECTORY_SEPARATOR . $filename;

            if (file_exists($path) && !$force) {
                $this->line("  <comment>Skipped</comment>  {$filename} (already exists, use --force to re-download)");
                continue;
            }

            $this->line("  Downloading {$filename}...");

            try {
                $response = Http::timeout(30)->get($url);

                if (!$response->successful()) {
                    $this->error("  Failed to download {$filename}: HTTP {$response->status()}");
                    continue;
                }

                file_put_contents($path, $response->body());
                $this->line("  <info>OK</info>       {$filename}");
            } catch (\Exception $e) {
                $this->error("  Error downloading {$filename}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Done. Run php artisan saml:download-schemas --force to refresh.');

        return self::SUCCESS;
    }
}
