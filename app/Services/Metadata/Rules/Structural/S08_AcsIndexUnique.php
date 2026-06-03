<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Structural;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class S08_AcsIndexUnique implements MetadataRule
{
    public function id(): string          { return 'S08'; }
    public function name(): string        { return 'ACS endpoint index values are unique'; }
    public function group(): string       { return 'structural'; }
    public function appliesTo(): array    { return ['sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'Each AssertionConsumerService endpoint index attribute must be unique within an SP. Duplicate index values cause undefined IdP behaviour. Only applies to SP entities.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $acsIndexes = $entity->endpoints
            ->where('type', 'acs')
            ->pluck('index')
            ->filter(fn($i) => $i !== null);

        $hasDuplicates = $acsIndexes->count() !== $acsIndexes->unique()->count();

        if (! $hasDuplicates) {
            return RuleResult::pass($this->id(), 'ACS endpoint index values are unique.');
        }

        return RuleResult::fail(
            $this->id(),
            'ACS endpoint index values must be unique.',
            'Index values: ' . $acsIndexes->implode(', '),
        );
    }
}
