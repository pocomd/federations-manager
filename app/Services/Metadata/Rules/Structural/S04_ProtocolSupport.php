<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Structural;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class S04_ProtocolSupport implements MetadataRule
{
    public function id(): string          { return 'S04'; }
    public function name(): string        { return 'protocolSupportEnumeration maps to SAML2'; }
    public function group(): string       { return 'structural'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'The entity type must be idp or sp, which maps to the SAML2 protocol support enumeration urn:oasis:names:tc:SAML:2.0:protocol.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if (in_array($entity->type, ['idp', 'sp'], true)) {
            return RuleResult::pass($this->id(), 'protocolSupportEnumeration maps to valid SAML2 protocol.');
        }

        return RuleResult::fail(
            $this->id(),
            'protocolSupportEnumeration maps to valid SAML2 protocol.',
            'Type: ' . $entity->type,
        );
    }
}
