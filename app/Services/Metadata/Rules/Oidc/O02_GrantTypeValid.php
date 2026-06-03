<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Oidc;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class O02_GrantTypeValid implements MetadataRule
{
    private const ALLOWED = ['authorization_code', 'client_credentials', 'refresh_token'];

    public function id(): string               { return 'O02'; }
    public function name(): string             { return 'OIDC Grant Type Valid'; }
    public function group(): string            { return 'oidc'; }
    public function appliesTo(): array         { return ['oidc']; }
    public function defaultSeverity(): string  { return 'error'; }
    public function specUrl(): string          { return 'https://openid.net/specs/openid-connect-core-1_0.html'; }

    public function description(): string
    {
        return 'grant_types must be a non-empty subset of: authorization_code, client_credentials, refresh_token.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $cfg = $entity->oidcConfig;

        if (! $cfg || empty($cfg->grant_types)) {
            return RuleResult::fail($this->id(), 'No grant_types configured.', 'At least one grant type is required.');
        }

        $invalid = array_diff($cfg->grant_types, self::ALLOWED);

        if (! empty($invalid)) {
            return RuleResult::fail(
                $this->id(),
                'Invalid grant type(s) detected.',
                'Unsupported: ' . implode(', ', $invalid) . '. Allowed: ' . implode(', ', self::ALLOWED),
            );
        }

        return RuleResult::pass($this->id(), 'All grant_types are valid.');
    }
}
