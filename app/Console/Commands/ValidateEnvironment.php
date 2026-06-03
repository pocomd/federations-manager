<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ValidateEnvironment extends Command
{
    protected $signature   = 'app:validate-env';
    protected $description = 'Validate environment variables, required files, and infrastructure connectivity';

    private const REQUIRED_ENV_VARS = [
        'APP_KEY'             => 'Laravel application encryption key',
        'DB_HOST'             => 'MySQL host',
        'DB_DATABASE'         => 'MySQL database name',
        'DB_USERNAME'         => 'MySQL username',
        'REDIS_HOST'          => 'Redis host',
        'MAIL_MAILER'         => 'Mail transport driver',
        'MAIL_FROM_ADDRESS'   => 'Default sender address',
    ];

    private const OPTIONAL_ENV_VARS = [
        'XMLSECTOOL_PATH'           => 'Path to xmlsectool binary (required for metadata signing)',
        'FILE_SIGNING_IS_ACTIVE'    => 'Enable file-based signing driver (default: true)',
        'SOFTHSM_SIGNING_IS_ACTIVE' => 'Enable SoftHSM2 signing driver (set by installer)',
        'JAGGER_HSM_PIN'            => 'SoftHSM2 token PIN (required when SoftHSM2 driver is active)',
        'HEALTH_CHECK_TOKEN'        => 'Bearer token for GET /health endpoint',
        'JAGGER_IMPORT_ENABLED'     => 'Enable Jagger import UI',
    ];

    public function handle(): int
    {
        $this->info('');
        $this->info('══════════════════════════════════════════');
        $this->info('  Environment Validation');
        $this->info('══════════════════════════════════════════');

        $failed = false;

        // ── Required env vars ─────────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>Required environment variables:</>');

        foreach (self::REQUIRED_ENV_VARS as $key => $desc) {
            $val = env($key);
            if ($val === null || $val === '') {
                $this->line("  <fg=red>✗</> <fg=yellow>{$key}</> — {$desc}");
                $failed = true;
            } else {
                $display = $this->maskSecret($key, (string) $val);
                $this->line("  <fg=green>✓</> {$key} = {$display}");
            }
        }

        // ── Optional env vars ─────────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>Optional environment variables:</>');

        foreach (self::OPTIONAL_ENV_VARS as $key => $desc) {
            $val = env($key);
            if ($val === null || $val === '') {
                $this->line("  <fg=yellow>–</> {$key} not set ({$desc})");
            } else {
                $display = $this->maskSecret($key, (string) $val);
                $this->line("  <fg=green>✓</> {$key} = {$display}");
            }
        }

        // ── File existence ────────────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>File checks:</>');

        $filesToCheck = array_filter([
            env('XMLSECTOOL_PATH'),
            env('PKCS11_LIBRARY'),
        ]);

        if (empty($filesToCheck)) {
            $this->line('  <fg=yellow>–</> No signing/tool paths configured — skipped');
        } else {
            foreach ($filesToCheck as $path) {
                if (file_exists((string) $path)) {
                    $extra = is_executable((string) $path) ? ' (executable)' : '';
                    $this->line("  <fg=green>✓</> {$path}{$extra}");
                } else {
                    $this->line("  <fg=red>✗</> Missing: {$path}");
                    $failed = true;
                }
            }
        }

        // ── Database ──────────────────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>Database connectivity:</>');

        try {
            DB::select('SELECT 1');
            $this->line('  <fg=green>✓</> MySQL connected');
        } catch (Throwable $e) {
            $this->line('  <fg=red>✗</> MySQL: ' . $e->getMessage());
            $failed = true;
        }

        // ── Redis ─────────────────────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>Redis connectivity:</>');

        try {
            $key = 'validate-env:ping:' . uniqid('', true);
            Cache::put($key, 'ok', 5);
            $val = Cache::get($key);
            Cache::forget($key);

            if ($val === 'ok') {
                $this->line('  <fg=green>✓</> Redis connected');
            } else {
                $this->line('  <fg=red>✗</> Redis round-trip failed');
                $failed = true;
            }
        } catch (Throwable $e) {
            $this->line('  <fg=red>✗</> Redis: ' . $e->getMessage());
            $failed = true;
        }

        // ── Result ────────────────────────────────────────────────────────
        $this->newLine();
        if ($failed) {
            $this->error('Validation FAILED — fix the issues above before running in production.');
            return self::FAILURE;
        }

        $this->info('All checks passed.');
        return self::SUCCESS;
    }

    private function maskSecret(string $key, string $value): string
    {
        $secrets = ['KEY', 'SECRET', 'PASSWORD', 'TOKEN', 'PASS'];
        foreach ($secrets as $s) {
            if (str_contains(strtoupper($key), $s)) {
                return str_repeat('*', min(strlen($value), 8));
            }
        }
        return strlen($value) > 60 ? substr($value, 0, 57) . '...' : $value;
    }
}
