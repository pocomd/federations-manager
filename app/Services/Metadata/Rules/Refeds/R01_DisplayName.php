<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R01_DisplayName implements MetadataRule
{
    public function id(): string          { return 'R01'; }
    public function name(): string        { return 'mdui:DisplayName in English present'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string     { return 'https://wiki.refeds.org/display/FBP/eduGAIN+Baseline+Profile'; }

    public function description(): string
    {
        return 'An English mdui:DisplayName is required by the eduGAIN Baseline Profile for human-readable entity identification.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $present = $entity->uiInfo->where('field', 'display_name')->where('lang', 'en')->isNotEmpty();

        if ($present) {
            return RuleResult::pass($this->id(), 'mdui:DisplayName in English is present.');
        }

        return RuleResult::warning(
            $this->id(),
            'mdui:DisplayName in English is recommended (eduGAIN requirement).',
            'Add a display_name ui_info entry with lang="en".',
        );
    }
}
