<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Structural;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class S10_HttpsEndpoints implements MetadataRule
{
    public function id(): string          { return 'S10'; }
    public function name(): string        { return 'All endpoint Location values use HTTPS'; }
    public function group(): string       { return 'structural'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'Every endpoint Location URL must use HTTPS to protect assertions and tokens in transit.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $nonHttps = $entity->endpoints
            ->pluck('location')
            ->filter(fn($url) => ! str_starts_with((string) $url, 'https://'))
            ->values();

        if ($nonHttps->isEmpty()) {
            return RuleResult::pass($this->id(), 'All endpoint Location values use HTTPS.');
        }

        return RuleResult::fail(
            $this->id(),
            'All endpoint Location values must use HTTPS.',
            'Non-HTTPS endpoints: ' . $nonHttps->implode(', '),
        );
    }
}
