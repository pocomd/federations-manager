<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Services\Signing\JavaEnvironment;
use Illuminate\Support\Facades\Process;
use App\Services\Installer\PhpBinary;

class RequirementsChecker
{
    private const MIN_PHP = '8.3.0';

    private const REQUIRED_EXTENSIONS = [
        'pdo_mysql'  => 'Database connectivity',
        'redis'      => 'Cache, sessions and queue',
        'openssl'    => 'Encryption, SAML and XML signing',
        'mbstring'   => 'Laravel core — multi-byte string handling',
        'tokenizer'  => 'Laravel core',
        'ctype'      => 'Laravel core — character type checks',
        'json'       => 'API responses, Horizon and sessions',
        'xml'        => 'SAML metadata XML processing',
        'dom'        => 'XML parsing and mail rendering',
        'simplexml'  => 'XML parsing',
        'curl'       => 'HTTP requests (webhooks, eduGAIN sync)',
        'fileinfo'   => 'File handling and MIME detection',
        'pcntl'      => 'Horizon signal handling',
        'posix'      => 'Horizon process management',
    ];

    /** Extensions only loaded in CLI SAPI — must be probed via subprocess, not extension_loaded() */
    private const CLI_ONLY_EXTENSIONS = ['pcntl', 'posix'];

    private const WRITABLE_PATHS = [
        'storage/'         => 'storage',
        'storage/app/'     => 'storage/app',
        'storage/logs/'    => 'storage/logs',
        'bootstrap/cache/' => 'bootstrap/cache',
    ];

    /** @return array<int, array{label: string, detail: string, pass: bool, required: bool}> */
    public function run(): array
    {
        $checks = [];

        // PHP version
        $phpVersion = PHP_VERSION;
        $phpOk      = version_compare($phpVersion, self::MIN_PHP, '>=');
        $checks[]   = [
            'label'    => 'PHP Version',
            'detail'   => "PHP {$phpVersion}" . ($phpOk ? '' : " — requires >= " . self::MIN_PHP),
            'pass'     => $phpOk,
            'required' => true,
        ];

        // Extensions
        foreach (self::REQUIRED_EXTENSIONS as $ext => $desc) {
            $loaded = in_array($ext, self::CLI_ONLY_EXTENSIONS, true)
                ? $this->extensionLoadedCli($ext)
                : extension_loaded($ext);

            $checks[] = [
                'label'    => "ext-{$ext}",
                'detail'   => $loaded ? $desc : "Not loaded — install php-{$ext}",
                'pass'     => $loaded,
                'required' => true,
            ];
        }

        // Writable directories
        foreach (self::WRITABLE_PATHS as $label => $relative) {
            $path     = base_path($relative);
            $ok       = is_dir($path) && is_writable($path);
            $checks[] = [
                'label'    => $label,
                'detail'   => $ok ? 'Writable' : "Not writable — chmod -R 775 {$path}",
                'pass'     => $ok,
                'required' => true,
            ];
        }

        // .env file
        $writer   = new EnvWriter();
        $envOk    = $writer->isWritable();
        $checks[] = [
            'label'    => '.env',
            'detail'   => $envOk ? 'Writable' : 'Not writable — see manual instructions below',
            'pass'     => $envOk,
            'required' => true,
        ];

        // xmlsectool — optional
        $checks[] = $this->checkXmlsectool();

        return $checks;
    }

    public function allRequiredPass(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['required'] && ! $check['pass']) {
                return false;
            }
        }

        return true;
    }

    private function extensionLoadedCli(string $ext): bool
    {
        $result = Process::run([PhpBinary::find(), '-r', "echo extension_loaded('{$ext}') ? '1' : '0';"]);

        return $result->successful() && trim($result->output()) === '1';
    }

    private function checkXmlsectool(): array
    {
        $path = env('XMLSECTOOL_PATH', config('federation.xmlsectool_path', '/usr/local/bin/xmlsectool'));

        $candidates = array_unique(array_filter([
            $path,
            '/usr/local/bin/xmlsectool',
            '/usr/bin/xmlsectool',
        ]));

        $javaEnv   = JavaEnvironment::env();
        $lastError = null;

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || ! file_exists($candidate) || ! is_executable($candidate)) {
                continue;
            }

            $result = Process::env($javaEnv)->run([$candidate, '--version']);

            if ($result->successful() || $result->exitCode() === 0) {
                $version = trim($result->output() ?: $result->errorOutput());
                return [
                    'label'    => 'xmlsectool',
                    'detail'   => $version ?: "Found at {$candidate}",
                    'pass'     => true,
                    'required' => false,
                ];
            }

            $lastError = trim($result->errorOutput() ?: $result->output()) ?: "Exit code {$result->exitCode()}";
        }

        return [
            'label'    => 'xmlsectool',
            'detail'   => 'Not found — required for signed metadata generation (see install instructions below)',
            'pass'     => false,
            'required' => false,
            'error'    => $lastError,
        ];
    }
}
