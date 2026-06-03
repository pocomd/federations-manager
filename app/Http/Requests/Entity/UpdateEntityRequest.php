<?php

declare(strict_types=1);

namespace App\Http\Requests\Entity;

use App\Models\EntityAttribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── mdui:UIInfo ───────────────────────────────────────────────
            'name_en'            => ['required', 'string', 'max:255'],
            'name_native'        => ['nullable', 'string', 'max:255'],
            'name_lang'          => ['nullable', 'string', 'max:10'],
            'description_en'     => ['required', 'string', 'max:1024'],
            'description_native' => ['nullable', 'string', 'max:1024'],
            'information_url_en' => ['nullable', 'url', 'max:1024'],
            'privacy_url_en'     => ['nullable', 'url', 'max:1024'],
            'logo_url'           => ['nullable', 'url', 'max:1024'],
            'logo_height'        => ['nullable', 'integer', 'min:1', 'max:200'],
            'logo_width'         => ['nullable', 'integer', 'min:1', 'max:400'],

            // ── md:Organization ───────────────────────────────────────────
            'org_name_en'         => ['required', 'string', 'max:255'],
            'org_name_native'     => ['nullable', 'string', 'max:255'],
            'org_display_name_en' => ['required', 'string', 'max:255'],
            'org_url_en'          => ['required', 'url', 'max:1024'],

            // ── md:ContactPerson ──────────────────────────────────────────
            'contact_technical_email' => ['nullable', 'email', 'max:255'],
            'contact_support_email'   => ['nullable', 'email', 'max:255'],
            'contact_security_email'  => ['nullable', 'email', 'max:255'],

            // ── REFEDS attributes ─────────────────────────────────────────
            'entity_categories'   => ['nullable', 'array'],
            'entity_categories.*' => ['string', 'url', 'max:255'],
            'assurance_profiles'   => ['nullable', 'array'],
            'assurance_profiles.*' => ['string', 'url', 'max:255'],
            'sirtfi'  => ['boolean'],
            'edugain' => ['boolean'],

            // ── IdP-specific ──────────────────────────────────────────────
            'scope'             => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9\-\.]+)?[a-z0-9]$/i'],
            'nameid_formats'    => ['nullable', 'array'],
            'nameid_formats.*'  => ['string'],
            'sso_http_post'     => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'sso_http_redirect' => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'sso_soap'          => ['nullable', 'url', 'max:1024', 'starts_with:https://'],

            // ── SP-specific ───────────────────────────────────────────────
            'acs_http_post'     => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'acs_http_redirect' => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'acs_paos'          => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'sp_want_authn_requests_signed' => ['boolean'],
            'sp_want_assertions_signed'     => ['boolean'],
            'requested_attributes'                    => ['nullable', 'array'],
            'requested_attributes.*.name'             => ['required', 'string', 'max:255'],
            'requested_attributes.*.friendly_name'    => ['nullable', 'string', 'max:255'],
            'requested_attributes.*.is_required'      => ['boolean'],

            // ── SLO ───────────────────────────────────────────────────────
            'slo_http_post'     => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'slo_http_redirect' => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'slo_soap'          => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $entity = $this->route('entity');
            $type   = $entity?->type;

            if ($type === 'idp') {
                if (empty($this->input('sso_http_post')) && empty($this->input('sso_http_redirect'))) {
                    $validator->errors()->add('sso_http_post',
                        'Identity Provider must retain at least one SingleSignOnService endpoint.');
                }
            }

            if ($type === 'sp') {
                if (empty($this->input('acs_http_post'))) {
                    $validator->errors()->add('acs_http_post',
                        'Service Provider must retain at least one AssertionConsumerService (HTTP-POST).');
                }
            }

            if ($this->boolean('sirtfi') && empty($this->input('contact_security_email'))) {
                $validator->errors()->add('contact_security_email',
                    'Security contact required when SIRTFI is asserted.');
            }

            $categories = $this->input('entity_categories', []);
            if (in_array(EntityAttribute::URI_COCO_V2, $categories)
                && empty($this->input('privacy_url_en'))) {
                $validator->errors()->add('privacy_url_en',
                    'PrivacyStatementURL required when Code of Conduct v2 is asserted.');
            }
        });
    }
}
