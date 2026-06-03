<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | SAML Login Enabled
    |--------------------------------------------------------------------------
    | Set SAML_ENABLED=true in .env to show the institutional SSO login button.
    | When false the SAML login option is hidden; local password login still works.
    */
    'enabled' => env('SAML_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | SimpleSAMLphp Base URL Path
    |--------------------------------------------------------------------------
    | The base URL under which the SimpleSAMLphp web interface is served.
    | Must end with a trailing slash.
    | Example: '/simplesaml/' or 'https://auth.example.com/simplesaml/'
    */
    'baseurlpath' => env('SAML2_BASEURLPATH', '/simplesaml/'),

    /*
    |--------------------------------------------------------------------------
    | Auth Source
    |--------------------------------------------------------------------------
    | The SimpleSAMLphp authentication source name defined in
    | your SimpleSAMLphp installation's config/authsources.php.
    */
    'auth_source' => env('SAML2_AUTH_SOURCE', 'default-sp'),

    /*
    |--------------------------------------------------------------------------
    | SP Entity ID
    |--------------------------------------------------------------------------
    | The SAML2 entityID for this SP (this Laravel application).
    | Must match the entityID registered in the IdP's metadata.
    */
    'sp_entity_id' => env('SAML2_SP_ENTITY_ID', 'https://registry.example.com/saml2/metadata'),

    /*
    |--------------------------------------------------------------------------
    | IdP Metadata URL
    |--------------------------------------------------------------------------
    | URL of the IdP metadata XML document. SimpleSAMLphp uses this to
    | configure trust with the IdP (loaded into metadata/saml20-idp-remote.php).
    */
    'idp_metadata_url' => env('SAML2_IDP_METADATA_URL'),

    /*
    |--------------------------------------------------------------------------
    | IdP Connection Details
    |--------------------------------------------------------------------------
    | These can alternatively be provided as discrete values instead of loading
    | the full IdP metadata document.
    */
    'idp_entity_id' => env('SAML2_IDP_ENTITY_ID'),
    'idp_sso_url'   => env('SAML2_IDP_SSO_URL'),
    'idp_sls_url'   => env('SAML2_IDP_SLS_URL'),
    'idp_cert'      => env('SAML2_IDP_CERT'),

    /*
    |--------------------------------------------------------------------------
    | Attribute Mapping
    |--------------------------------------------------------------------------
    | Map IdP SAML attribute names to the fields used by findOrCreateUser().
    | Keys are the internal field names; values are SAML attribute names.
    */
    'attributes' => [
        'email'        => env('SAML2_ATTR_MAIL',         'mail'),
        'name'         => env('SAML2_ATTR_DISPLAY_NAME', 'displayName'),
        'given_name'   => env('SAML2_ATTR_GIVEN_NAME',   'givenName'),
        'surname'      => env('SAML2_ATTR_SURNAME',       'sn'),
        'eppn'         => env('SAML2_ATTR_EPPN',          'eduPersonPrincipalName'),
    ],

];
