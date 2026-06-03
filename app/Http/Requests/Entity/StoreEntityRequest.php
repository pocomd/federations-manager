<?php

declare(strict_types=1);

namespace App\Http\Requests\Entity;

use App\Models\EntityAttribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreEntityRequest
 *
 * Validation rules derived from:
 *   - SAML Metadata 2.0 spec (OASIS)
 *   - eduGAIN Metadata Templates (IdP/SP) — GÉANT
 *   - REFEDS Baseline Expectations v1
 *   - REFEDS SIRTFI / CoCo v2 / R&S specifications
 *
 * Fields marked REQUIRED must pass — entity cannot be saved without them.
 * Fields marked RECOMMENDED produce warnings but do not block creation.
 * Recommended fields are still validated for format when present.
 */
class StoreEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate checked in controller
    }

    public function rules(): array
    {
        return [
            // ── CORE ──────────────────────────────────────────────────────
            // entityID maps to @entityID on <md:EntityDescriptor>
            // Must be a valid URI (SAML spec). HTTPS strongly recommended.
            // InCommon no longer accepts URNs for new registrations.
            'entity_id' => [
                'required',
                'string',
                'max:1024',
                'url',
                Rule::unique('entities', 'entity_id'),
            ],

            // type determines IDPSSODescriptor vs SPSSODescriptor
            'type' => ['required', Rule::in(['idp', 'sp'])],

            // eduGAIN export flag — when true, entity will be included in
            // interfederation metadata sent to eduGAIN central hub
            'edugain' => ['boolean'],

            // ── mdui:UIInfo ───────────────────────────────────────────────
            // RECOMMENDED by eduGAIN metadata templates
            'name_en'            => ['required', 'string', 'max:255'],
            'name_native'        => ['nullable', 'string', 'max:255'],
            'name_lang'          => ['nullable', 'string', 'max:10'],   // BCP47 tag e.g. "ro", "de"
            'description_en'     => ['required', 'string', 'max:1024'],
            'description_native' => ['nullable', 'string', 'max:1024'],
            'information_url_en' => ['nullable', 'url', 'max:1024'],
            // privacy_url_en REQUIRED when CoCo v2 is asserted (enforced in controller/service layer)
            'privacy_url_en'     => ['nullable', 'url', 'max:1024'],
            'logo_url'           => ['nullable', 'url', 'max:1024'],
            'logo_height'        => ['nullable', 'integer', 'min:1', 'max:200'],
            'logo_width'         => ['nullable', 'integer', 'min:1', 'max:400'],

            // ── md:Organization ───────────────────────────────────────────
            // RECOMMENDED by eduGAIN metadata templates
            'org_name_en'         => ['required', 'string', 'max:255'],
            'org_name_native'     => ['nullable', 'string', 'max:255'],
            'org_display_name_en' => ['required', 'string', 'max:255'],
            'org_url_en'          => ['required', 'url', 'max:1024'],

            // ── md:ContactPerson ──────────────────────────────────────────
            // SUGGESTED by eduGAIN. REQUIRED for SIRTFI (security contact).
            'contact_technical_email' => ['nullable', 'email', 'max:255'],
            'contact_support_email'   => ['nullable', 'email', 'max:255'],
            'contact_security_email'  => ['nullable', 'email', 'max:255'],

            // ── REFEDS entity attributes ──────────────────────────────────
            // Rendered as saml:Attribute in mdattr:EntityAttributes extension
            // Valid canonical URIs from https://refeds.org/specifications:
            //   http://refeds.org/category/research-and-scholarship
            //   https://refeds.org/category/code-of-conduct/v2
            //   http://refeds.org/category/hide-from-discovery
            //   https://refeds.org/category/anonymous
            //   https://refeds.org/category/pseudonymous
            //   https://refeds.org/category/personalized
            'entity_categories'   => ['nullable', 'array'],
            'entity_categories.*' => ['string', 'url', 'max:255'],

            // REFEDS Assurance Framework (RAF) values
            'assurance_profiles'   => ['nullable', 'array'],
            'assurance_profiles.*' => ['string', 'url', 'max:255'],

            // SIRTFI: when true, adds https://refeds.org/sirtfi to entity attributes
            // and REQUIRES contact_security_email (validated in service layer)
            'sirtfi' => ['boolean'],

            // ── IdP-specific ──────────────────────────────────────────────
            // shibmd:Scope — domain scope for attribute release decisions
            // RECOMMENDED for all IdPs. Should match entityID domain.
            'scope' => ['nullable', 'string', 'max:255',
                'regex:/^[a-z0-9]([a-z0-9\-\.]+)?[a-z0-9]$/i'],

            // NameIDFormat URNs — listed in preferred order in XML
            'nameid_formats'   => ['nullable', 'array'],
            'nameid_formats.*' => ['string', Rule::in([
                'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
                'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
                'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
                'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
            ])],

            // md:SingleSignOnService endpoints — at least one required for IdP
            // Required if type=idp (cross-field rule below)
            'sso_http_post'     => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'sso_http_redirect' => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'sso_soap'          => ['nullable', 'url', 'max:1024', 'starts_with:https://'],

            // ── SP-specific ───────────────────────────────────────────────
            // md:AssertionConsumerService — at least acs_http_post required for SP
            'acs_http_post'     => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'acs_http_redirect' => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'acs_paos'          => ['nullable', 'url', 'max:1024', 'starts_with:https://'],

            // Security attributes on SPSSODescriptor — both recommended true
            'sp_want_authn_requests_signed' => ['boolean'],
            'sp_want_assertions_signed'     => ['boolean'],

            // md:RequestedAttribute elements — attributes the SP needs from the IdP
            // Rendered as <md:RequestedAttribute FriendlyName=... Name=... isRequired=...>
            'requested_attributes'                    => ['nullable', 'array'],
            'requested_attributes.*.name'             => ['required', 'string', 'max:255'],
            'requested_attributes.*.friendly_name'    => ['nullable', 'string', 'max:255'],
            'requested_attributes.*.name_format'      => ['nullable', 'string', 'max:255'],
            'requested_attributes.*.is_required'      => ['boolean'],

            // ── SLO endpoints (both IdP and SP) ──────────────────────────
            'slo_http_post'     => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'slo_http_redirect' => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'slo_soap'          => ['nullable', 'url', 'max:1024', 'starts_with:https://'],

            // ── Certificates ──────────────────────────────────────────────
            // Each entry maps to one <md:KeyDescriptor> in the XML
            'certificates'          => ['required', 'array', 'min:1'],
            'certificates.*.use'    => ['required', Rule::in(['signing', 'encryption', 'both'])],
            'certificates.*.pem'    => ['required', 'string'],

            // ── Federation membership ─────────────────────────────────────
            'federation_ids'   => ['nullable', 'array'],
            'federation_ids.*' => ['exists:federations,id'],
        ];
    }

    /**
     * Cross-field rules: require IdP SSO or SP ACS based on type.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');

            if ($type === 'idp') {
                if (empty($this->input('sso_http_post')) && empty($this->input('sso_http_redirect'))) {
                    $validator->errors()->add(
                        'sso_http_post',
                        'Identity Provider must have at least one SingleSignOnService endpoint (HTTP-POST or HTTP-Redirect).'
                    );
                }
            }

            if ($type === 'sp') {
                if (empty($this->input('acs_http_post'))) {
                    $validator->errors()->add(
                        'acs_http_post',
                        'Service Provider must have at least one AssertionConsumerService endpoint (HTTP-POST required).'
                    );
                }
            }

            // SIRTFI requires security contact
            if ($this->boolean('sirtfi') && empty($this->input('contact_security_email'))) {
                $validator->errors()->add(
                    'contact_security_email',
                    'A security contact email (contactType="security") is required when SIRTFI is asserted.'
                );
            }

            // CoCo v2 requires privacy URL
            $categories = $this->input('entity_categories', []);
            if (in_array(EntityAttribute::URI_COCO_V2, $categories)
                && empty($this->input('privacy_url_en'))) {
                $validator->errors()->add(
                    'privacy_url_en',
                    'A PrivacyStatementURL is required when Code of Conduct v2 is asserted.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'entity_id.unique'  => 'This entityID is already registered. Each entity must have a globally unique entityID.',
            'entity_id.url'     => 'The entityID must be a valid URI (e.g. https://idp.example.org/idp).',
            'type.in'           => 'Type must be "idp" (Identity Provider) or "sp" (Service Provider).',
            'certificates.min'  => 'At least one X.509 certificate is required.',
            'org_url_en.url'    => 'The organisation URL must be a valid HTTPS URL.',
        ];
    }
}
