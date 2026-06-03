<?php

declare(strict_types=1);

namespace App\Services\Installer;

use RuntimeException;

class EnvWriter
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? base_path('.env');
    }

    public function isWritable(): bool
    {
        return file_exists($this->path)
            ? is_writable($this->path)
            : is_writable(dirname($this->path));
    }

    public function get(string $key): ?string
    {
        if (! file_exists($this->path)) {
            return null;
        }

        $content = file_get_contents($this->path);

        if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $content, $matches)) {
            return trim($matches[1], '"\'');
        }

        return null;
    }

    public function set(string $key, string $value): void
    {
        $this->setMany([$key => $value]);
    }

    public function setMany(array $values): void
    {
        $content = file_exists($this->path) ? (file_get_contents($this->path) ?: '') : '';

        foreach ($values as $key => $value) {
            $escaped = $this->escape((string) $value);
            $pattern = '/^' . preg_quote((string) $key, '/') . '=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $key . '=' . $escaped, $content);
            } else {
                $content = rtrim($content) . "\n" . $key . '=' . $escaped . "\n";
            }
        }

        $fp = fopen($this->path, 'c');

        if ($fp === false) {
            throw new RuntimeException("Cannot open .env for writing: {$this->path}");
        }

        try {
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, $content);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
    }

    private function escape(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s"\'#\\\\]/', $value)) {
            return '"' . addcslashes($value, '"\\') . '"';
        }

        return $value;
    }
}
