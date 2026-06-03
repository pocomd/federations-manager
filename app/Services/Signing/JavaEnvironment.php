<?php

declare(strict_types=1);

namespace App\Services\Signing;

final class JavaEnvironment
{
    public static function home(): ?string
    {
        if (($h = getenv('JAVA_HOME')) !== false && is_dir($h)) {
            return $h;
        }

        if (is_file('/usr/bin/java')) {
            $real = realpath('/usr/bin/java');
            if ($real) {
                $h = dirname(dirname($real));
                if (is_dir($h)) {
                    return $h;
                }
            }
        }

        foreach (glob('/usr/lib/jvm/java-*-openjdk-*') ?: [] as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return null;
    }

    /** Returns the full process environment with JAVA_HOME injected if detectable. */
    public static function env(): array
    {
        $env  = getenv();
        $home = self::home();

        if ($home) {
            $env['JAVA_HOME'] = $home;
        }

        return $env;
    }
}
