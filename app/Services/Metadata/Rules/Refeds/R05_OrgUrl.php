<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R05_OrgUrl implements MetadataRule
{
    public function id(): string          { return 'R05'; }
    public function name(): string        { return 'md:OrganizationURL in English present'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string     { return 'https://wiki.refeds.org/display/FBP/eduGAIN+Baseline+Profile'; }

    public function description(): string
    {
        return 'An English md:OrganizationURL is recommended by the eduGAIN Baseline Profile.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $present = $entity->uiInfo->where('field', 'org_url')->where('lang', 'en')->isNotEmpty();

        if ($present) {
            return RuleResult::pass($this->id(), 'md:OrganizationURL in English is present.');
        }

        return RuleResult::warning(
            $this->id(),
            'md:OrganizationURL in English is recommended.',
            'Add an org_url ui_info entry with lang="en".',
        );
    }
}
