<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R14_AuthnRequestsSigned implements MetadataRule
{
    public function id(): string          { return 'R14'; }
    public function name(): string        { return 'SP AuthnRequestsSigned=true'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['sp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'SPSSODescriptor/@AuthnRequestsSigned should be true to prevent unauthenticated authentication requests. Only applies to SP entities.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if ($entity->sp_want_authn_requests_signed === true) {
            return RuleResult::pass($this->id(), 'SPSSODescriptor/@AuthnRequestsSigned is true.');
        }

        return RuleResult::warning(
            $this->id(),
            'SPSSODescriptor/@AuthnRequestsSigned should be true for security.',
            'Current value: ' . ($entity->sp_want_authn_requests_signed ? 'true' : 'false'),
        );
    }
}
