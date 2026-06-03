<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R03_OrgName implements MetadataRule
{
    public function id(): string          { return 'R03'; }
    public function name(): string        { return 'md:OrganizationName in English present'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string     { return 'https://wiki.refeds.org/display/FBP/eduGAIN+Baseline+Profile'; }

    public function description(): string
    {
        return 'An English md:OrganizationName is recommended by the eduGAIN Baseline Profile.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $present = $entity->uiInfo->where('field', 'org_name')->where('lang', 'en')->isNotEmpty();

        if ($present) {
            return RuleResult::pass($this->id(), 'md:OrganizationName in English is present.');
        }

        return RuleResult::warning(
            $this->id(),
            'md:OrganizationName in English is recommended.',
            'Add an org_name ui_info entry with lang="en".',
        );
    }
}
