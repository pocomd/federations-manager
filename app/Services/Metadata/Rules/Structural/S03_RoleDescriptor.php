<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Structural;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class S03_RoleDescriptor implements MetadataRule
{
    public function id(): string          { return 'S03'; }
    public function name(): string        { return 'At least one role descriptor with endpoint'; }
    public function group(): string       { return 'structural'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'An IdP must have at least one IDPSSODescriptor with an SSO endpoint, and an SP must have at least one SPSSODescriptor with an ACS endpoint.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $hasEndpoint = match ($entity->type) {
            'idp'   => $entity->endpoints->where('type', 'sso')->isNotEmpty(),
            'sp'    => $entity->endpoints->where('type', 'acs')->isNotEmpty(),
            default => false,
        };

        if ($hasEndpoint) {
            return RuleResult::pass($this->id(), 'Required SSO/ACS endpoint is present.');
        }

        $detail = match ($entity->type) {
            'idp'   => 'IdP must have at least one SingleSignOnService endpoint.',
            default => 'SP must have at least one AssertionConsumerService endpoint.',
        };

        return RuleResult::fail($this->id(), 'At least one role descriptor with a valid endpoint is required.', $detail);
    }
}
