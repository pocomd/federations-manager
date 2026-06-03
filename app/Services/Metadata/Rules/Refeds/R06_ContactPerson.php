<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R06_ContactPerson implements MetadataRule
{
    public function id(): string          { return 'R06'; }
    public function name(): string        { return 'At least one md:ContactPerson (technical or support)'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string     { return 'https://wiki.refeds.org/display/FBP/eduGAIN+Baseline+Profile'; }

    public function description(): string
    {
        return 'At least one md:ContactPerson of type "technical" or "support" is recommended by the eduGAIN Baseline Profile.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $hasContact = $entity->contacts->whereIn('type', ['technical', 'support'])->isNotEmpty();

        if ($hasContact) {
            return RuleResult::pass($this->id(), 'At least one md:ContactPerson (technical or support) is present.');
        }

        return RuleResult::warning(
            $this->id(),
            'At least one md:ContactPerson (technical or support) is recommended.',
            'Add a contact with type="technical" or type="support".',
        );
    }
}
