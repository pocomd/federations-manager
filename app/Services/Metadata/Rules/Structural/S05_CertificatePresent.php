<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Structural;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class S05_CertificatePresent implements MetadataRule
{
    public function id(): string          { return 'S05'; }
    public function name(): string        { return 'At least one X.509 certificate present'; }
    public function group(): string       { return 'structural'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'At least one X.509 certificate (KeyDescriptor) must be present in the entity metadata.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if ($entity->certificates->isNotEmpty()) {
            return RuleResult::pass(
                $this->id(),
                'At least one X.509 certificate (KeyDescriptor) is present.',
            );
        }

        return RuleResult::fail(
            $this->id(),
            'At least one X.509 certificate (KeyDescriptor) is required.',
            'Certificates found: 0',
        );
    }
}
