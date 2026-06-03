<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SystemPreference;
use Illuminate\Database\Seeder;

class SystemPreferencesSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // ── General ───────────────────────────────────────────────────────
            ['key' => 'app_name',               'value' => 'Federation Manager',         'type' => 'string',   'label' => 'Application name',               'description' => null,                                          'category' => 'general', 'is_public' => false],
            ['key' => 'app_url',                'value' => env('APP_URL', 'https://registry.example.com'), 'type' => 'url', 'label' => 'Application URL', 'description' => 'Used as [[app_url]] placeholder in email templates. Does not affect application routing.',  'category' => 'general', 'is_public' => false],
            ['key' => 'support_email',          'value' => '',                           'type' => 'email',    'label' => 'Support email',                  'description' => 'Contact address shown on the login page. Leave blank to hide.',  'category' => 'general', 'is_public' => false],
            ['key' => 'cookie_consent_enabled', 'value' => '0',                          'type' => 'boolean',  'label' => 'Show cookie consent banner',     'description' => null,                                          'category' => 'general', 'is_public' => true],
            ['key' => 'cookie_consent_text',    'value' => 'This site uses cookies for session management only.', 'type' => 'textarea', 'label' => 'Cookie consent text', 'description' => null,                          'category' => 'general', 'is_public' => true],
            ['key' => 'supported_languages',    'value' => 'en,ro', 'type' => 'string',  'label' => 'Supported UI languages', 'description' => 'Comma-separated language codes available in the language switcher', 'category' => 'general', 'is_public' => false],
            ['key' => 'default_language',       'value' => 'en',    'type' => 'string',  'label' => 'Default UI language',    'description' => 'Applied when no user preference is set',                            'category' => 'general', 'is_public' => false],

            // ── Page ──────────────────────────────────────────────────────────
            ['key' => 'footer_text',           'value' => '',  'type' => 'string',   'label' => 'Footer custom text',     'description' => 'Additional text shown in footer',        'category' => 'page', 'is_public' => false],
            ['key' => 'header_title_prefix',   'value' => '',  'type' => 'string',   'label' => 'Header title prefix',    'description' => 'Prefix added before page title in browser tab', 'category' => 'page', 'is_public' => false],

            // ── Mail ──────────────────────────────────────────────────────────
            ['key' => 'mail_from_name',    'value' => 'Federation Manager',                    'type' => 'string',   'label' => 'Mail sender name',    'description' => null,                                     'category' => 'mail', 'is_public' => false],
            ['key' => 'mail_from_address', 'value' => env('MAIL_FROM_ADDRESS', ''),            'type' => 'email',    'label' => 'Mail sender address', 'description' => null,                                     'category' => 'mail', 'is_public' => false],
            ['key' => 'mail_signature',    'value' => "Federation Manager\nhttps://registry.example.com", 'type' => 'textarea', 'label' => 'Mail signature', 'description' => 'Appended to all notification emails', 'category' => 'mail', 'is_public' => false],

            // ── Authentication ────────────────────────────────────────────────
            ['key' => 'default_saml_role',      'value' => 'Guest', 'type' => 'string',  'label' => 'Default role for new SAML users', 'description' => 'Role assigned on first SAML login',          'category' => 'authn', 'is_public' => false],
            ['key' => 'session_timeout_minutes','value' => '120',   'type' => 'integer', 'label' => 'Session timeout (minutes)',       'description' => null,                                          'category' => 'authn', 'is_public' => false],
            ['key' => 'max_login_attempts',     'value' => '5',     'type' => 'integer', 'label' => 'Max login attempts before lockout','description' => null,                                         'category' => 'authn', 'is_public' => false],

            // ── Federation ────────────────────────────────────────────────────
            ['key' => 'pending_membership_expiry_days', 'value' => '7', 'type' => 'integer', 'label' => 'Pending membership expiry (days)', 'description' => 'Number of days before a pending federation membership request expires automatically. Expired requests are auto-rejected. Accepted values: 1–7.', 'category' => 'federation', 'is_public' => false],

            // ── eduGAIN checks ────────────────────────────────────────────────
            ['key' => 'edugain_checks_enabled',       'value' => '0',    'type' => 'boolean', 'label' => 'Enable eduGAIN integration',               'description' => 'Show eduGAIN status data on entity and federation pages', 'category' => 'edugain_checks', 'is_public' => false],
            ['key' => 'edugain_federation_code',      'value' => 'LEAF', 'type' => 'string',  'label' => 'Your eduGAIN federation code',              'description' => 'Used to query eduGAIN APIs. Examples: LEAF, AAF, HAKA, IDEM', 'category' => 'edugain_checks', 'is_public' => false],
            ['key' => 'eccs_check_enabled',           'value' => '1',    'type' => 'boolean', 'label' => 'Show ECCS connectivity status (IdP only)',  'description' => 'Check if IdP properly consumes eduGAIN SP metadata',      'category' => 'edugain_checks', 'is_public' => false],
            ['key' => 'edugain_entity_check_enabled', 'value' => '1',    'type' => 'boolean', 'label' => 'Show entity presence in eduGAIN database',  'description' => null,                                                      'category' => 'edugain_checks', 'is_public' => false],
        ];

        foreach ($defaults as $row) {
            SystemPreference::firstOrCreate(['key' => $row['key']], $row);
        }
    }
}
