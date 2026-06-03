<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Models\EntityAttribute;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R09_RsCanonicalUri implements MetadataRule
{

    public function id(): string          { return 'R09'; }
    public function name(): string        { return 'R&S entity category URI is the canonical REFEDS URI'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://refeds.org/category/research-and-scholarship'; }

    public function description(): string
    {
        return 'If the Research & Scholarship entity category is used, it must be the canonical REFEDS URI (http, not https).';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $entityCategories = $entity->attributes
            ->where('attribute_name', 'entity_category')
            ->pluck('attribute_value')
            ->toArray();

        $rsLike = array_filter($entityCategories, fn($cat) => str_contains($cat, 'research-and-scholarship'));

        if (empty($rsLike)) {
            return RuleResult::notApplicable($this->id());
        }

        $nonCanonical = array_filter($rsLike, fn($cat) => $cat !== EntityAttribute::URI_RS);

        if (empty($nonCanonical)) {
            return RuleResult::pass($this->id(), 'R&S entity category URI is canonical.');
        }

        return RuleResult::fail(
            $this->id(),
            'Non-canonical R&S URI detected — must use: ' . EntityAttribute::URI_RS,
            'Found: ' . implode(', ', $nonCanonical),
        );
    }
}
