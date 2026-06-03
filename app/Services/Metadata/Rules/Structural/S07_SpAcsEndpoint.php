<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Structural;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class S07_SpAcsEndpoint implements MetadataRule
{
    public function id(): string          { return 'S07'; }
    public function name(): string        { return 'SP has at least one ACS endpoint'; }
    public function group(): string       { return 'structural'; }
    public function appliesTo(): array    { return ['sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'A Service Provider must advertise at least one AssertionConsumerService endpoint. Only applies to SP entities.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $acsEndpoints = $entity->endpoints->where('type', 'acs');

        if ($acsEndpoints->isNotEmpty()) {
            return RuleResult::pass($this->id(), 'SP has at least one AssertionConsumerService endpoint.');
        }

        return RuleResult::fail(
            $this->id(),
            'SP must have at least one AssertionConsumerService endpoint.',
            'ACS endpoints found: 0',
        );
    }
}
