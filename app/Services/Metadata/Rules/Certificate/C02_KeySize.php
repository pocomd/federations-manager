<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Certificate;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class C02_KeySize implements MetadataRule
{
    public function id(): string          { return 'C02'; }
    public function name(): string        { return 'Certificate key size meets minimum requirements'; }
    public function group(): string       { return 'certificate'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'RSA keys must be at least 2048 bits; EC keys must be at least 256 bits.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if ($entity->certificates->isEmpty()) {
            return RuleResult::notApplicable($this->id());
        }

        $failures = [];

        foreach ($entity->certificates as $cert) {
            $minBits = str_starts_with($cert->key_algorithm ?? '', 'EC') ? 256 : 2048;

            if (($cert->key_bits ?? 0) < $minBits) {
                $failures[] = "Subject: {$cert->subject} — found {$cert->key_bits} bits, minimum {$minBits} bits";
            }
        }

        if (empty($failures)) {
            return RuleResult::pass($this->id(), 'All certificate key sizes meet minimum requirements.');
        }

        return RuleResult::fail(
            $this->id(),
            'Certificate key size does not meet minimum requirements.',
            implode('; ', $failures),
        );
    }
}
