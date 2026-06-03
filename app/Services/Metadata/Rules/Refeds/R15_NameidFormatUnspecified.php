<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R15_NameidFormatUnspecified implements MetadataRule
{
    private const NAMEID_UNSPECIFIED = 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified';

    public function id(): string          { return 'R15'; }
    public function name(): string        { return 'NameIDFormat does not include unspecified without reason'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string     { return 'https://wiki.refeds.org/display/FBP/eduGAIN+Baseline+Profile'; }

    public function description(): string
    {
        return 'Advertising NameIDFormat:unspecified leaves the format undefined. Prefer explicit formats such as transient or persistent.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if (! in_array(self::NAMEID_UNSPECIFIED, $entity->nameid_formats ?? [], true)) {
            return RuleResult::pass($this->id(), 'NameIDFormat:unspecified is not advertised.');
        }

        return RuleResult::warning(
            $this->id(),
            'NameIDFormat:unspecified is listed — consider using transient or persistent instead.',
            'Using "unspecified" leaves the NameID format undefined; prefer explicit formats.',
        );
    }
}
