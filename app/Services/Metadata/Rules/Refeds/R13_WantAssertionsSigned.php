<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R13_WantAssertionsSigned implements MetadataRule
{
    public function id(): string          { return 'R13'; }
    public function name(): string        { return 'SP WantAssertionsSigned=true'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['sp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'SPSSODescriptor/@WantAssertionsSigned should be true to ensure assertions are cryptographically verified. Only applies to SP entities.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if ($entity->sp_want_assertions_signed === true) {
            return RuleResult::pass($this->id(), 'SPSSODescriptor/@WantAssertionsSigned is true.');
        }

        return RuleResult::warning(
            $this->id(),
            'SPSSODescriptor/@WantAssertionsSigned should be true for security.',
            'Current value: ' . ($entity->sp_want_assertions_signed ? 'true' : 'false'),
        );
    }
}
