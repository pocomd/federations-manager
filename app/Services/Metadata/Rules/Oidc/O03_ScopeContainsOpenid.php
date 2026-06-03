<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Oidc;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class O03_ScopeContainsOpenid implements MetadataRule
{
    public function id(): string               { return 'O03'; }
    public function name(): string             { return 'OIDC Scope Contains openid'; }
    public function group(): string            { return 'oidc'; }
    public function appliesTo(): array         { return ['oidc']; }
    public function defaultSeverity(): string  { return 'error'; }
    public function specUrl(): string          { return 'https://openid.net/specs/openid-connect-core-1_0.html#ScopeClaims'; }

    public function description(): string
    {
        return 'The scopes list must contain "openid" — it is required for OIDC authentication flows.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $cfg = $entity->oidcConfig;

        if (! $cfg) {
            return RuleResult::fail($this->id(), 'No OIDC configuration found.', 'Create an OIDC configuration for this entity.');
        }

        if (in_array('openid', $cfg->scopes ?? [], true)) {
            return RuleResult::pass($this->id(), 'Scope "openid" is present.');
        }

        return RuleResult::fail(
            $this->id(),
            'Scope "openid" is missing.',
            'The "openid" scope is required for OIDC authentication flows.',
        );
    }
}
