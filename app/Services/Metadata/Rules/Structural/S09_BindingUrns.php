<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Structural;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class S09_BindingUrns implements MetadataRule
{
    private const VALID_BINDINGS = [
        'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact',
        'urn:oasis:names:tc:SAML:2.0:bindings:SOAP',
        'urn:oasis:names:tc:SAML:2.0:bindings:PAOS',
        'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST-SimpleSign',
    ];

    public function id(): string          { return 'S09'; }
    public function name(): string        { return 'All endpoint binding URIs are valid SAML2 identifiers'; }
    public function group(): string       { return 'structural'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'Every endpoint binding attribute must be one of the six defined SAML 2.0 binding URNs.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $invalid = $entity->endpoints->filter(
            fn($ep) => ! in_array($ep->binding, self::VALID_BINDINGS, true)
        );

        if ($invalid->isEmpty()) {
            return RuleResult::pass($this->id(), 'All endpoint binding URIs are valid SAML2 identifiers.');
        }

        $detail = $invalid->map(fn($ep) => "{$ep->binding} ({$ep->type})")->implode('; ');

        return RuleResult::fail(
            $this->id(),
            'All endpoint binding URIs must be valid SAML2 identifiers.',
            'Invalid bindings: ' . $detail,
        );
    }
}
