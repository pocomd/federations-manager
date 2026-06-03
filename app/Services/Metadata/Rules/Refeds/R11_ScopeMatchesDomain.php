<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R11_ScopeMatchesDomain implements MetadataRule
{
    public function id(): string          { return 'R11'; }
    public function name(): string        { return 'Scope matches entityID domain'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string     { return 'https://wiki.shibboleth.net/confluence/display/SHIB2/NativeSPMetadataProvider'; }

    public function description(): string
    {
        return 'The shibmd:Scope value should match or be a subdomain of the entityID hostname. Only applies to IdP entities.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if (empty($entity->scope) || empty($entity->entity_id)) {
            return RuleResult::notApplicable($this->id());
        }

        $entityHost = parse_url($entity->entity_id, PHP_URL_HOST) ?? '';
        $scopeMatch = str_ends_with($entityHost, $entity->scope)
            || str_ends_with($entity->scope, $entityHost);

        if ($scopeMatch) {
            return RuleResult::pass($this->id(), "Scope '{$entity->scope}' matches entityID domain '{$entityHost}'.");
        }

        return RuleResult::warning(
            $this->id(),
            "Scope '{$entity->scope}' does not match entityID domain '{$entityHost}'.",
            'Scope should match or be a subdomain of the entityID hostname.',
        );
    }
}
