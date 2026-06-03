<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SystemPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('federation.edit');
    }

    public function rules(): array
    {
        return [
            'app_name'                => ['required', 'string', 'max:100'],
            'app_url'                 => ['required', 'url'],
            'support_email'           => ['nullable', 'email'],
            'cookie_consent_enabled'  => ['boolean'],
            'cookie_consent_text'     => ['nullable', 'string', 'max:500'],
            'footer_text'             => ['nullable', 'string', 'max:255'],
            'header_title_prefix'     => ['nullable', 'string', 'max:50'],
            'mail_from_name'          => ['required', 'string', 'max:100'],
            'mail_from_address'       => ['required', 'email'],
            'mail_signature'          => ['nullable', 'string', 'max:500'],
            'default_saml_role'       => [Rule::in(['Guest', 'Entity Manager', 'Federation Manager', 'Admin'])],
            'session_timeout_minutes' => ['integer', 'min:5', 'max:1440'],
            'max_login_attempts'      => ['integer', 'min:3', 'max:20'],
            'pending_membership_expiry_days' => ['integer', 'min:1', 'max:7'],
            'edugain_checks_enabled'        => ['boolean'],
            'edugain_federation_code'      => ['nullable', 'string', 'max:20', 'alpha_dash'],
            'eccs_check_enabled'           => ['boolean'],
            'edugain_entity_check_enabled' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cookie_consent_enabled'       => $this->boolean('cookie_consent_enabled'),
            'edugain_checks_enabled'       => $this->boolean('edugain_checks_enabled'),
            'eccs_check_enabled'           => $this->boolean('eccs_check_enabled'),
            'edugain_entity_check_enabled' => $this->boolean('edugain_entity_check_enabled'),
        ]);
    }
}
