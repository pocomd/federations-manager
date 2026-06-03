<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Structural;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class S02_EntityIdUnique implements MetadataRule
{
    public function id(): string          { return 'S02'; }
    public function name(): string        { return 'EntityID is unique across the registry'; }
    public function group(): string       { return 'structural'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'The entityID must be globally unique within the federation registry. Duplicate entityIDs cause metadata conflicts and authentication failures.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $duplicate = Entity::where('entity_id', $entity->entity_id)
            ->when($entity->exists, fn($q) => $q->where('id', '!=', $entity->id))
            ->exists();

        if (!$duplicate) {
            return RuleResult::pass($this->id(), 'entityID is unique across the registry.');
        }

        return RuleResult::fail(
            $this->id(),
            'entityID must be unique across the registry.',
            'Duplicate found for: ' . $entity->entity_id,
        );
    }
}
