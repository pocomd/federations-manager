<?php

declare(strict_types=1);

namespace App\Services\HealthChecks;

final class HealthCheckResult
{
    public function __construct(
        public readonly string      $name,
        public readonly CheckStatus $status,
        public readonly string      $message,
        public readonly int         $durationMs,
    ) {}

    public static function ok(string $name, string $message, int $durationMs): self
    {
        return new self($name, CheckStatus::Ok, $message, $durationMs);
    }

    public static function warn(string $name, string $message, int $durationMs): self
    {
        return new self($name, CheckStatus::Warn, $message, $durationMs);
    }

    public static function fail(string $name, string $message, int $durationMs): self
    {
        return new self($name, CheckStatus::Fail, $message, $durationMs);
    }

    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'status'      => $this->status->value,
            'message'     => $this->message,
            'duration_ms' => $this->durationMs,
        ];
    }
}
