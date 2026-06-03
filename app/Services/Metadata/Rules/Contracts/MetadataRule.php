<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Contracts;

use App\Models\Entity;

interface MetadataRule
{
    public function id(): string;
    public function name(): string;
    public function group(): string;
    public function description(): string;
    public function specUrl(): string;
    public function defaultSeverity(): string;  // 'error'|'warning'
    /** @return string[] Entity types this rule applies to, e.g. ['idp','sp'] or ['oidc'] */
    public function appliesTo(): array;
    public function evaluate(Entity $entity): RuleResult;
}
