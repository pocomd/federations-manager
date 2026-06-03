<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Certificate;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class C05_SignatureAlgorithm implements MetadataRule
{
    private const WEAK_ALGORITHMS = ['sha1WithRSAEncryption', 'md5WithRSAEncryption'];

    public function id(): string          { return 'C05'; }
    public function name(): string        { return 'Certificate uses SHA-256 or stronger signature algorithm'; }
    public function group(): string       { return 'certificate'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'Certificate signature algorithms weaker than SHA-256 (e.g. SHA-1, MD5) are deprecated and should be replaced.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if ($entity->certificates->isEmpty()) {
            return RuleResult::notApplicable($this->id());
        }

        $weak = $entity->certificates->filter(
            fn($cert) => in_array($cert->signature_algorithm ?? '', self::WEAK_ALGORITHMS, true)
        );

        if ($weak->isEmpty()) {
            return RuleResult::pass($this->id(), 'All certificates use SHA-256 or stronger signature algorithms.');
        }

        $detail = $weak->map(fn($cert) => "{$cert->signature_algorithm} (Subject: {$cert->subject})")->implode('; ');

        return RuleResult::warning(
            $this->id(),
            'One or more certificates use a weak signature algorithm — upgrade to SHA-256 or stronger.',
            $detail,
        );
    }
}
