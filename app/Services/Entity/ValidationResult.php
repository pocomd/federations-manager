<?php

declare(strict_types=1);

namespace App\Services\Entity;

use ArrayAccess;

/**
 * Value object returned by EntityMetadataService::validate().
 *
 * Implements ArrayAccess so callers may use either $result['passed'] or $result->passed().
 *
 * Each check entry shape:
 *   ['id' => 'S01', 'status' => 'pass'|'fail'|'warning'|'not_applicable', 'message' => '...']
 */
final class ValidationResult implements ArrayAccess
{
    private function __construct(
        private readonly array $checks,
    ) {}

    public static function fromChecks(array $checks): self
    {
        return new self($checks);
    }

    public function passed(): bool
    {
        foreach ($this->checks as $check) {
            if ($check['status'] === 'fail') {
                return false;
            }
        }

        return true;
    }

    /** @return list<array{id:string,status:string,message:string}> */
    public function checks(): array
    {
        return $this->checks;
    }

    /** @return list<string> */
    public function errors(): array
    {
        return array_values(
            array_map(
                fn (array $c) => "[{$c['id']}] {$c['message']}",
                array_filter($this->checks, fn (array $c) => $c['status'] === 'fail'),
            )
        );
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return array_values(
            array_map(
                fn (array $c) => "[{$c['id']}] {$c['message']}",
                array_filter($this->checks, fn (array $c) => $c['status'] === 'warning'),
            )
        );
    }

    public function toArray(): array
    {
        $checks = $this->checks;
        return [
            'passed'         => $this->passed(),
            'summary'        => [
                'total'          => count($checks),
                'passed'         => count(array_filter($checks, fn($c) => $c['status'] === 'pass')),
                'errors'         => count(array_filter($checks, fn($c) => $c['status'] === 'fail')),
                'warnings'       => count(array_filter($checks, fn($c) => $c['status'] === 'warning')),
                'not_applicable' => count(array_filter($checks, fn($c) => $c['status'] === 'not_applicable')),
            ],
            'errors'         => array_values(array_filter($checks, fn($c) => $c['status'] === 'fail')),
            'warnings'       => array_values(array_filter($checks, fn($c) => $c['status'] === 'warning')),
            'not_applicable' => array_values(array_filter($checks, fn($c) => $c['status'] === 'not_applicable')),
            'checks'         => $checks,
        ];
    }

    // ── ArrayAccess — delegates to toArray() so $result['passed'] works ──────

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Read-only value object — writes are silently ignored.
    }

    public function offsetUnset(mixed $offset): void
    {
        // Read-only value object — unset is silently ignored.
    }
}
