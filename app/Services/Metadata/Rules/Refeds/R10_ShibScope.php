<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R10_ShibScope implements MetadataRule
{
    public function id(): string          { return 'R10'; }
    public function name(): string        { return 'shibmd:Scope present for IdP'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string     { return 'https://wiki.shibboleth.net/confluence/display/SHIB2/NativeSPMetadataProvider'; }

    public function description(): string
    {
        return 'An IdP should declare at least one shibmd:Scope to enable discovery services and attribute release policies. Only applies to IdP entities.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if (! empty($entity->scope)) {
            return RuleResult::pass($this->id(), 'shibmd:Scope is present.');
        }

        return RuleResult::warning(
            $this->id(),
            'shibmd:Scope is recommended for Identity Providers.',
            'Scope helps discovery services and attribute release policies.',
        );
    }
}
