<?php

declare(strict_types=1);

namespace App\Services\Auth;

use RuntimeException;

/**
 * Production SimpleSAMLphp SP session wrapper.
 *
 * Requires `simplesamlphp/simplesamlphp` to be installed and configured:
 *   composer require simplesamlphp/simplesamlphp
 *
 * SimpleSAMLphp configuration lives in:
 *   vendor/simplesamlphp/simplesamlphp/config/config.php
 *   vendor/simplesamlphp/simplesamlphp/metadata/saml20-idp-remote.php
 *
 * The SP entity ID and IdP metadata URL are read from config/simplesamlphp.php
 * which maps to SAML2_* environment variables.
 *
 * In production, SimpleSAMLphp is typically also deployed as a standalone web
 * application at /simplesaml/ — the Laravel app uses the same PHP session to
 * read SP attributes via \SimpleSAML\Auth\Simple::getAttributes().
 */
class SamlService implements SamlServiceInterface
{
    private readonly string $authSource;

    public function __construct()
    {
        $this->authSource = config('simplesamlphp.auth_source', 'default-sp');
    }

    public function isAuthenticated(): bool
    {
        return $this->getAuth()->isAuthenticated();
    }

    public function getLoginUrl(): string
    {
        // SimpleSAMLphp accepts a ReturnTo parameter so the user lands back
        // on the Laravel callback URL after the IdP exchanges the assertion.
        $callbackUrl = route('saml.acs');

        return config('simplesamlphp.baseurlpath') . 'module.php/core/as_login'
            . '?AuthId=' . urlencode($this->authSource)
            . '&ReturnTo=' . urlencode($callbackUrl);
    }

    public function getLogoutUrl(string $returnTo): string
    {
        return config('simplesamlphp.baseurlpath') . 'module.php/core/as_logout'
            . '?AuthId=' . urlencode($this->authSource)
            . '&ReturnTo=' . urlencode($returnTo);
    }

    public function getAttributes(): array
    {
        return $this->getAuth()->getAttributes();
    }

    /**
     * Instantiate the SimpleSAMLphp Auth\Simple object for the configured SP.
     *
     * @throws RuntimeException when simplesamlphp/simplesamlphp is not installed.
     */
    private function getAuth(): \SimpleSAML\Auth\Simple
    {
        if (!class_exists(\SimpleSAML\Auth\Simple::class)) {
            throw new RuntimeException(
                'SimpleSAMLphp is not installed. '
                . 'Run: composer require simplesamlphp/simplesamlphp'
            );
        }

        return new \SimpleSAML\Auth\Simple($this->authSource);
    }
}
