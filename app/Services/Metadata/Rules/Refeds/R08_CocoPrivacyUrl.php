<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Models\EntityAttribute;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R08_CocoPrivacyUrl implements MetadataRule
{

    public function id(): string          { return 'R08'; }
    public function name(): string        { return 'CoCo v2: privacy statement URL present when CoCo asserted'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://refeds.org/category/code-of-conduct/v2'; }

    public function description(): string
    {
        return 'When the REFEDS Code of Conduct v2 entity category is asserted, a mdui:PrivacyStatementURL in English must be present.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $entityCategories = $entity->attributes
            ->where('attribute_name', 'entity_category')
            ->pluck('attribute_value')
            ->toArray();

        if (! in_array(EntityAttribute::URI_COCO_V2, $entityCategories, true)) {
            return RuleResult::notApplicable($this->id());
        }

        $hasPrivacyUrl = $entity->uiInfo->where('field', 'privacy_url')->where('lang', 'en')->isNotEmpty();

        if ($hasPrivacyUrl) {
            return RuleResult::pass($this->id(), 'CoCo v2 asserted and mdui:PrivacyStatementURL is present.');
        }

        return RuleResult::fail(
            $this->id(),
            'REFEDS Code of Conduct v2 asserted but no mdui:PrivacyStatementURL found.',
            'CoCo v2 requires a PrivacyStatementURL — add a privacy_url ui_info entry with lang="en".',
        );
    }
}
