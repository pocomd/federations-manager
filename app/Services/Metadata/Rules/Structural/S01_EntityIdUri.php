<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Structural;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class S01_EntityIdUri implements MetadataRule
{
    public function id(): string          { return 'S01'; }
    public function name(): string        { return 'EntityID is a valid URI'; }
    public function group(): string       { return 'structural'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'The entityID attribute must be a valid absolute URI (http or https). This is a required field in the SAML2 metadata specification.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $valid = !empty($entity->entity_id)
            && (bool) filter_var($entity->entity_id, FILTER_VALIDATE_URL)
            && str_starts_with($entity->entity_id, 'http');

        if ($valid) {
            return RuleResult::pass($this->id(), 'entityID is a valid URI.');
        }

        return RuleResult::fail(
            $this->id(),
            'entityID must be a valid http/https URI.',
            'entityID: ' . ($entity->entity_id ?: '(empty)'),
        );
    }
}
