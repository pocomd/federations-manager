<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Abstraction over the SimpleSAMLphp SP session.
 *
 * The production implementation wraps \SimpleSAML\Auth\Simple.
 * A fake implementation is bound in the service container during testing
 * so that tests do not require a running SimpleSAMLphp installation.
 */
interface SamlServiceInterface
{
    /**
     * Whether the current PHP session has an active SimpleSAMLphp SP auth state.
     */
    public function isAuthenticated(): bool;

    /**
     * Return the URL that initiates SSO against the configured IdP.
     * Calling this may redirect the browser directly — always return the URL
     * and let the controller issue the redirect.
     */
    public function getLoginUrl(): string;

    /**
     * Return the URL that initiates global logout (SLO) from the IdP.
     *
     * @param string $returnTo  URL to redirect the user to after logout
     */
    public function getLogoutUrl(string $returnTo): string;

    /**
     * Return all SP attributes from the current SimpleSAMLphp session.
     *
     * Attribute names follow the SAML2 OID or friendly-name conventions
     * used by the IdP (e.g. 'mail', 'displayName', 'eppn').
     *
     * @return array<string, list<string>>
     */
    public function getAttributes(): array;
}
