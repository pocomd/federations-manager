{{--
    resources/views/livewire/entity-form.blade.php

    EntityForm Livewire component — Bootstrap 5, Alpine.js tab switching.
    Tabs: 1=Basic Info  2=Endpoints  3=Certificates  4=Organisation  5=REFEDS
    wire:model.live on entity_id for live validation feedback.
--}}

<div
    x-data="{ tab: 'basic' }"
    x-on:switch-tab.window="tab = $event.detail.tab"
    x-cloak
>

{{-- ── General error ──────────────────────────────────────────────────────── --}}
@error('general')
    <div class="alert alert-danger mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $message }}
    </div>
@enderror

{{-- ── Tab navigation ──────────────────────────────────────────────────────── --}}
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link" :class="{ active: tab === 'basic' }"
                @click.prevent="tab = 'basic'" type="button">
            <i class="bi bi-info-circle me-1"></i> Basic Info
            @if($errors->hasAny(['entity_id','type','name_en','description_en','information_url_en','privacy_url_en','logo_url','logo_height','logo_width']))
                <span class="badge bg-danger ms-1" style="font-size:.6rem;padding:.2em .4em;">!</span>
            @endif
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" :class="{ active: tab === 'endpoints' }"
                @click.prevent="tab = 'endpoints'" type="button">
            <i class="bi bi-link-45deg me-1"></i> Endpoints
            @php $endpointKeys = ['sso_http_post','sso_http_redirect','sso_soap','acs_http_post','acs_http_redirect','acs_paos','slo_http_post','slo_http_redirect','slo_soap','nameid_formats','scope']; @endphp
            @if(collect($errors->keys())->contains(fn($k) => in_array(explode('.', $k)[0], $endpointKeys)))
                <span class="badge bg-danger ms-1" style="font-size:.6rem;padding:.2em .4em;">!</span>
            @endif
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" :class="{ active: tab === 'certificates' }"
                @click.prevent="tab = 'certificates'" type="button">
            <i class="bi bi-shield-lock me-1"></i> Certificates
            @if($errors->has('certificates'))
                <span class="badge bg-danger ms-1" style="font-size:.6rem;padding:.2em .4em;">!</span>
            @endif
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" :class="{ active: tab === 'organisation' }"
                @click.prevent="tab = 'organisation'" type="button">
            <i class="bi bi-building me-1"></i> Organisation
            @if($errors->hasAny(['org_name_en','org_name_native','org_display_name_en','org_url_en','org_lat','org_lng','contacts'])
                || $errors->hasAny(array_map(fn($i) => "contacts.{$i}.email", array_keys($contacts)))
                || $errors->hasAny(array_map(fn($i) => "contacts.{$i}.type", array_keys($contacts))))
                <span class="badge bg-danger ms-1" style="font-size:.6rem;padding:.2em .4em;">!</span>
            @endif
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" :class="{ active: tab === 'refeds' }"
                @click.prevent="tab = 'refeds'" type="button">
            <i class="bi bi-tags me-1"></i> REFEDS
            @php $refedsKeys = ['entity_categories','assurance_profiles','sirtfi','requested_attributes']; @endphp
            @if(collect($errors->keys())->contains(fn($k) => in_array(explode('.', $k)[0], $refedsKeys)))
                <span class="badge bg-danger ms-1" style="font-size:.6rem;padding:.2em .4em;">!</span>
            @endif
        </button>
    </li>
    @if($type === 'oidc')
    <li class="nav-item">
        <button class="nav-link" :class="{ active: tab === 'oidc' }"
                @click.prevent="tab = 'oidc'" type="button">
            <i class="bi bi-key-fill me-1"></i> OIDC
        </button>
    </li>
    @endif
    <li class="nav-item">
        <button class="nav-link" :class="{ active: tab === 'languages' }"
                @click.prevent="tab = 'languages'" type="button">
            <i class="bi bi-translate me-1"></i>
            Languages
            @if(count($additionalLangs) > 0)
                <span class="badge bg-info text-dark ms-1">{{ count($additionalLangs) }}</span>
            @endif
            @if($errors->has('additionalLangs') || $errors->hasAny(array_map(fn($i) => "additionalLangs.{$i}.value", array_keys($additionalLangs))))
                <span class="badge bg-danger ms-1" style="font-size:.6rem;padding:.2em .4em;">!</span>
            @endif
        </button>
    </li>
</ul>

<form wire:submit.prevent="save" novalidate>

{{-- ════════════════════════════════════════════════════════════════════════
     TAB 1 — BASIC INFO
════════════════════════════════════════════════════════════════════════ --}}
<div x-show="tab === 'basic'">
    <div class="row g-3">

        {{-- entityID --}}
        <div class="col-12">
            <label for="entity_id" class="form-label fw-semibold">
                Entity ID <span class="text-danger">*</span>
                <span class="text-muted fw-normal small">(SAML2 entityID URI — must be globally unique, HTTPS strongly recommended)</span>
            </label>
            <input type="url"
                   id="entity_id"
                   wire:model.live="entity_id"
                   class="form-control @error('entity_id') is-invalid @enderror"
                   placeholder="https://idp.example.org/idp/shibboleth"
                   {{ $entityDbId ? 'readonly' : '' }}>
            @error('entity_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            @if($entityDbId)
                <div class="form-text text-muted">Entity ID cannot be changed after creation.</div>
            @endif
        </div>

        {{-- Registration Authority --}}
        <div class="col-12">
            <label for="registration_authority" class="form-label fw-semibold">
                Registration Authority
                <span class="text-muted fw-normal small">(mdrpi:RegistrationInfo — URI identifying the registrar, e.g. https://www.edugain.org/)</span>
            </label>
            <input type="url"
                   id="registration_authority"
                   wire:model="registration_authority"
                   class="form-control @error('registration_authority') is-invalid @enderror"
                   placeholder="https://www.edugain.org/">
            @error('registration_authority')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- Registration Policies --}}
        <div class="col-12">
            <label class="form-label fw-semibold">
                Registration Policies
                <span class="text-muted fw-normal small">(mdrpi:RegistrationPolicy — policy URL per language)</span>
            </label>
            @foreach($registration_policies as $i => $policy)
            <div class="d-flex gap-2 mb-2 align-items-start">
                <input type="text"
                       wire:model="registration_policies.{{ $i }}.lang"
                       class="form-control form-control-sm @error('registration_policies.'.$i.'.lang') is-invalid @enderror"
                       placeholder="en" style="max-width:70px">
                <div class="flex-grow-1">
                    <input type="url"
                           wire:model="registration_policies.{{ $i }}.url"
                           class="form-control form-control-sm @error('registration_policies.'.$i.'.url') is-invalid @enderror"
                           placeholder="https://federation.example.org/policy">
                    @error("registration_policies.{$i}.lang") <div class="text-danger" style="font-size:.8rem">{{ $message }}</div> @enderror
                    @error("registration_policies.{$i}.url")  <div class="text-danger" style="font-size:.8rem">{{ $message }}</div> @enderror
                </div>
                <button type="button" wire:click="removeRegistrationPolicy({{ $i }})"
                        class="btn btn-sm btn-outline-danger flex-shrink-0">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            @endforeach
            <button type="button" wire:click="addRegistrationPolicy"
                    class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-plus me-1"></i> Add policy URL
            </button>
        </div>

        {{-- Type --}}
        <div class="col-sm-6">
            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
            <div class="d-flex gap-4 pt-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="type_sp"
                           wire:model="type" value="sp" {{ $entityDbId ? 'disabled' : '' }}>
                    <label class="form-check-label" for="type_sp">
                        <i class="bi bi-window-desktop me-1"></i> Service Provider (SP)
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="type_idp"
                           wire:model="type" value="idp" {{ $entityDbId ? 'disabled' : '' }}>
                    <label class="form-check-label" for="type_idp">
                        <i class="bi bi-shield-lock me-1"></i> Identity Provider (IdP)
                    </label>
                </div>
            </div>
            @error('type')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        {{-- eduGAIN --}}
        <div class="col-sm-6 d-flex align-items-end pb-1">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="edugain" wire:model="edugain">
                <label class="form-check-label" for="edugain">
                    Export to <strong>eduGAIN</strong> interfederation
                </label>
            </div>
        </div>
        @if($edugain && count($certificates) === 0)
        <div class="col-12">
            <div class="alert alert-warning py-2 px-3 mb-0 small">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                This entity has no certificates and will be <strong>excluded from the eduGAIN metadata feed</strong>.
                Add a certificate on the <button type="button" class="btn btn-link btn-sm p-0 align-baseline" @click.prevent="tab = 'certificates'">Certificates tab</button>.
            </div>
        </div>
        @endif
        @php $hasSecurityContactEduGAIN = collect($contacts)->contains(fn($c) => ($c['type'] ?? '') === 'security' && !empty($c['email'])); @endphp
        @if($edugain && !$hasSecurityContactEduGAIN)
        <div class="col-12">
            <div class="alert alert-warning py-2 px-3 mb-0 small">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                This entity has no security contact and will be <strong>excluded from the eduGAIN metadata feed</strong>.
                Add a security contact on the <button type="button" class="btn btn-link btn-sm p-0 align-baseline" @click.prevent="tab = 'organisation'">Organisation tab</button>.
            </div>
        </div>
        @endif

        {{-- Display Name (English) --}}
        <div class="col-sm-8">
            <label for="name_en" class="form-label fw-semibold">
                Display Name (English) <span class="text-danger">*</span>
            </label>
            <input type="text" id="name_en" wire:model="name_en"
                   class="form-control @error('name_en') is-invalid @enderror"
                   placeholder="My University Identity Provider">
            @error('name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        {{-- Description (English) --}}
        <div class="col-12">
            <label for="description_en" class="form-label fw-semibold">
                Description (English) <span class="text-danger">*</span>
            </label>
            <textarea id="description_en" wire:model="description_en" rows="3"
                      class="form-control @error('description_en') is-invalid @enderror"
                      placeholder="Short description of this entity for federation operators and users."></textarea>
            @error('description_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-12">
            <p class="text-muted small mb-0">
                <i class="bi bi-translate me-1"></i>
                To add display names and descriptions in other languages, use the
                <button type="button" class="btn btn-link btn-sm p-0 align-baseline"
                        @click.prevent="tab = 'languages'">Languages tab</button>.
            </p>
        </div>

        {{-- Information URL --}}
        <div class="col-sm-6">
            <label for="information_url_en" class="form-label fw-semibold">Information URL</label>
            <input type="url" id="information_url_en" wire:model="information_url_en"
                   class="form-control @error('information_url_en') is-invalid @enderror"
                   placeholder="https://example.org/about">
            @error('information_url_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        {{-- Privacy URL --}}
        <div class="col-sm-6">
            <label for="privacy_url_en" class="form-label fw-semibold">
                Privacy Statement URL
                <span class="text-danger small">* required for CoCo v2</span>
            </label>
            <input type="url" id="privacy_url_en" wire:model="privacy_url_en"
                   class="form-control @error('privacy_url_en') is-invalid @enderror"
                   placeholder="https://example.org/privacy">
            @error('privacy_url_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        {{-- Logo preview + fields --}}
        <div class="col-12" x-data="{ logoUrl: @js($logo_url) }">
            <div class="row g-3">
                {{-- Preview pane --}}
                <div class="col-sm-3 d-flex align-items-center justify-content-center">
                    <div class="border rounded d-flex align-items-center justify-content-center bg-light"
                         style="width:120px;height:80px;overflow:hidden;">
                        <img x-show="logoUrl" :src="logoUrl" alt="Logo preview"
                             style="max-width:112px;max-height:72px;object-fit:contain;">
                        <span x-show="!logoUrl" class="text-muted small text-center px-2">
                            <i class="bi bi-image d-block fs-4 mb-1"></i>Logo preview
                        </span>
                    </div>
                </div>
                <div class="col-sm-9">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="logo_url" class="form-label fw-semibold">Logo URL</label>
                            <input type="url" id="logo_url" wire:model="logo_url"
                                   @input="logoUrl = $event.target.value"
                                   class="form-control @error('logo_url') is-invalid @enderror"
                                   placeholder="https://example.org/logo.png">
                            @error('logo_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label for="logo_height" class="form-label fw-semibold">Logo Height (px)</label>
                            <input type="number" id="logo_height" wire:model="logo_height"
                                   class="form-control @error('logo_height') is-invalid @enderror"
                                   min="1" max="200" placeholder="60">
                            @error('logo_height') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label for="logo_width" class="form-label fw-semibold">Logo Width (px)</label>
                            <input type="number" id="logo_width" wire:model="logo_width"
                                   class="form-control @error('logo_width') is-invalid @enderror"
                                   min="1" max="400" placeholder="200">
                            @error('logo_width') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>{{-- /tab basic --}}

{{-- ════════════════════════════════════════════════════════════════════════
     TAB 2 — ENDPOINTS
════════════════════════════════════════════════════════════════════════ --}}
<div x-show="tab === 'endpoints'">

    {{-- IdP SSO --}}
    @if($this->isIdp)
    <div class="card mb-4">
        <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
            <i class="bi bi-arrow-right-circle me-1"></i> SingleSignOnService (SSO) — IdP
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">At least one of HTTP-POST or HTTP-Redirect is required for IdPs.</p>
            <div class="row g-3">
                <div class="col-12">
                    <label for="sso_http_post" class="form-label fw-semibold">HTTP-POST Binding</label>
                    <input type="url" id="sso_http_post" wire:model="sso_http_post"
                           class="form-control @error('sso_http_post') is-invalid @enderror"
                           placeholder="https://idp.example.org/idp/profile/SAML2/POST/SSO">
                    @error('sso_http_post') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="sso_http_redirect" class="form-label fw-semibold">HTTP-Redirect Binding</label>
                    <input type="url" id="sso_http_redirect" wire:model="sso_http_redirect"
                           class="form-control @error('sso_http_redirect') is-invalid @enderror"
                           placeholder="https://idp.example.org/idp/profile/SAML2/Redirect/SSO">
                    @error('sso_http_redirect') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="sso_soap" class="form-label fw-semibold">SOAP Binding</label>
                    <input type="url" id="sso_soap" wire:model="sso_soap"
                           class="form-control @error('sso_soap') is-invalid @enderror"
                           placeholder="https://idp.example.org/idp/profile/SAML2/SOAP/ECP">
                    @error('sso_soap') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- IdP NameID formats + Scope --}}
    <div class="card mb-4">
        <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
            <i class="bi bi-person-badge me-1"></i> NameIDFormat + Scope
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">NameID Formats</label>
                    @foreach([
                        'urn:oasis:names:tc:SAML:2.0:nameid-format:transient'              => 'Transient (SAML 2.0)',
                        'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent'             => 'Persistent (SAML 2.0)',
                        'urn:oasis:names:tc:SAML:2.0:nameid-format:emailAddress'           => 'Email Address (SAML 2.0)',
                        'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'           => 'Email Address (SAML 1.1)',
                        'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified'            => 'Unspecified (SAML 1.1)',
                        'urn:oasis:names:tc:SAML:2.0:nameid-format:kerberos'              => 'Kerberos (SAML 2.0)',
                        'urn:oasis:names:tc:SAML:1.1:nameid-format:X509SubjectName'       => 'X.509 Subject Name (SAML 1.1)',
                        'urn:oasis:names:tc:SAML:1.1:nameid-format:WindowsDomainQualifiedName' => 'Windows Domain (SAML 1.1)',
                    ] as $urn => $label)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="nid_{{ $loop->index }}"
                               wire:model="nameid_formats" value="{{ $urn }}">
                        <label class="form-check-label small font-monospace" for="nid_{{ $loop->index }}">
                            {{ $label }}
                        </label>
                    </div>
                    @endforeach
                    @error('nameid_formats') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    @foreach($nameid_formats as $i => $_)
                        @error("nameid_formats.{$i}") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    @endforeach
                </div>
                <div class="col-sm-6">
                    <label for="scope" class="form-label fw-semibold">Shibboleth Scope</label>
                    <input type="text" id="scope" wire:model="scope"
                           class="form-control @error('scope') is-invalid @enderror"
                           placeholder="example.org">
                    <div class="form-text">Domain-only, e.g. <code>university.ie</code></div>
                    @error('scope') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- SP ACS --}}
    @if($this->isSp)
    <div class="card mb-4">
        <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
            <i class="bi bi-arrow-left-circle me-1"></i> AssertionConsumerService (ACS) — SP
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">HTTP-POST is required for SPs. Endpoints are indexed in order entered.</p>
            <div class="row g-3">
                <div class="col-12">
                    <label for="acs_http_post" class="form-label fw-semibold">
                        HTTP-POST Binding <span class="text-danger">*</span>
                    </label>
                    <input type="url" id="acs_http_post" wire:model="acs_http_post"
                           class="form-control @error('acs_http_post') is-invalid @enderror"
                           placeholder="https://sp.example.org/Shibboleth.sso/SAML2/POST">
                    @error('acs_http_post') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="acs_http_redirect" class="form-label fw-semibold">HTTP-Redirect Binding</label>
                    <input type="url" id="acs_http_redirect" wire:model="acs_http_redirect"
                           class="form-control @error('acs_http_redirect') is-invalid @enderror"
                           placeholder="https://sp.example.org/Shibboleth.sso/SAML2/Redirect">
                    @error('acs_http_redirect') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="acs_paos" class="form-label fw-semibold">PAOS Binding (ECP)</label>
                    <input type="url" id="acs_paos" wire:model="acs_paos"
                           class="form-control @error('acs_paos') is-invalid @enderror"
                           placeholder="https://sp.example.org/Shibboleth.sso/SAML2/ECP">
                    @error('acs_paos') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- SP security flags --}}
    <div class="card mb-4">
        <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
            <i class="bi bi-shield-check me-1"></i> SP Security Flags
        </div>
        <div class="card-body">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="want_authn_signed"
                       wire:model="sp_want_authn_requests_signed">
                <label class="form-check-label" for="want_authn_signed">
                    <code>WantAuthnRequestsSigned</code> — require signed AuthnRequests
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="want_assertions_signed"
                       wire:model="sp_want_assertions_signed">
                <label class="form-check-label" for="want_assertions_signed">
                    <code>WantAssertionsSigned</code> — require signed Assertions
                </label>
            </div>
        </div>
    </div>
    @endif

    {{-- SLO (both types) --}}
    <div class="card mb-4">
        <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
            <i class="bi bi-box-arrow-right me-1"></i> SingleLogoutService (SLO)
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label for="slo_http_post" class="form-label fw-semibold">HTTP-POST Binding</label>
                    <input type="url" id="slo_http_post" wire:model="slo_http_post"
                           class="form-control @error('slo_http_post') is-invalid @enderror"
                           placeholder="https://example.org/saml2/slo/post">
                    @error('slo_http_post') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="slo_http_redirect" class="form-label fw-semibold">HTTP-Redirect Binding</label>
                    <input type="url" id="slo_http_redirect" wire:model="slo_http_redirect"
                           class="form-control @error('slo_http_redirect') is-invalid @enderror"
                           placeholder="https://example.org/saml2/slo/redirect">
                    @error('slo_http_redirect') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="slo_soap" class="form-label fw-semibold">SOAP Binding</label>
                    <input type="url" id="slo_soap" wire:model="slo_soap"
                           class="form-control @error('slo_soap') is-invalid @enderror"
                           placeholder="https://example.org/saml2/slo/soap">
                    @error('slo_soap') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

</div>{{-- /tab endpoints --}}

{{-- ════════════════════════════════════════════════════════════════════════
     TAB 3 — CERTIFICATES
════════════════════════════════════════════════════════════════════════ --}}
<div x-show="tab === 'certificates'">

    @error('certificates')
        <div class="alert alert-danger mb-3">{{ $message }}</div>
    @enderror

    @foreach($certificates as $i => $cert)
    <div class="card mb-3">
        <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
            <span>
                <i class="bi bi-file-earmark-lock me-1"></i>
                Certificate {{ $i + 1 }}
            </span>
            @if(count($certificates) > 1)
            <button type="button" class="btn btn-sm btn-outline-danger"
                    wire:click="removeCertificate({{ $i }})"
                    wire:confirm="Remove this certificate?">
                <i class="bi bi-trash me-1"></i> Remove
            </button>
            @endif
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-4">
                    <label class="form-label fw-semibold">Use <span class="text-danger">*</span></label>
                    <select wire:model="certificates.{{ $i }}.use"
                            class="form-select @error('certificates.'.$i.'.use') is-invalid @enderror">
                        <option value="signing">signing</option>
                        <option value="encryption">encryption</option>
                        <option value="both">both</option>
                    </select>
                    @error('certificates.'.$i.'.use')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">PEM Certificate <span class="text-danger">*</span></label>
                    <textarea wire:model="certificates.{{ $i }}.pem" rows="6"
                              class="form-control font-monospace @error('certificates.'.$i.'.pem') is-invalid @enderror"
                              placeholder="-----BEGIN CERTIFICATE-----&#10;MII...&#10;-----END CERTIFICATE-----"></textarea>
                    @error('certificates.'.$i.'.pem')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Paste the full PEM block including the <code>-----BEGIN CERTIFICATE-----</code> header.</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="addCertificate">
        <i class="bi bi-plus-circle me-1"></i> Add Certificate
    </button>

</div>{{-- /tab certificates --}}

{{-- ════════════════════════════════════════════════════════════════════════
     TAB 4 — ORGANISATION
════════════════════════════════════════════════════════════════════════ --}}
<div x-show="tab === 'organisation'">
    <div class="row g-3">

        <div class="col-sm-6">
            <label for="org_name_en" class="form-label fw-semibold">
                Organisation Name (English) <span class="text-danger">*</span>
            </label>
            <input type="text" id="org_name_en" wire:model="org_name_en"
                   class="form-control @error('org_name_en') is-invalid @enderror"
                   placeholder="Example University">
            @error('org_name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-sm-6">
            <label for="org_name_native" class="form-label fw-semibold">Organisation Name (Native)</label>
            <input type="text" id="org_name_native" wire:model="org_name_native"
                   class="form-control @error('org_name_native') is-invalid @enderror"
                   placeholder="Universitatea Exemplu">
            @error('org_name_native') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-sm-6">
            <label for="org_display_name_en" class="form-label fw-semibold">
                Organisation Display Name (English) <span class="text-danger">*</span>
            </label>
            <input type="text" id="org_display_name_en" wire:model="org_display_name_en"
                   class="form-control @error('org_display_name_en') is-invalid @enderror"
                   placeholder="Example University">
            @error('org_display_name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-sm-6">
            <label for="org_url_en" class="form-label fw-semibold">
                Organisation URL <span class="text-danger">*</span>
            </label>
            <input type="url" id="org_url_en" wire:model="org_url_en"
                   class="form-control @error('org_url_en') is-invalid @enderror"
                   placeholder="https://www.example.org">
            @error('org_url_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        {{-- Coordinates + map picker --}}
        <div class="col-12"
             x-data="{
                mapOpen: false,
                _map: null,
                _marker: null,
                toggleMap() {
                    this.mapOpen = !this.mapOpen;
                    if (this.mapOpen) this.$nextTick(() => this.boot());
                },
                boot() {
                    if (this._map) { this._map.invalidateSize(); return; }
                    const lat = $wire.org_lat ?? 47.0;
                    const lng = $wire.org_lng ?? 28.0;
                    const zoom = $wire.org_lat ? 10 : 4;
                    this._map = L.map(this.$refs.mapEl).setView([lat, lng], zoom);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a>'
                    }).addTo(this._map);
                    if ($wire.org_lat && $wire.org_lng) {
                        this._marker = L.marker([lat, lng]).addTo(this._map);
                    }
                    this._map.on('click', e => {
                        const clat = parseFloat(e.latlng.lat.toFixed(7));
                        const clng = parseFloat(e.latlng.lng.toFixed(7));
                        if (this._marker) this._marker.setLatLng([clat, clng]);
                        else this._marker = L.marker([clat, clng]).addTo(this._map);
                        $wire.set('org_lat', clat);
                        $wire.set('org_lng', clng);
                    });
                }
             }">
            <div class="d-flex align-items-center gap-2 mb-2">
                <h6 class="fw-semibold text-muted mb-0">
                    <i class="bi bi-geo-alt me-1"></i> Organisation Coordinates
                    <span class="text-muted fw-normal small">(optional)</span>
                </h6>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto"
                        @click="toggleMap">
                    <i class="bi bi-map me-1"></i>
                    <span x-text="mapOpen ? 'Hide map' : 'Pick on map'"></span>
                </button>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-sm-6">
                    <label class="form-label small fw-semibold">Latitude</label>
                    <input type="number" step="0.0000001" min="-90" max="90"
                           wire:model.live="org_lat"
                           class="form-control form-control-sm @error('org_lat') is-invalid @enderror"
                           placeholder="e.g. 47.0105">
                    @error('org_lat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label small fw-semibold">Longitude</label>
                    <input type="number" step="0.0000001" min="-180" max="180"
                           wire:model.live="org_lng"
                           class="form-control form-control-sm @error('org_lng') is-invalid @enderror"
                           placeholder="e.g. 28.8638">
                    @error('org_lng') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div x-show="mapOpen" x-cloak
                 wire:ignore
                 style="height:300px;border:1px solid #dee2e6;border-radius:.375rem;overflow:hidden;">
                <div x-ref="mapEl" style="height:100%;width:100%;"></div>
            </div>
        </div>

        <div class="col-12 mt-2">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="fw-semibold text-muted mb-0">
                    <i class="bi bi-person-lines-fill me-1"></i> Contact Persons
                </h6>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        wire:click="addContact">
                    <i class="bi bi-plus-lg me-1"></i> Add Contact
                </button>
            </div>
            @error('contacts') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
        </div>

        @foreach($contacts as $ci => $contact)
        <div class="col-12">
            <div class="card border mb-2">
                <div class="card-body py-2 px-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-2">
                            <label class="form-label small fw-semibold mb-1">Type</label>
                            <select wire:model="contacts.{{ $ci }}.type"
                                    class="form-select form-select-sm @error('contacts.'.$ci.'.type') is-invalid @enderror">
                                <option value="technical">Technical</option>
                                <option value="support">Support</option>
                                <option value="security">Security</option>
                                <option value="administrative">Administrative</option>
                                <option value="billing">Billing</option>
                            </select>
                            @error('contacts.'.$ci.'.type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label small fw-semibold mb-1">First Name</label>
                            <input type="text"
                                   wire:model="contacts.{{ $ci }}.given_name"
                                   class="form-control form-control-sm @error('contacts.'.$ci.'.given_name') is-invalid @enderror"
                                   placeholder="Jane">
                            @error('contacts.'.$ci.'.given_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label small fw-semibold mb-1">Last Name</label>
                            <input type="text"
                                   wire:model="contacts.{{ $ci }}.sur_name"
                                   class="form-control form-control-sm @error('contacts.'.$ci.'.sur_name') is-invalid @enderror"
                                   placeholder="Smith">
                            @error('contacts.'.$ci.'.sur_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label small fw-semibold mb-1">Email</label>
                            <input type="email"
                                   wire:model="contacts.{{ $ci }}.email"
                                   class="form-control form-control-sm @error('contacts.'.$ci.'.email') is-invalid @enderror"
                                   placeholder="contact@example.org">
                            @error('contacts.'.$ci.'.email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label small fw-semibold mb-1">Phone</label>
                            <input type="text"
                                   wire:model="contacts.{{ $ci }}.phone"
                                   class="form-control form-control-sm @error('contacts.'.$ci.'.phone') is-invalid @enderror"
                                   placeholder="+1 555 0100">
                            @error('contacts.'.$ci.'.phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-sm-1 d-flex justify-content-end">
                            @if(count($contacts) > 1)
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    wire:click="removeContact({{ $ci }})"
                                    title="Remove contact">
                                <i class="bi bi-trash3"></i>
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        {{-- Federation membership — create only; on edit, memberships are managed via wizard --}}
        @if(!empty($availableFederations) && !$entityDbId)
        <div class="col-12 mt-2">
            <h6 class="fw-semibold text-muted mb-3">
                <i class="bi bi-collection me-1"></i> Federation Membership
            </h6>
            <div class="row g-2">
                @foreach($availableFederations as $fed)
                <div class="col-sm-6 col-lg-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               id="fed_{{ $fed['id'] }}"
                               wire:model="federation_ids"
                               value="{{ $fed['id'] }}">
                        <label class="form-check-label" for="fed_{{ $fed['id'] }}">
                            {{ $fed['name'] }}
                        </label>
                    </div>
                </div>
                @endforeach
            </div>
            @error('federation_ids') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            @error('federation_ids.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
        @endif

    </div>
</div>{{-- /tab organisation --}}

{{-- ════════════════════════════════════════════════════════════════════════
     TAB 5 — REFEDS
════════════════════════════════════════════════════════════════════════ --}}
<div x-show="tab === 'refeds'">
    <div class="row g-4">

        {{-- Entity Categories — split by type --}}
        @php
        $guideLink = route('guide.show', 'entities') . '#entity-categories';

        $accessCategories = [
            'http://refeds.org/category/research-and-scholarship' => [
                'label' => 'Research &amp; Scholarship (R&amp;S)',
                'tip'   => 'SP asserts it serves the research community. IdPs agreeing to R&S release ePPN, mail and displayName to all such SPs without per-SP agreements.',
            ],
            'https://refeds.org/category/code-of-conduct/v2' => [
                'label' => 'Code of Conduct v2 (CoCo)',
                'tip'   => 'SP has accepted the GÉANT Data Protection Code of Conduct. Enables policy-based attribute release. Requires a Privacy Statement URL.',
            ],
            'https://refeds.org/category/anonymous' => [
                'label' => 'Anonymous',
                'tip'   => 'SP only needs proof of authentication — no personal data. IdP releases only home organisation and broad affiliation.',
            ],
            'https://refeds.org/category/pseudonymous' => [
                'label' => 'Pseudonymous',
                'tip'   => 'SP needs a stable pairwise pseudonym to recognise returning users without receiving real identity data.',
            ],
            'https://refeds.org/category/personalized' => [
                'label' => 'Personalized',
                'tip'   => 'SP needs full personal data (name, email, affiliation). Highest REFEDS Personalization tier — only assert when genuinely required.',
            ],
        ];

        $hideFromDiscovery = [
            'http://refeds.org/category/hide-from-discovery' => [
                'label' => 'Hide from Discovery',
                'tip'   => 'Instructs discovery services (WAYF/DS) to omit this IdP from their default list. Use for internal or test IdPs not intended for end users.',
            ],
        ];
        @endphp

        {{-- SP: entity-category (membership) --}}
        @if($this->isSp)
        <div class="col-12">
            <h6 class="fw-semibold mb-1">
                Entity Categories
                <a href="{{ $guideLink }}" target="_blank" class="text-muted ms-1" style="font-size:.85rem;" title="Open guide: REFEDS Entity Categories"><i class="bi bi-info-circle"></i></a>
            </h6>
            <p class="text-muted small">Declare which REFEDS categories this SP belongs to (<code>entity-category</code>).</p>
            @foreach($accessCategories as $uri => $meta)
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="ec_{{ $loop->index }}" wire:model="entity_categories" value="{{ $uri }}">
                <label class="form-check-label" for="ec_{{ $loop->index }}">
                    {!! $meta['label'] !!}
                    <span x-data x-init="new bootstrap.Tooltip($el)" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ $meta['tip'] }}" class="text-muted ms-1" style="cursor:default;font-size:.75rem;"><i class="bi bi-info-circle"></i></span>
                    <span class="text-muted small font-monospace ms-1">{{ $uri }}</span>
                </label>
            </div>
            @endforeach
            @error('entity_categories') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            @error('entity_categories.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- IdP: entity-category (Hide from Discovery) + entity-category-support --}}
        @elseif($this->isIdp)
        <div class="col-12">
            <h6 class="fw-semibold mb-1">
                Entity Categories
                <a href="{{ $guideLink }}" target="_blank" class="text-muted ms-1" style="font-size:.85rem;" title="Open guide: REFEDS Entity Categories"><i class="bi bi-info-circle"></i></a>
            </h6>
            <p class="text-muted small">IdP-level category assertions (<code>entity-category</code>).</p>
            @foreach($hideFromDiscovery as $uri => $meta)
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="ec_hfd" wire:model="entity_categories" value="{{ $uri }}">
                <label class="form-check-label" for="ec_hfd">
                    {!! $meta['label'] !!}
                    <span x-data x-init="new bootstrap.Tooltip($el)" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ $meta['tip'] }}" class="text-muted ms-1" style="cursor:default;font-size:.75rem;"><i class="bi bi-info-circle"></i></span>
                    <span class="text-muted small font-monospace ms-1">{{ $uri }}</span>
                </label>
            </div>
            @endforeach
            @error('entity_categories') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="col-12">
            <h6 class="fw-semibold mb-1">
                Entity Category Support
                <a href="{{ $guideLink }}" target="_blank" class="text-muted ms-1" style="font-size:.85rem;" title="Open guide: REFEDS Entity Categories"><i class="bi bi-info-circle"></i></a>
            </h6>
            <p class="text-muted small">Declare which SP categories this IdP agrees to support — releasing the defined attribute bundle automatically (<code>entity-category-support</code>).</p>
            @foreach($accessCategories as $uri => $meta)
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="ecs_{{ $loop->index }}" wire:model="entity_category_support" value="{{ $uri }}">
                <label class="form-check-label" for="ecs_{{ $loop->index }}">
                    {!! $meta['label'] !!}
                    <span x-data x-init="new bootstrap.Tooltip($el)" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ $meta['tip'] }}" class="text-muted ms-1" style="cursor:default;font-size:.75rem;"><i class="bi bi-info-circle"></i></span>
                    <span class="text-muted small font-monospace ms-1">{{ $uri }}</span>
                </label>
            </div>
            @endforeach
            @error('entity_category_support') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            @error('entity_category_support.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        @else
        <div class="col-12">
            <h6 class="fw-semibold mb-1">Entity Categories</h6>
            <p class="text-muted small text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Save the entity with a type selected (SP or IdP) to configure entity categories.</p>
        </div>
        @endif

        @foreach($assurance_profiles as $i => $_)
            @error("assurance_profiles.{$i}") <div class="text-danger small mt-1">Assurance profile [{{ $i }}]: {{ $message }}</div> @enderror
        @endforeach

        {{-- SIRTFI --}}
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="sirtfi" wire:model="sirtfi">
                        <label class="form-check-label fw-semibold" for="sirtfi">
                            Assert REFEDS SIRTFI compliance
                            @php $hasSecurityContact = collect($contacts)->contains(fn($c) => ($c['type'] ?? '') === 'security' && !empty($c['email'])); @endphp
                            @if($hasSecurityContact)
                                <span class="badge bg-success ms-1"><i class="bi bi-check-lg me-1"></i>Security contact present</span>
                            @else
                                <span class="badge bg-warning text-dark ms-1">Requires security contact</span>
                            @endif
                        </label>
                    </div>
                    <p class="small text-muted mb-0 mt-1">
                        Adds <code>https://refeds.org/sirtfi</code> to the entity's assurance profile attributes.
                        A security contact email is mandatory.
                    </p>
                    @error('contacts')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        @foreach($requested_attributes as $i => $_)
            @error("requested_attributes.{$i}.name") <div class="text-danger small">Requested attribute [{{ $i }}]: {{ $message }}</div> @enderror
        @endforeach

        {{-- SP Requested Attributes --}}
        @if($this->isSp)
        <div class="col-12">
            <div class="d-flex align-items-center gap-2 mb-1">
                <h6 class="fw-semibold mb-0">Requested Attributes (SP)</h6>
                @if($entityDbId)
                    <a href="{{ route('entities.requested-attributes', $entityDbId) }}"
                       class="btn btn-sm btn-outline-secondary ms-auto">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Manage on dedicated page
                    </a>
                @endif
            </div>
            <p class="text-muted small">
                Attributes the SP requests from the IdP — rendered as
                <code>&lt;md:RequestedAttribute&gt;</code> in the SP metadata.
            </p>

            {{-- Schema filter --}}
            <div class="d-flex gap-1 mb-2 flex-wrap">
                <button type="button" wire:click="$set('attributeSchema', '')"
                        class="btn btn-xs {{ $attributeSchema === '' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        style="font-size:.75rem;padding:.15rem .5rem;">
                    All
                </button>
                @foreach(['eduperson' => 'eduPerson', 'ldap' => 'LDAP', 'schac' => 'SCHAC', 'voperson' => 'voPerson'] as $schKey => $schLabel)
                <button type="button" wire:click="$set('attributeSchema', '{{ $schKey }}')"
                        class="btn btn-xs {{ $attributeSchema === $schKey ? 'btn-primary' : 'btn-outline-secondary' }}"
                        style="font-size:.75rem;padding:.15rem .5rem;">
                    {{ $schLabel }}
                </button>
                @endforeach
            </div>

            @php
                $displayedAttributes = $this->storedRequestedAttributes;
                if ($attributeSchema !== '') {
                    $displayedAttributes = $displayedAttributes->filter(
                        fn($ra) => ($ra->attributeDefinition?->schema ?? '') === $attributeSchema
                    );
                }
            @endphp

            @if($displayedAttributes->isNotEmpty())
            <table class="table table-sm table-bordered mt-2 mb-0" style="font-size:.82rem;">
                <thead class="table-light">
                    <tr>
                        <th class="fw-semibold text-secondary" style="font-size:.72rem;letter-spacing:.04em;">Attribute</th>
                        <th class="fw-semibold text-secondary" style="font-size:.72rem;letter-spacing:.04em;">Friendly Name</th>
                        <th class="fw-semibold text-secondary text-center" style="font-size:.72rem;letter-spacing:.04em;width:90px;">Required</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($displayedAttributes as $ra)
                    <tr>
                        <td class="font-monospace small text-break">{{ $ra->attributeDefinition?->saml2_oid ?? $ra->attributeDefinition?->name ?? '—' }}</td>
                        <td>{{ $ra->attributeDefinition?->full_name ?? '—' }}</td>
                        <td class="text-center">
                            @if($ra->is_required)
                                <span class="badge bg-danger" style="font-size:.7rem;">Yes</span>
                            @else
                                <span class="text-muted small">No</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-muted small mt-2 mb-0">No requested attributes defined. Use the dedicated page to add them.</p>
            @endif
        </div>
        @endif

    </div>
</div>{{-- /tab refeds --}}

{{-- ════════════════════════════════════════════════════════════════════════
     TAB 6 — LANGUAGES
════════════════════════════════════════════════════════════════════════ --}}
<div x-show="tab === 'languages'">
    <div class="alert alert-info mb-3 small">
        <i class="bi bi-info-circle me-1"></i>
        English (en) fields are managed in the <strong>Basic Info</strong> and <strong>Organisation</strong> tabs.
        Use this tab to add translations for other languages.
        These appear as <code>xml:lang</code> variants in the generated SAML2 metadata.
    </div>

    <button type="button" class="btn btn-outline-primary btn-sm mb-3"
            wire:click="addLanguageVariant">
        <i class="bi bi-plus me-1"></i>Add language variant
    </button>

    @foreach($additionalLangs as $index => $variant)
    <div class="card mb-2">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Language</label>
                    <select wire:model="additionalLangs.{{ $index }}.lang"
                            class="form-select form-select-sm @error('additionalLangs.'.$index.'.lang') is-invalid @enderror">
                        @foreach($availableLangs as $code => $name)
                            <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    @error('additionalLangs.'.$index.'.lang')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Field</label>
                    <select wire:model="additionalLangs.{{ $index }}.field"
                            class="form-select form-select-sm @error('additionalLangs.'.$index.'.field') is-invalid @enderror">
                        @foreach($multilingualFields as $fieldKey => $fieldLabel)
                            <option value="{{ $fieldKey }}">{{ $fieldLabel }}</option>
                        @endforeach
                    </select>
                    @error('additionalLangs.'.$index.'.field')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Value</label>
                    <input type="text"
                           wire:model="additionalLangs.{{ $index }}.value"
                           class="form-control form-control-sm @error('additionalLangs.'.$index.'.value') is-invalid @enderror"
                           placeholder="Value in selected language">
                    @error('additionalLangs.'.$index.'.value')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button"
                            class="btn btn-outline-danger btn-sm flex-shrink-0"
                            style="width:2rem;height:2rem;padding:0;"
                            wire:click="removeLanguageVariant({{ $index }})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    @if(empty($additionalLangs))
    <p class="text-muted small">
        No additional language variants defined.
        Click "Add language variant" to add translations.
    </p>
    @endif
</div>{{-- /tab languages --}}

{{-- ════════════════════════════════════════════════════════════════════════
     TAB 7 — OIDC CONFIGURATION (only for oidc entities)
════════════════════════════════════════════════════════════════════════ --}}
@if($type === 'oidc')
<div x-show="tab === 'oidc'">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label fw-semibold">Redirect URIs <span class="text-danger">*</span></label>
            <textarea wire:model="oidcRedirectUris"
                      rows="5"
                      class="form-control font-monospace small"
                      placeholder="One URL per line&#10;https://app.example.com/callback"></textarea>
            @error('oidcRedirectUris')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text">One redirect URI per line. Must use https:// (or http://localhost).</div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Grant Types</label>
            @foreach (['authorization_code', 'client_credentials', 'refresh_token'] as $gt)
            <div class="form-check">
                <input class="form-check-input" type="checkbox"
                       wire:model="oidcGrantTypes"
                       value="{{ $gt }}"
                       id="gt_{{ $loop->index }}">
                <label class="form-check-label" for="gt_{{ $loop->index }}">
                    <code>{{ $gt }}</code>
                </label>
            </div>
            @endforeach
        </div>

        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label fw-semibold">Scopes</label>
                <input type="text" wire:model="oidcScopes"
                       class="form-control"
                       placeholder="openid profile email">
                <div class="form-text">Space-separated. Must include <code>openid</code>.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Application Type</label>
                <select wire:model="oidcApplicationType" class="form-select">
                    <option value="web">web</option>
                    <option value="native">native</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Token Endpoint Auth Method</label>
                <select wire:model="oidcTokenEndpointAuthMethod" class="form-select">
                    <option value="client_secret_basic">client_secret_basic</option>
                    <option value="client_secret_post">client_secret_post</option>
                    <option value="none">none (public client)</option>
                </select>
            </div>
        </div>

        <div class="col-12">
            <button type="button" wire:click="saveOidcConfig" class="btn btn-primary">
                <span wire:loading wire:target="saveOidcConfig" class="spinner-border spinner-border-sm me-1"></span>
                Save OIDC Config
            </button>
        </div>
    </div>
</div>
@endif

{{-- ── Validation Gate ─────────────────────────────────────────────────────── --}}
<div class="mt-4 pt-3 border-top">
    <div class="d-flex align-items-center gap-2 mb-3">
        <button type="button"
                class="btn btn-outline-secondary btn-sm"
                wire:click="runValidationGate"
                wire:loading.attr="disabled"
                wire:target="runValidationGate">
            <span wire:loading wire:target="runValidationGate"
                  class="spinner-border spinner-border-sm me-1" role="status"></span>
            <i class="bi bi-shield-check me-1" wire:loading.remove wire:target="runValidationGate"></i>
            Validate
        </button>
        @if($hasRunValidation)
            @if($validationPassed)
                <span class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Validation passed</span>
            @else
                <span class="text-danger small"><i class="bi bi-exclamation-circle-fill me-1"></i>Validation found issues</span>
            @endif
        @endif
    </div>

    @if($hasRunValidation)
    <div class="mb-3">
        <table class="table table-sm table-bordered mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;">Rule</th>
                    <th style="width:80px;">Status</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                @foreach($validationResults as $result)
                <tr class="{{ $result['status'] === 'fail' ? 'table-danger' : ($result['status'] === 'warning' ? 'table-warning' : '') }}">
                    <td class="fw-semibold">{{ $result['id'] }}</td>
                    <td>
                        @if($result['status'] === 'pass')
                            <span class="text-success"><i class="bi bi-check-circle"></i> Pass</span>
                        @elseif($result['status'] === 'fail')
                            <span class="text-danger"><i class="bi bi-x-circle"></i> Fail</span>
                        @else
                            <span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Warning</span>
                        @endif
                    </td>
                    <td>{{ $result['message'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if(! $validationPassed && collect($validationResults)->where('status', 'fail')->isEmpty())
        {{-- Only warnings, no hard failures — allow acknowledgement --}}
        <div class="form-check mt-2">
            <input type="checkbox"
                   class="form-check-input"
                   id="warningsAcknowledged"
                   wire:model="warningsAcknowledged">
            <label class="form-check-label small" for="warningsAcknowledged">
                I acknowledge the warnings and want to submit anyway.
            </label>
        </div>
        @endif
    </div>
    @endif

    @error('validation')
    <div class="alert alert-danger small p-2 mb-3">{{ $message }}</div>
    @enderror

    <div class="d-flex justify-content-between align-items-center">
        <a href="{{ route('entities.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Cancel
        </a>
        @auth
        @if(auth()->user()->hasAnyRole(['Guest', 'Entity Manager']))
        <button type="submit"
                class="btn btn-primary btn-sm px-4"
                wire:loading.attr="disabled"
                @if($hasRunValidation && ! $validationPassed && ! $warningsAcknowledged) disabled @endif>
            <span wire:loading wire:target="save" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
            <i class="bi bi-floppy me-1" wire:loading.remove wire:target="save"></i>
            <span wire:loading wire:target="save">Saving…</span>
            <span wire:loading.remove wire:target="save">{{ $entityDbId ? 'Save Changes' : 'Register Entity' }}</span>
        </button>
        @else
        <button type="submit" class="btn btn-primary btn-sm px-4" wire:loading.attr="disabled">
            <span wire:loading wire:target="save" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
            <i class="bi bi-floppy me-1" wire:loading.remove wire:target="save"></i>
            <span wire:loading wire:target="save">Saving…</span>
            <span wire:loading.remove wire:target="save">{{ $entityDbId ? 'Save Changes' : 'Register Entity' }}</span>
        </button>
        @endif
        @endauth
    </div>
</div>

</form>
</div>
