<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Test double for SamlServiceInterface.
 *
 * Bind in tests before hitting SAML routes:
 *
 *   app()->bind(SamlServiceInterface::class, fn() => new FakeSamlService([
 *       'mail'        => ['operator@university.ie'],
 *       'displayName' => ['Test Operator'],
 *   ]));
 *
 * An empty attributes array means "not authenticated".
 */
class FakeSamlService implements SamlServiceInterface
{
    /**
     * @param array<string, list<string>> $attributes  Empty = not authenticated.
     */
    public function __construct(
        private readonly array $attributes = [],
    ) {}

    public function isAuthenticated(): bool
    {
        return !empty($this->attributes);
    }

    public function getLoginUrl(): string
    {
        return '/simplesaml/saml/login?fake=1';
    }

    public function getLogoutUrl(string $returnTo): string
    {
        return $returnTo;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
