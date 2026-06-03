<?php

declare(strict_types=1);

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

uses(Tests\TestCase::class)->in('Unit');

/**
 * Generate a self-signed RSA-2048 PEM certificate for use in tests.
 * Locates openssl.cnf relative to PHP_BINARY (required on Windows).
 * Shared across Unit and Feature test suites — defined once to avoid redeclaration.
 */
/**
 * Create an in-memory EntityUiInfo stub (no DB).
 */
function makeUiInfo(string $field, string $lang, string $value, ?int $logoHeight = null, ?int $logoWidth = null): \App\Models\EntityUiInfo
{
    $ui = new \App\Models\EntityUiInfo();
    $ui->field       = $field;
    $ui->lang        = $lang;
    $ui->value       = $value;
    $ui->logo_height = $logoHeight;
    $ui->logo_width  = $logoWidth;
    return $ui;
}

/**
 * Create an in-memory EntityContact stub (no DB).
 */
function makeContact(string $type, string $email, ?string $givenName = null, ?string $surName = null): \App\Models\EntityContact
{
    $contact             = new \App\Models\EntityContact();
    $contact->type       = $type;
    $contact->email      = $email;
    $contact->given_name = $givenName;
    $contact->sur_name   = $surName;
    return $contact;
}

/**
 * Create an in-memory EntityEndpoint stub (no DB).
 */
function makeEndpoint(string $type, string $binding, string $location, ?int $index = null, bool $isDefault = false): \App\Models\EntityEndpoint
{
    $ep             = new \App\Models\EntityEndpoint();
    $ep->type       = $type;
    $ep->binding    = $binding;
    $ep->location   = $location;
    $ep->index      = $index;
    $ep->is_default = $isDefault;
    return $ep;
}

/**
 * Create an in-memory EntityAttribute stub (no DB).
 */
function makeAttribute(string $name, string $value): \App\Models\EntityAttribute
{
    $attr                  = new \App\Models\EntityAttribute();
    $attr->attribute_name  = $name;
    $attr->attribute_value = $value;
    return $attr;
}

/**
 * Generate a self-signed RSA-2048 PEM certificate for use in tests.
 * Locates openssl.cnf relative to PHP_BINARY (required on Windows).
 * Shared across Unit and Feature test suites — defined once to avoid redeclaration.
 */
function generateSelfSignedPem(): string
{
    $opensslConf = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR
                   . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';

    $config = file_exists($opensslConf) ? ['config' => $opensslConf] : [];

    $key  = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048] + $config);
    $csr  = openssl_csr_new(['CN' => 'Test', 'O' => 'TestOrg', 'C' => 'IE'], $key, ['digest_alg' => 'sha256'] + $config);
    $x509 = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256'] + $config);
    openssl_x509_export($x509, $pem);

    return $pem;
}
