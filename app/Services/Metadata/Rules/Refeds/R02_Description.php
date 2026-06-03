<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R02_Description implements MetadataRule
{
    public function id(): string          { return 'R02'; }
    public function name(): string        { return 'mdui:Description in English present'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string     { return 'https://wiki.refeds.org/display/FBP/eduGAIN+Baseline+Profile'; }

    public function description(): string
    {
        return 'An English mdui:Description is required by the eduGAIN Baseline Profile.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $present = $entity->uiInfo->where('field', 'description')->where('lang', 'en')->isNotEmpty();

        if ($present) {
            return RuleResult::pass($this->id(), 'mdui:Description in English is present.');
        }

        return RuleResult::warning(
            $this->id(),
            'mdui:Description in English is recommended (eduGAIN requirement).',
            'Add a description ui_info entry with lang="en".',
        );
    }
}
