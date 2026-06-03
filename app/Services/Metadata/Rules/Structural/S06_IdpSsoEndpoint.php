<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Structural;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class S06_IdpSsoEndpoint implements MetadataRule
{
    public function id(): string          { return 'S06'; }
    public function name(): string        { return 'IdP has at least one SSO endpoint'; }
    public function group(): string       { return 'structural'; }
    public function appliesTo(): array    { return ['idp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'An Identity Provider must advertise at least one SingleSignOnService endpoint. Only applies to IdP entities.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $ssoEndpoints = $entity->endpoints->where('type', 'sso');

        if ($ssoEndpoints->isNotEmpty()) {
            return RuleResult::pass($this->id(), 'IdP has at least one SingleSignOnService endpoint.');
        }

        return RuleResult::fail(
            $this->id(),
            'IdP must have at least one SingleSignOnService endpoint.',
            'SSO endpoints found: 0',
        );
    }
}
