<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\AttributeDefinition;
use App\Models\Entity;
use App\Models\EntityAttribute;
use App\Models\EntityEndpoint;
use App\Models\Federation;
use Illuminate\Support\Collection;
use App\Services\Entity\CertificateService;
use App\Services\Entity\EntityMetadataService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * EntityForm Livewire component
 *
 * Handles both create and edit for SAML2 Entity records.
 * Mirrors the validation rules from StoreEntityRequest inline.
 * Do NOT rename save() to validate() — conflicts with Livewire base class.
 */
class EntityForm extends Component
{
    // Null for create, UUID for edit
    public ?string $entityDbId = null;

    public string $entity_id              = '';
    public string $type                   = 'sp';
    public bool   $edugain                = false;
    public string $scope                  = '';
    public string $registration_authority = '';
    public array  $registration_policies  = [];

    public string $name_en            = '';
    public string $name_native        = '';
    public string $name_lang          = '';
    public string $description_en     = '';
    public string $description_native = '';
    public string $information_url_en = '';
    public string $privacy_url_en     = '';
    public string $logo_url           = '';
    public ?int   $logo_height        = null;
    public ?int   $logo_width         = null;

    public string  $org_name_en         = '';
    public string  $org_name_native     = '';
    public string  $org_display_name_en = '';
    public string  $org_url_en          = '';
    public ?float  $org_lat             = null;
    public ?float  $org_lng             = null;

    public array $contacts = [
        ['type' => 'technical', 'given_name' => '', 'sur_name' => '', 'email' => '', 'phone' => ''],
    ];

    public string $sso_http_post     = '';
    public string $sso_http_redirect = '';
    public string $sso_soap          = '';

    public string $acs_http_post     = '';
    public string $acs_http_redirect = '';
    public string $acs_paos          = '';

    public string $slo_http_post     = '';
    public string $slo_http_redirect = '';
    public string $slo_soap          = '';

    public array $nameid_formats                = [];
    public bool  $sp_want_authn_requests_signed = true;
    public bool  $sp_want_assertions_signed     = true;
    public array $requested_attributes          = [];

    public array $entity_categories         = [];
    public array $entity_category_support   = [];
    public array $assurance_profiles        = [];
    public bool  $sirtfi                    = false;

    public array $certificates = [
        ['use' => 'signing', 'pem' => ''],
    ];

    public array $federation_ids = [];

    public string $attributeSchema = '';

    public array $additionalLangs = [];

    public array $availableLangs = [
        'ro' => 'Romanian',
        'de' => 'German',
        'fr' => 'French',
        'ru' => 'Russian',
        'es' => 'Spanish',
        'it' => 'Italian',
        'pl' => 'Polish',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'et' => 'Estonian',
        'cs' => 'Czech',
        'sk' => 'Slovak',
        'hu' => 'Hungarian',
        'nl' => 'Dutch',
        'pt' => 'Portuguese',
        'sv' => 'Swedish',
        'fi' => 'Finnish',
        'da' => 'Danish',
        'no' => 'Norwegian',
        'bg' => 'Bulgarian',
        'hr' => 'Croatian',
        'sr' => 'Serbian',
        'uk' => 'Ukrainian',
    ];

    public array $multilingualFields = [
        'display_name'     => 'Display Name',
        'description'      => 'Description',
        'information_url'  => 'Information URL',
        'privacy_url'      => 'Privacy Statement URL',
        'org_name'         => 'Organisation Name',
        'org_display_name' => 'Organisation Display Name',
        'org_url'          => 'Organisation URL',
    ];

    public string $oidcRedirectUris              = '';
    public array  $oidcGrantTypes                = [];
    public string $oidcScopes                    = '';
    public string $oidcApplicationType           = 'web';
    public string $oidcTokenEndpointAuthMethod   = 'client_secret_basic';

    public array $validationResults    = [];
    public bool  $validationPassed     = false;
    public bool  $warningsAcknowledged = false;
    public bool  $hasRunValidation     = false;

    public array $availableFederations = [];


    public function mount(?Entity $entity = null): void
    {
        $this->availableFederations = Federation::orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        if ($entity === null || !$entity->exists) {
            $this->loadFromSession();
            return;
        }

        $this->entityDbId = $entity->id;
        $entity->load(['uiInfo', 'contacts', 'endpoints', 'attributes', 'certificates', 'federations']);

        // Core
        $this->entity_id              = $entity->entity_id;
        $this->type                   = $entity->type;
        $this->edugain                = (bool) $entity->edugain;
        $this->scope                  = $entity->scope ?? '';
        $this->registration_authority = $entity->registration_authority ?? '';
        $this->registration_policies  = $entity->registration_policies ?? [];
        $this->org_lat   = $entity->org_lat ? (float) $entity->org_lat : null;
        $this->org_lng   = $entity->org_lng ? (float) $entity->org_lng : null;

        // UIInfo
        $uiInfo = $entity->uiInfo;
        $this->name_en             = $uiInfo->where('field', 'display_name')->where('lang', 'en')->first()?->value ?? '';
        $this->description_en      = $uiInfo->where('field', 'description')->where('lang', 'en')->first()?->value ?? '';
        $this->information_url_en  = $uiInfo->where('field', 'information_url')->where('lang', 'en')->first()?->value ?? '';
        $this->privacy_url_en      = $uiInfo->where('field', 'privacy_url')->where('lang', 'en')->first()?->value ?? '';
        $this->org_name_en         = $uiInfo->where('field', 'org_name')->where('lang', 'en')->first()?->value ?? '';
        $this->org_display_name_en = $uiInfo->where('field', 'org_display_name')->where('lang', 'en')->first()?->value ?? '';
        $this->org_url_en          = $uiInfo->where('field', 'org_url')->where('lang', 'en')->first()?->value ?? '';

        $logo = $uiInfo->where('field', 'logo_url')->first();
        if ($logo) {
            $this->logo_url    = $logo->value;
            $this->logo_height = $logo->logo_height;
            $this->logo_width  = $logo->logo_width;
        }

        $nativeDisplayName = $uiInfo->where('field', 'display_name')->where('lang', '!=', 'en')->first();
        if ($nativeDisplayName) {
            $this->name_lang   = $nativeDisplayName->lang;
            $this->name_native = $nativeDisplayName->value;
        }

        $nativeDesc               = $uiInfo->where('field', 'description')->where('lang', '!=', 'en')->first();
        $this->description_native = $nativeDesc?->value ?? '';

        // Contacts
        $loadedContacts = $entity->contacts->map(fn ($c) => [
            'type'       => $c->type,
            'given_name' => $c->given_name ?? '',
            'sur_name'   => $c->sur_name   ?? '',
            'email'      => $c->email      ?? '',
            'phone'      => $c->phone      ?? '',
        ])->values()->toArray();
        $this->contacts = !empty($loadedContacts) ? $loadedContacts
            : [['type' => 'technical', 'given_name' => '', 'sur_name' => '', 'email' => '', 'phone' => '']];

        // Endpoints
        $ep                      = $entity->endpoints;
        $this->sso_http_post     = $ep->where('type', 'sso')->where('binding', EntityEndpoint::BINDING_HTTP_POST)->first()?->location ?? '';
        $this->sso_http_redirect = $ep->where('type', 'sso')->where('binding', EntityEndpoint::BINDING_HTTP_REDIRECT)->first()?->location ?? '';
        $this->sso_soap          = $ep->where('type', 'sso')->where('binding', EntityEndpoint::BINDING_SOAP)->first()?->location ?? '';
        $this->acs_http_post     = $ep->where('type', 'acs')->where('binding', EntityEndpoint::BINDING_HTTP_POST)->first()?->location ?? '';
        $this->acs_http_redirect = $ep->where('type', 'acs')->where('binding', EntityEndpoint::BINDING_HTTP_REDIRECT)->first()?->location ?? '';
        $this->acs_paos          = $ep->where('type', 'acs')->where('binding', EntityEndpoint::BINDING_PAOS)->first()?->location ?? '';
        $this->slo_http_post     = $ep->where('type', 'slo')->where('binding', EntityEndpoint::BINDING_HTTP_POST)->first()?->location ?? '';
        $this->slo_http_redirect = $ep->where('type', 'slo')->where('binding', EntityEndpoint::BINDING_HTTP_REDIRECT)->first()?->location ?? '';
        $this->slo_soap          = $ep->where('type', 'slo')->where('binding', EntityEndpoint::BINDING_SOAP)->first()?->location ?? '';

        // REFEDS
        $attrs                   = $entity->attributes;
        $this->entity_categories = $attrs->where('attribute_name', EntityAttribute::ATTR_ENTITY_CATEGORY)
            ->pluck('attribute_value')->toArray();
        $this->entity_category_support = $attrs->where('attribute_name', EntityAttribute::ATTR_ENTITY_CATEGORY_SUPPORT)
            ->pluck('attribute_value')->toArray();
        $this->assurance_profiles = $attrs->where('attribute_name', EntityAttribute::ATTR_ASSURANCE_PROFILE)
            ->where('attribute_value', '!=', EntityAttribute::URI_SIRTFI)
            ->pluck('attribute_value')->toArray();
        $this->sirtfi             = $attrs->where('attribute_name', EntityAttribute::ATTR_ASSURANCE_PROFILE)
            ->where('attribute_value', EntityAttribute::URI_SIRTFI)
            ->isNotEmpty();

        // Entity-level SP flags and formats
        $this->nameid_formats                = $entity->nameid_formats ?? [];
        $this->sp_want_authn_requests_signed = (bool) $entity->sp_want_authn_requests_signed;
        $this->sp_want_assertions_signed     = (bool) $entity->sp_want_assertions_signed;
        $this->requested_attributes          = $entity->requested_attributes ?? [];

        // Certificates
        $this->certificates = $entity->certificates->map(fn($c) => [
            'use' => $c->use,
            'pem' => $c->pem,
        ])->toArray();

        if (empty($this->certificates)) {
            $this->certificates = [['use' => 'signing', 'pem' => '']];
        }

        // Federations
        $this->federation_ids = $entity->federations->pluck('id')->toArray();

        // Additional language variants (all non-English ui_info rows)
        $this->additionalLangs = $entity->uiInfo
            ->where('lang', '!=', 'en')
            ->filter(fn ($u) => $u->field !== 'logo_url')
            ->map(fn ($u) => [
                'lang'  => $u->lang,
                'field' => $u->field,
                'value' => $u->value,
            ])
            ->values()
            ->toArray();

        // OIDC configuration (only when entity type is oidc)
        if ($entity->type === 'oidc') {
            $entity->loadMissing('oidcConfig');
            $cfg = $entity->oidcConfig;
            if ($cfg) {
                $this->oidcRedirectUris            = implode("\n", $cfg->redirect_uris ?? []);
                $this->oidcGrantTypes              = $cfg->grant_types ?? [];
                $this->oidcScopes                  = implode(' ', $cfg->scopes ?? []);
                $this->oidcApplicationType         = $cfg->application_type ?? 'web';
                $this->oidcTokenEndpointAuthMethod = $cfg->token_endpoint_auth_method ?? 'client_secret_basic';
            }
        }

        // If a metadata reload was requested, pre-populate from parsed XML/JSON.
        // entityDbId is already set so save() will update, not create.
        $reloadKey = "entity_reload_{$entity->id}";
        $reloadData = session($reloadKey);
        if ($reloadData) {
            $this->populateFromData($reloadData);
            session()->forget($reloadKey);
        }
    }

    /**
     * Populate form state from session('import_data'). Called on mount when no entity is bound.
     * Session data is placed by EntityImportController after parsing an XML or array import.
     * No-ops silently if the key is absent.
     */
    private function loadFromSession(): void
    {
        $data = session('import_data');
        if ($data) {
            $this->populateFromData($data);
        }
    }

    public function populateFromData(array $data): void
    {
        $this->entity_id              = $data['entity_id'] ?? '';
        $this->type                   = $data['type'] ?? 'sp';
        $this->scope                  = $data['scope'] ?? '';
        $this->registration_authority = $data['registration_authority'] ?? '';
        $this->registration_policies  = $data['registration_policies'] ?? [];

        $uiInfo = collect($data['ui_info'] ?? []);
        $this->name_en             = $uiInfo->where('field', 'display_name')->where('lang', 'en')->first()['value'] ?? '';
        $this->description_en      = $uiInfo->where('field', 'description')->where('lang', 'en')->first()['value'] ?? '';
        $this->information_url_en  = $uiInfo->where('field', 'information_url')->where('lang', 'en')->first()['value'] ?? '';
        $this->privacy_url_en      = $uiInfo->where('field', 'privacy_url')->where('lang', 'en')->first()['value'] ?? '';
        $this->org_name_en         = $uiInfo->where('field', 'org_name')->where('lang', 'en')->first()['value'] ?? '';
        $this->org_display_name_en = $uiInfo->where('field', 'org_display_name')->where('lang', 'en')->first()['value'] ?? '';
        $this->org_url_en          = $uiInfo->where('field', 'org_url')->where('lang', 'en')->first()['value'] ?? '';

        $logo = $uiInfo->where('field', 'logo_url')->first();
        if ($logo) {
            $this->logo_url    = $logo['value'] ?? '';
            $this->logo_height = isset($logo['logo_height']) ? (int) $logo['logo_height'] : null;
            $this->logo_width  = isset($logo['logo_width'])  ? (int) $logo['logo_width']  : null;
        }

        $nativeName = $uiInfo->where('field', 'display_name')->filter(fn($r) => ($r['lang'] ?? 'en') !== 'en')->first();
        if ($nativeName) {
            $this->name_lang   = $nativeName['lang'] ?? '';
            $this->name_native = $nativeName['value'] ?? '';
        }

        // Preserve all non-English UI info rows so syncUiInfo() doesn't discard them on save.
        $this->additionalLangs = $uiInfo
            ->filter(fn($r) => ($r['lang'] ?? 'en') !== 'en' && ($r['field'] ?? '') !== 'logo_url')
            ->map(fn($r) => [
                'lang'  => $r['lang'] ?? '',
                'field' => $r['field'] ?? '',
                'value' => $r['value'] ?? '',
            ])
            ->values()
            ->toArray();

        $loadedContacts = collect($data['contacts'] ?? [])->map(fn ($c) => [
            'type'       => $c['type']       ?? 'technical',
            'given_name' => $c['given_name'] ?? '',
            'sur_name'   => $c['sur_name']   ?? '',
            'email'      => $c['email']      ?? '',
            'phone'      => $c['phone']      ?? '',
        ])->values()->toArray();
        $this->contacts = !empty($loadedContacts) ? $loadedContacts
            : [['type' => 'technical', 'given_name' => '', 'sur_name' => '', 'email' => '', 'phone' => '']];

        $endpoints = collect($data['endpoints'] ?? []);
        $this->sso_http_post     = $endpoints->where('type', 'sso')->where('binding', EntityEndpoint::BINDING_HTTP_POST)->first()['location'] ?? '';
        $this->sso_http_redirect = $endpoints->where('type', 'sso')->where('binding', EntityEndpoint::BINDING_HTTP_REDIRECT)->first()['location'] ?? '';
        $this->sso_soap          = $endpoints->where('type', 'sso')->where('binding', EntityEndpoint::BINDING_SOAP)->first()['location'] ?? '';
        $this->acs_http_post     = $endpoints->where('type', 'acs')->where('binding', EntityEndpoint::BINDING_HTTP_POST)->first()['location'] ?? '';
        $this->acs_http_redirect = $endpoints->where('type', 'acs')->where('binding', EntityEndpoint::BINDING_HTTP_REDIRECT)->first()['location'] ?? '';
        $this->acs_paos          = $endpoints->where('type', 'acs')->where('binding', EntityEndpoint::BINDING_PAOS)->first()['location'] ?? '';
        $this->slo_http_post     = $endpoints->where('type', 'slo')->where('binding', EntityEndpoint::BINDING_HTTP_POST)->first()['location'] ?? '';
        $this->slo_http_redirect = $endpoints->where('type', 'slo')->where('binding', EntityEndpoint::BINDING_HTTP_REDIRECT)->first()['location'] ?? '';
        $this->slo_soap          = $endpoints->where('type', 'slo')->where('binding', EntityEndpoint::BINDING_SOAP)->first()['location'] ?? '';

        $this->entity_categories       = $data['entity_categories'] ?? [];
        $this->entity_category_support = $data['entity_category_support'] ?? [];
        $this->assurance_profiles = array_filter(
            $data['assurance_profiles'] ?? [],
            fn($v) => $v !== EntityAttribute::URI_SIRTFI
        );
        $this->sirtfi             = in_array(EntityAttribute::URI_SIRTFI, $data['assurance_profiles'] ?? [], true);
        $this->nameid_formats     = $data['nameid_formats'] ?? [];

        if (!empty($data['certificates'])) {
            $this->certificates = array_map(
                fn($c) => ['use' => $c['use'] ?? 'signing', 'pem' => $c['pem'] ?? ''],
                $data['certificates']
            );
        }
    }


    #[Computed]
    public function isIdp(): bool
    {
        return $this->type === 'idp';
    }

    #[Computed]
    public function isSp(): bool
    {
        return $this->type === 'sp';
    }

    #[Computed]
    public function storedRequestedAttributes(): \Illuminate\Support\Collection
    {
        if (!$this->entityDbId) {
            return collect();
        }

        return \App\Models\EntityRequestedAttribute::with('attributeDefinition')
            ->where('entity_id', $this->entityDbId)
            ->get();
    }

    #[Computed]
    public function availableAttributes(): Collection
    {
        return AttributeDefinition::active()
            ->when($this->attributeSchema,
                fn($q) => $q->where('schema', $this->attributeSchema))
            ->orderBy('name')
            ->get();
    }


    public function addRegistrationPolicy(): void
    {
        $this->registration_policies[] = ['lang' => 'en', 'url' => ''];
    }

    public function removeRegistrationPolicy(int $index): void
    {
        array_splice($this->registration_policies, $index, 1);
        $this->registration_policies = array_values($this->registration_policies);
    }

    public function addContact(): void
    {
        $this->contacts[] = ['type' => 'technical', 'given_name' => '', 'sur_name' => '', 'email' => '', 'phone' => ''];
    }

    public function removeContact(int $index): void
    {
        array_splice($this->contacts, $index, 1);
        if (empty($this->contacts)) {
            $this->contacts = [['type' => 'technical', 'given_name' => '', 'sur_name' => '', 'email' => '']];
        }
    }

    public function addCertificate(): void
    {
        $this->certificates[] = ['use' => 'signing', 'pem' => ''];
    }

    public function removeCertificate(int $index): void
    {
        unset($this->certificates[$index]);
        $this->certificates = array_values($this->certificates);
    }


    public function addLanguageVariant(): void
    {
        $this->additionalLangs[] = [
            'lang'  => array_key_first($this->availableLangs),
            'field' => 'display_name',
            'value' => '',
        ];
    }

    public function removeLanguageVariant(int $index): void
    {
        array_splice($this->additionalLangs, $index, 1);
    }

    public function removeRequestedAttribute(int $index): void
    {
        $this->requested_attributes = array_values(array_filter(
            $this->requested_attributes,
            fn($k) => $k !== $index,
            ARRAY_FILTER_USE_KEY
        ));
    }

    public function addRequestedAttribute(): void
    {
        $this->requested_attributes[] = [
            'name'          => '',
            'friendly_name' => '',
            'name_format'   => '',
            'is_required'   => false,
        ];
    }


    /**
     * Persist the OIDC configuration for the current entity (edit mode only).
     *
     * @authorizes  EntityPolicy::update
     */
    public function saveOidcConfig(): void
    {
        $entity = Entity::find($this->entityDbId);
        if (! $entity) {
            return;
        }
        Gate::authorize('update', $entity);

        $entity->oidcConfig()->updateOrCreate(
            ['entity_id' => $entity->id],
            [
                'redirect_uris'              => array_values(array_filter(array_map('trim', explode("\n", $this->oidcRedirectUris)))),
                'grant_types'                => $this->oidcGrantTypes,
                'response_types'             => [],
                'scopes'                     => array_values(array_filter(explode(' ', $this->oidcScopes))),
                'application_type'           => $this->oidcApplicationType,
                'token_endpoint_auth_method' => $this->oidcTokenEndpointAuthMethod,
            ],
        );

        $this->dispatch('notify', message: 'OIDC configuration saved.');
    }


    /**
     * Run inline SAML/REFEDS pre-submission checks (entity ID format, SSO/ACS endpoints,
     * signing certificate presence, display name). Guest and Entity Manager roles are blocked
     * from calling save() until this gate passes or warnings are acknowledged.
     */
    public function runValidationGate(): void
    {
        $results = [];

        // S01 — entityID must be a valid HTTPS URL
        if (empty($this->entity_id)) {
            $results[] = ['id' => 'S01', 'status' => 'fail', 'message' => 'Entity ID is required.'];
        } elseif (! filter_var($this->entity_id, FILTER_VALIDATE_URL)) {
            $results[] = ['id' => 'S01', 'status' => 'fail', 'message' => 'Entity ID must be a valid URL.'];
        } else {
            $results[] = ['id' => 'S01', 'status' => 'pass', 'message' => 'Entity ID is a valid URL.'];
        }

        // S03 — role descriptor (at least one SSO/ACS endpoint)
        if ($this->type === 'idp') {
            if (empty($this->sso_http_post) && empty($this->sso_http_redirect)) {
                $results[] = ['id' => 'S03', 'status' => 'fail', 'message' => 'IdP must have at least one SingleSignOnService endpoint.'];
            } else {
                $results[] = ['id' => 'S03', 'status' => 'pass', 'message' => 'SSO endpoint present.'];
            }
        } else {
            if (empty($this->acs_http_post)) {
                $results[] = ['id' => 'S03', 'status' => 'fail', 'message' => 'SP must have at least one AssertionConsumerService (HTTP-POST) endpoint.'];
            } else {
                $results[] = ['id' => 'S03', 'status' => 'pass', 'message' => 'ACS endpoint present.'];
            }
        }

        // C01 — at least one signing certificate
        $hasCert = collect($this->certificates)->filter(fn ($c) => ! empty($c['pem']))->isNotEmpty();
        if (! $hasCert) {
            $results[] = ['id' => 'C01', 'status' => 'warning', 'message' => 'No certificate present. Metadata will be generated without a signing certificate.'];
        } else {
            $results[] = ['id' => 'C01', 'status' => 'pass', 'message' => 'Signing certificate present.'];
        }

        // S04 — display name
        if (empty($this->name_en)) {
            $results[] = ['id' => 'S04', 'status' => 'warning', 'message' => 'Display name (English) is missing.'];
        } else {
            $results[] = ['id' => 'S04', 'status' => 'pass', 'message' => 'Display name present.'];
        }

        $this->validationResults    = $results;
        $this->hasRunValidation     = true;
        $hardErrors                 = collect($results)->where('status', 'fail');
        $this->validationPassed     = $hardErrors->isEmpty();
        $this->warningsAcknowledged = false;
    }


    /**
     * Create or update the entity. Runs cross-field SAML guards before the DB transaction:
     * IdP must have an SSO endpoint, SP must have an ACS-POST endpoint, SIRTFI requires
     * a security contact, CoCo v2 requires a Privacy Statement URL.
     *
     * Certificate handling uses smart diffing — only re-parses PEMs that changed (compared
     * by normalized fingerprint) to avoid redundant crypto operations on unrelated saves.
     *
     * Federation membership is set only on create; edit leaves memberships to the approval
     * wizard flows to avoid inadvertently changing pivot status.
     *
     * @sideeffects  Cache::forget(EntityMetadataService::xmlCacheKey) on success
     */
    public function save(): void
    {
        $isCreate = $this->entityDbId === null;

        if (\Illuminate\Support\Facades\Auth::user()->hasAnyRole(['Guest', 'Entity Manager'])
            && ! $this->validationPassed
            && ! $this->warningsAcknowledged
        ) {
            $this->addError('validation', 'Please run validation and fix all errors before submitting, or acknowledge warnings.');
            return;
        }

        $entityIdRules = ['required', 'string', 'max:1024', 'url'];
        if ($isCreate) {
            $entityIdRules[] = Rule::unique('entities', 'entity_id');
        }

        try {
            $this->validate([
                'entity_id' => $entityIdRules,
            'type'      => ['required', Rule::in(['idp', 'sp', 'oidc'])],
            'edugain'   => ['boolean'],
            'registration_authority'          => ['nullable', 'url', 'max:1024'],
            'registration_policies'          => ['nullable', 'array'],
            'registration_policies.*.lang'   => ['required', 'string', 'max:10'],
            'registration_policies.*.url'    => ['required', 'url', 'max:1024'],

            'name_en'            => ['required', 'string', 'max:255'],
            'description_en'     => ['required', 'string', 'max:1024'],
            'information_url_en' => ['nullable', 'url', 'max:1024'],
            'privacy_url_en'     => ['nullable', 'url', 'max:1024'],
            'logo_url'           => ['nullable', 'url', 'max:1024'],
            'logo_height'        => ['nullable', 'integer', 'min:1', 'max:200'],
            'logo_width'         => ['nullable', 'integer', 'min:1', 'max:400'],

            'org_name_en'         => ['required', 'string', 'max:255'],
            'org_name_native'     => ['nullable', 'string', 'max:255'],
            'org_display_name_en' => ['required', 'string', 'max:255'],
            'org_url_en'          => ['required', 'url', 'max:1024'],
            'org_lat'             => ['nullable', 'numeric', 'between:-90,90'],
            'org_lng'             => ['nullable', 'numeric', 'between:-180,180'],

            'contacts'                => ['nullable', 'array'],
            'contacts.*.type'        => ['required', Rule::in(['technical','support','security','administrative','billing'])],
            'contacts.*.given_name'  => ['nullable', 'string', 'max:255'],
            'contacts.*.sur_name'    => ['nullable', 'string', 'max:255'],
            'contacts.*.email'       => ['nullable', 'email', 'max:255'],
            'contacts.*.phone'       => ['nullable', 'string', 'max:50'],

            'entity_categories'          => ['nullable', 'array'],
            'entity_categories.*'        => ['string', 'url', 'max:255'],
            'entity_category_support'    => ['nullable', 'array'],
            'entity_category_support.*'  => ['string', 'url', 'max:255'],

            'assurance_profiles'   => ['nullable', 'array'],
            'assurance_profiles.*' => ['string', 'max:512'],

            'sirtfi' => ['boolean'],

            'scope' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9\-\.]+)?[a-z0-9]$/i'],

            'nameid_formats'   => ['nullable', 'array'],
            'nameid_formats.*' => ['string', Rule::in([
                'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
                'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
                'urn:oasis:names:tc:SAML:2.0:nameid-format:emailAddress',
                'urn:oasis:names:tc:SAML:2.0:nameid-format:kerberos',
                'urn:oasis:names:tc:SAML:2.0:nameid-format:entity',
                'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
                'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
                'urn:oasis:names:tc:SAML:1.1:nameid-format:X509SubjectName',
                'urn:oasis:names:tc:SAML:1.1:nameid-format:WindowsDomainQualifiedName',
            ])],

            'sso_http_post'     => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'sso_http_redirect' => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'sso_soap'          => ['nullable', 'url', 'max:1024', 'starts_with:https://'],

            'acs_http_post'     => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'acs_http_redirect' => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'acs_paos'          => ['nullable', 'url', 'max:1024', 'starts_with:https://'],

            'sp_want_authn_requests_signed' => ['boolean'],
            'sp_want_assertions_signed'     => ['boolean'],

            'requested_attributes'                 => ['nullable', 'array'],
            'requested_attributes.*.name'          => ['required', 'string', 'max:255'],
            'requested_attributes.*.friendly_name' => ['nullable', 'string', 'max:255'],
            'requested_attributes.*.name_format'   => ['nullable', 'string', 'max:255'],
            'requested_attributes.*.is_required'   => ['boolean'],

            'slo_http_post'     => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'slo_http_redirect' => ['nullable', 'url', 'max:1024', 'starts_with:https://'],
            'slo_soap'          => ['nullable', 'url', 'max:1024', 'starts_with:https://'],

            'certificates'       => ['required', 'array', 'min:1'],
            'certificates.*.use' => ['required', Rule::in(['signing', 'encryption', 'both'])],
            'certificates.*.pem' => ['required', 'string'],

            'federation_ids'   => ['nullable', 'array'],
            'federation_ids.*' => ['exists:federations,id'],

            'additionalLangs'           => ['nullable', 'array'],
            'additionalLangs.*.lang'    => ['required', 'string', 'max:10'],
            'additionalLangs.*.field'   => ['required', 'string', 'max:100'],
            'additionalLangs.*.value'   => ['nullable', 'string', 'max:1024'],
        ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            $this->dispatch('notify', type: 'error', message: 'Please fix validation errors.');
            $this->dispatchSwitchToErrorTab(array_keys($ve->errors()));
            throw $ve;
        }

        // Cross-field rules
        if ($this->type === 'idp' && empty($this->sso_http_post) && empty($this->sso_http_redirect)) {
            $this->addError('sso_http_post', 'Identity Provider must have at least one SingleSignOnService endpoint (HTTP-POST or HTTP-Redirect).');
            $this->dispatch('notify', type: 'error', message: 'Please fix validation errors.');
            return;
        }

        if ($this->type === 'sp' && empty($this->acs_http_post)) {
            $this->addError('acs_http_post', 'Service Provider must have at least one AssertionConsumerService endpoint (HTTP-POST required).');
            $this->dispatch('notify', type: 'error', message: 'Please fix validation errors.');
            return;
        }

        $hasSecurityContact = collect($this->contacts)
            ->contains(fn ($c) => ($c['type'] ?? '') === 'security' && !empty($c['email']));
        if ($this->sirtfi && !$hasSecurityContact) {
            $this->addError('contacts', 'A security contact with an email address is required when SIRTFI is asserted.');
            $this->dispatch('notify', type: 'error', message: 'SIRTFI requires a security contact — see Organisation tab.');
            return;
        }

        $cocoAsserted = in_array(EntityAttribute::URI_COCO_V2, $this->entity_categories, true)
            || in_array(EntityAttribute::URI_COCO_V2, $this->entity_category_support, true);
        if ($cocoAsserted && empty($this->privacy_url_en)) {
            $this->addError('privacy_url_en', 'A PrivacyStatementURL is required when Code of Conduct v2 is asserted.');
            $this->dispatch('notify', type: 'error', message: 'CoCo v2 requires a Privacy Statement URL — see Basic Info tab.');
            return;
        }

        $certificateService = app(CertificateService::class);
        $metadataService    = app(EntityMetadataService::class);

        try {
            DB::beginTransaction();

            if ($isCreate) {
                $entity = Entity::create([
                    'entity_id'                     => $this->entity_id,
                    'type'                          => $this->type,
                    'status'                        => 'draft',
                    'edugain'                       => $this->edugain,
                    'registration_authority'        => $this->registration_authority ?: (config('federation.registration_authority') ?? ''),
                    'registration_policies'         => $this->registration_policies ?: null,
                    'scope'                         => $this->scope ?: null,
                    'nameid_formats'                => $this->nameid_formats,
                    'sp_want_authn_requests_signed' => $this->sp_want_authn_requests_signed,
                    'sp_want_assertions_signed'     => $this->sp_want_assertions_signed,
                    'requested_attributes'          => $this->requested_attributes,
                    'org_lat'                       => $this->org_lat,
                    'org_lng'                       => $this->org_lng,
                ]);
            } else {
                $entity = Entity::findOrFail($this->entityDbId);
                $entity->update([
                    'edugain'                       => $this->edugain,
                    'registration_authority'        => $this->registration_authority ?: '',
                    'registration_policies'         => $this->registration_policies ?: null,
                    'scope'                         => $this->scope ?: null,
                    'nameid_formats'                => $this->nameid_formats,
                    'sp_want_authn_requests_signed' => $this->sp_want_authn_requests_signed,
                    'sp_want_assertions_signed'     => $this->sp_want_assertions_signed,
                    'requested_attributes'          => $this->requested_attributes,
                    'org_lat'                       => $this->org_lat,
                    'org_lng'                       => $this->org_lng,
                ]);
            }

            $this->syncUiInfo($entity);
            $this->syncContacts($entity);
            $this->syncEndpoints($entity);
            $this->syncAttributes($entity);

            // Certificates — only parse/replace certs that actually changed.
            // Keyed by normalized-PEM + use so that two entries with the same
            // certificate data but different use values (signing + encryption)
            // are treated as distinct and both preserved.
            $existingByCertKey = $isCreate
                ? []
                : $entity->certificates
                    ->mapWithKeys(fn($c) => [
                        $certificateService->normalizePem($c->pem) . '|' . $c->use => $c
                    ])
                    ->all();

            $keptIds = [];

            foreach ($this->certificates as $cert) {
                if (trim($cert['pem'] ?? '') === '') {
                    continue; // skip empty placeholders
                }

                $pem     = $certificateService->normalizePem($cert['pem']);
                $certKey = $pem . '|' . ($cert['use'] ?? 'signing');

                if (isset($existingByCertKey[$certKey])) {
                    $keptIds[] = $existingByCertKey[$certKey]->id;
                } else {
                    $parsed = $certificateService->parse($pem);
                    $new = $entity->certificates()->create([
                        'use'                 => $cert['use'],
                        'pem'                 => $pem,
                        'subject'             => $parsed['subject'],
                        'issuer'              => $parsed['issuer'],
                        'serial'              => $parsed['serial'],
                        'not_before'          => $parsed['not_before'],
                        'not_after'           => $parsed['not_after'],
                        'key_bits'            => $parsed['key_bits'],
                        'key_algorithm'       => $parsed['key_algorithm'],
                        'fingerprint'         => $parsed['fingerprint'],
                        'signature_algorithm' => $parsed['signature_algorithm'],
                        'debian_weak'         => $certificateService->isDebianWeak($parsed['fingerprint']),
                    ]);
                    $keptIds[] = $new->id;
                }
            }

            $entity->certificates()->whereNotIn('id', $keptIds)->delete();

            // Federation membership — only set on create; on edit leave memberships to wizard flows
            if ($isCreate) {
                $pivotData = [];
                foreach ($this->federation_ids as $fedId) {
                    $pivotData[$fedId] = [
                        'status'     => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                $entity->federations()->sync($pivotData);
            }

            $validationResult = $metadataService->validate($entity->fresh());

            DB::commit();

            Log::info($isCreate ? 'Entity created via EntityForm' : 'Entity updated via EntityForm', [
                'entity_id' => $entity->entity_id,
                'type'      => $entity->type,
            ]);

            Cache::forget(EntityMetadataService::xmlCacheKey($entity));

            foreach ($entity->federations as $federation) {
                Cache::forget("federation_metadata:{$federation->id}");
                Cache::forget("federation_edugain_metadata:{$federation->id}");
            }

            session()->flash('success', 'Entity saved.');

            $this->redirect(route('entities.show', $entity), navigate: true);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('EntityForm save failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', type: 'error', message: 'Save failed: ' . $e->getMessage());
        }
    }


    private function syncUiInfo(Entity $entity): void
    {
        $entity->uiInfo()->delete();

        $enFields = [
            'display_name'     => $this->name_en ?: null,
            'description'      => $this->description_en ?: null,
            'information_url'  => $this->information_url_en ?: null,
            'privacy_url'      => $this->privacy_url_en ?: null,
            'org_name'         => $this->org_name_en ?: null,
            'org_display_name' => $this->org_display_name_en ?: null,
            'org_url'          => $this->org_url_en ?: null,
        ];

        foreach ($enFields as $field => $value) {
            if ($value !== null && $value !== '') {
                $entity->uiInfo()->create(['field' => $field, 'lang' => 'en', 'value' => $value]);
            }
        }

        if (!empty($this->logo_url)) {
            $entity->uiInfo()->create([
                'field'       => 'logo_url',
                'lang'        => 'en',
                'value'       => $this->logo_url,
                'logo_height' => $this->logo_height,
                'logo_width'  => $this->logo_width,
            ]);
        }

        // Additional language variants
        foreach ($this->additionalLangs as $variant) {
            if (empty(trim($variant['value'] ?? ''))) {
                continue;
            }
            $entity->uiInfo()->updateOrCreate(
                ['field' => $variant['field'], 'lang' => $variant['lang']],
                ['value' => $variant['value']]
            );
        }

        // Remove non-English rows that are no longer in $additionalLangs
        $keepPairs = collect($this->additionalLangs)
            ->map(fn ($v) => $v['field'] . '|' . $v['lang'])
            ->toArray();

        $entity->uiInfo()
            ->where('lang', '!=', 'en')
            ->get()
            ->each(function ($ui) use ($keepPairs) {
                if (! in_array($ui->field . '|' . $ui->lang, $keepPairs, true)) {
                    $ui->delete();
                }
            });
    }

    private function syncContacts(Entity $entity): void
    {
        $entity->contacts()->delete();

        foreach ($this->contacts as $row) {
            if (empty($row['email']) && empty($row['given_name']) && empty($row['sur_name'])) {
                continue;
            }
            $entity->contacts()->create([
                'type'       => $row['type']       ?? 'technical',
                'given_name' => $row['given_name'] ?: null,
                'sur_name'   => $row['sur_name']   ?: null,
                'email'      => $row['email']       ?? '',
                'phone'      => $row['phone']       ?: null,
            ]);
        }
    }

    private function syncEndpoints(Entity $entity): void
    {
        $entity->endpoints()->delete();

        $ssoMap = [
            EntityEndpoint::BINDING_HTTP_POST     => $this->sso_http_post,
            EntityEndpoint::BINDING_HTTP_REDIRECT => $this->sso_http_redirect,
            EntityEndpoint::BINDING_SOAP          => $this->sso_soap,
        ];
        foreach ($ssoMap as $binding => $location) {
            if (!empty($location)) {
                $entity->endpoints()->create(['type' => 'sso', 'binding' => $binding, 'location' => $location]);
            }
        }

        $acsIndex = 1;
        $acsMap   = [
            EntityEndpoint::BINDING_HTTP_POST     => $this->acs_http_post,
            EntityEndpoint::BINDING_HTTP_REDIRECT => $this->acs_http_redirect,
            EntityEndpoint::BINDING_PAOS          => $this->acs_paos,
        ];
        foreach ($acsMap as $binding => $location) {
            if (!empty($location)) {
                $entity->endpoints()->create([
                    'type'       => 'acs',
                    'binding'    => $binding,
                    'location'   => $location,
                    'index'      => $acsIndex,
                    'is_default' => $acsIndex === 1,
                ]);
                $acsIndex++;
            }
        }

        $sloMap = [
            EntityEndpoint::BINDING_HTTP_POST     => $this->slo_http_post,
            EntityEndpoint::BINDING_HTTP_REDIRECT => $this->slo_http_redirect,
            EntityEndpoint::BINDING_SOAP          => $this->slo_soap,
        ];
        foreach ($sloMap as $binding => $location) {
            if (!empty($location)) {
                $entity->endpoints()->create(['type' => 'slo', 'binding' => $binding, 'location' => $location]);
            }
        }
    }

    private function syncAttributes(Entity $entity): void
    {
        $entity->attributes()->delete();

        foreach ($this->entity_categories as $uri) {
            $entity->attributes()->create([
                'attribute_name'  => EntityAttribute::ATTR_ENTITY_CATEGORY,
                'attribute_value' => $uri,
            ]);
        }

        foreach ($this->entity_category_support as $uri) {
            $entity->attributes()->create([
                'attribute_name'  => EntityAttribute::ATTR_ENTITY_CATEGORY_SUPPORT,
                'attribute_value' => $uri,
            ]);
        }

        foreach ($this->assurance_profiles as $uri) {
            $entity->attributes()->create([
                'attribute_name'  => EntityAttribute::ATTR_ASSURANCE_PROFILE,
                'attribute_value' => $uri,
            ]);
        }

        if ($this->sirtfi) {
            $alreadySet = $entity->attributes()
                ->where('attribute_name', EntityAttribute::ATTR_ASSURANCE_PROFILE)
                ->where('attribute_value', EntityAttribute::URI_SIRTFI)
                ->exists();

            if (!$alreadySet) {
                $entity->attributes()->create([
                    'attribute_name'  => EntityAttribute::ATTR_ASSURANCE_PROFILE,
                    'attribute_value' => EntityAttribute::URI_SIRTFI,
                ]);
            }
        }
    }

    private function dispatchSwitchToErrorTab(array $errorKeys): void
    {
        $tabFields = [
            'basic'        => ['entity_id','type','edugain','registration_authority','registration_policies','name_en','description_en','information_url_en','privacy_url_en','logo_url','logo_height','logo_width'],
            'endpoints'    => ['sso_http_post','sso_http_redirect','sso_soap','acs_http_post','acs_http_redirect','acs_paos','slo_http_post','slo_http_redirect','slo_soap','sp_want_authn_requests_signed','sp_want_assertions_signed','nameid_formats','scope'],
            'certificates' => ['certificates'],
            'organisation' => ['org_name_en','org_name_native','org_display_name_en','org_url_en','org_lat','org_lng','contacts','federation_ids'],
            'refeds'       => ['entity_categories','entity_category_support','assurance_profiles','sirtfi','requested_attributes'],
            'languages'    => ['additionalLangs'],
        ];

        foreach ($tabFields as $tab => $fields) {
            foreach ($errorKeys as $key) {
                if (in_array(explode('.', $key)[0], $fields, true)) {
                    $this->dispatch('switch-tab', tab: $tab);
                    return;
                }
            }
        }
    }

    public function render(): View
    {
        return view('livewire.entity-form');
    }
}
