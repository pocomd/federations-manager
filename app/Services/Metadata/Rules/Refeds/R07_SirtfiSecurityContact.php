<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Models\EntityAttribute;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R07_SirtfiSecurityContact implements MetadataRule
{

    public function id(): string          { return 'R07'; }
    public function name(): string        { return 'SIRTFI: security contact present when SIRTFI asserted'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://refeds.org/sirtfi'; }

    public function description(): string
    {
        return 'When the SIRTFI assurance profile is asserted, a md:ContactPerson with contactType="security" must be present.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $entityCategories = $entity->attributes
            ->where('attribute_name', 'entity_category')
            ->pluck('attribute_value')
            ->toArray();

        $hasSirtfi = in_array(EntityAttribute::URI_SIRTFI, $entityCategories, true)
            || in_array(EntityAttribute::URI_SIRTFI2, $entityCategories, true);

        if (! $hasSirtfi) {
            return RuleResult::notApplicable($this->id());
        }

        if ($entity->contacts->where('type', 'security')->isNotEmpty()) {
            return RuleResult::pass($this->id(), 'SIRTFI asserted and security contact is present.');
        }

        return RuleResult::fail(
            $this->id(),
            'SIRTFI asserted but no security contact (contactType="security") found.',
            'REFEDS SIRTFI requires a security contact.',
        );
    }
}
