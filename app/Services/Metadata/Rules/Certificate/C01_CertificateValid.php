<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Certificate;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class C01_CertificateValid implements MetadataRule
{
    public function id(): string          { return 'C01'; }
    public function name(): string        { return 'At least one valid X.509 PEM certificate present'; }
    public function group(): string       { return 'certificate'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'At least one X.509 certificate (KeyDescriptor) must be present and parseable as a valid PEM certificate.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if ($entity->certificates->isNotEmpty()) {
            return RuleResult::pass($this->id(), 'At least one X.509 certificate is present.');
        }

        return RuleResult::fail(
            $this->id(),
            'At least one X.509 certificate (KeyDescriptor) is required.',
            'Certificates found: 0',
        );
    }
}
