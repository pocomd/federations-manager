<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Federation Registration Authority
    |--------------------------------------------------------------------------
    | The canonical URI that identifies this federation operator.
    | Used as the default registrationAuthority in mdrpi:RegistrationInfo.
    */
    'registration_authority' => env('FEDERATION_REGISTRATION_AUTHORITY', ''),

    /*
    |--------------------------------------------------------------------------
    | xmlsectool Binary Path
    |--------------------------------------------------------------------------
    | Absolute path to the xmlsectool executable (Java CLI, v3.0.0+).
    */
    'xmlsectool_path' => env('XMLSECTOOL_PATH', '/usr/local/bin/xmlsectool'),

    /*
    |--------------------------------------------------------------------------
    | Signing Drivers — active backends
    |--------------------------------------------------------------------------
    | Each driver is enabled/disabled via an ENV flag. FILE_SIGNING_IS_ACTIVE
    | should always be true; SOFTHSM_SIGNING_IS_ACTIVE is set by the installer
    | when softhsm2 and opensc packages are detected.
    */
    'signing_drivers' => [
        'FILE_SIGNING_IS_ACTIVE'    => env('FILE_SIGNING_IS_ACTIVE', true),
        'SOFTHSM_SIGNING_IS_ACTIVE' => env('SOFTHSM_SIGNING_IS_ACTIVE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | SoftHSM2 / PKCS#11
    |--------------------------------------------------------------------------
    */
    'pkcs11_library'  => env('PKCS11_LIBRARY', '/usr/lib/softhsm/libsofthsm2.so'),
    'softhsm2_conf'   => env('SOFTHSM2_CONF', '/etc/softhsm/softhsm2.conf'),
    'softhsm_pin'     => env('JAGGER_HSM_PIN'),
    'softhsm_so_pin'  => env('JAGGER_HSM_SO_PIN'),

    /*
    |--------------------------------------------------------------------------
    | Import from Jagger
    |--------------------------------------------------------------------------
    | Set JAGGER_IMPORT_ENABLED=true to expose the "Import from Jagger" feature.
    | The feature is also restricted to Admin users regardless of this flag.
    */
    'import_enabled' => env('JAGGER_IMPORT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Internationalisation (i18n)
    |--------------------------------------------------------------------------
    | Set I18N_ENABLED=false to hide the language switcher and lock the UI to
    | English regardless of user preferences or session locale.
    */
    'i18n_enabled' => env('I18N_ENABLED', true),

];
