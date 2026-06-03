<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Oidc;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class O01_RedirectUriHttps implements MetadataRule
{
    public function id(): string               { return 'O01'; }
    public function name(): string             { return 'OIDC Redirect URI HTTPS'; }
    public function group(): string            { return 'oidc'; }
    public function appliesTo(): array         { return ['oidc']; }
    public function defaultSeverity(): string  { return 'error'; }
    public function specUrl(): string          { return 'https://openid.net/specs/openid-connect-core-1_0.html#AuthRequest'; }

    public function description(): string
    {
        return 'All redirect_uris must use the HTTPS scheme (or http://localhost for development).';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $cfg = $entity->oidcConfig;

        if (! $cfg || empty($cfg->redirect_uris)) {
            return RuleResult::fail($this->id(), 'No redirect_uris configured.', 'At least one redirect URI is required.');
        }

        foreach ($cfg->redirect_uris as $uri) {
            if (! str_starts_with($uri, 'https://') && ! str_starts_with($uri, 'http://localhost')) {
                return RuleResult::fail(
                    $this->id(),
                    'Redirect URI must use HTTPS scheme (or http://localhost).',
                    "Invalid URI: {$uri}",
                );
            }
        }

        return RuleResult::pass($this->id(), 'All redirect_uris use HTTPS.');
    }
}
