<?php

declare(strict_types=1);

namespace App\Services\Installer;

final class PhpBinary
{
    private static ?string $resolved = null;

    /**
     * Returns the PHP CLI binary path.
     *
     * PHP_BINARY under PHP-FPM points to the FPM daemon (e.g. /usr/sbin/php-fpm8.4)
     * which does not accept CLI flags. We probe for a usable CLI binary instead.
     */
    public static function find(): string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $candidates = array_unique(array_filter([
            dirname(PHP_BINARY) . '/php',
            '/usr/bin/php',
            'php',
        ]));

        foreach ($candidates as $bin) {
            $output = shell_exec(escapeshellcmd($bin) . ' -r "echo 1;" 2>/dev/null');
            if (trim($output ?? '') === '1') {
                return self::$resolved = $bin;
            }
        }

        return self::$resolved = 'php';
    }
}
