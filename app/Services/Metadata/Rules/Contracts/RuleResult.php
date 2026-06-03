<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Contracts;

final class RuleResult
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $status,   // 'pass'|'fail'|'warning'|'not_applicable'
        public readonly string $message,
        public readonly string $detail = '',
    ) {}

    public static function pass(string $ruleId, string $message): self
    {
        return new self($ruleId, 'pass', $message);
    }

    public static function fail(string $ruleId, string $message, string $detail = ''): self
    {
        return new self($ruleId, 'fail', $message, $detail);
    }

    public static function warning(string $ruleId, string $message, string $detail = ''): self
    {
        return new self($ruleId, 'warning', $message, $detail);
    }

    public static function notApplicable(string $ruleId, string $ruleName = ''): self
    {
        return new self($ruleId, 'not_applicable', $ruleName ?: 'Not applicable');
    }

    public function toArray(): array
    {
        return [
            'id'      => $this->ruleId,
            'status'  => $this->status,
            'message' => $this->message,
            'detail'  => $this->detail,
        ];
    }
}
