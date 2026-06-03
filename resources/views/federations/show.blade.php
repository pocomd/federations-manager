@extends('layouts.app')

@section('title', $federation->name . ' — Federation Registry')

@section('content')
<div class="container-fluid py-4 px-4">

    {{-- Header ──────────────────────────────────────────────────────────── --}}
    <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federations.index') }}">Federations</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $federation->name }}</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-1">
                {{ $federation->name }}
                @if($federation->status === 'active')
                    <span class="badge bg-success ms-2 fs-6 fw-normal align-middle">Active</span>
                @else
                    <span class="badge bg-secondary ms-2 fs-6 fw-normal align-middle">Inactive</span>
                @endif
            </h1>
            <p class="text-muted small mb-0 font-monospace">{{ $federation->uri }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            @can('federation.edit')
                <a href="{{ route('federations.mail.compose', $federation) }}"
                   class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-envelope me-1"></i> Send Email
                </a>
                <a href="{{ route('federations.mail.templates.index', $federation) }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-file-earmark-text me-1"></i> Email Templates
                </a>
                @if($federation->status === 'active')
                <button type="button" class="btn btn-outline-warning btn-sm"
                        onclick="Livewire.dispatch('open-deactivate-modal', { federationId: '{{ $federation->id }}' })">
                    <i class="bi bi-slash-circle me-1"></i> {{ __('app.action_deactivate') }}
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm"
                        disabled
                        title="{{ __('app.federation_delete_blocked_active') }}">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
                @elseif($federation->status === 'inactive')
                <form method="POST"
                      action="{{ route('federations.destroy', $federation) }}"
                      class="d-inline"
                      onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.federation.delete_title, text: Lang.federation.delete_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                </form>
                @endif
            @endcan
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Tabs ─────────────────────────────────────────────────────────────── --}}
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" data-bs-toggle="tab"
               href="#general" role="tab" aria-selected="true">
                <i class="bi bi-info-circle me-1"></i>General
            </a>
        </li>
        <li class="nav-item" role="presentation"
            x-data="{ pendingCount: {{ $pendingCount }} }"
            x-on:membership-pending-count-changed.window="pendingCount = $event.detail.count">
            <a class="nav-link" data-bs-toggle="tab"
               href="#membership" role="tab">
                <i class="bi bi-people me-1"></i>Membership
                <span class="badge bg-warning text-dark ms-1" x-show="pendingCount > 0" x-text="pendingCount" style="{{ $pendingCount > 0 ? '' : 'display:none' }}">{{ $pendingCount }}</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab"
               href="#metadata" role="tab">
                <i class="bi bi-file-code me-1"></i>Metadata
            </a>
        </li>
        <li class="nav-item" role="presentation"
            x-data="{ attrCount: {{ $attributeCount }} }"
            x-on:attributes-count-changed.window="attrCount = $event.detail.count">
            <a class="nav-link" data-bs-toggle="tab"
               href="#attributes" role="tab">
                <i class="bi bi-tags me-1"></i>Attributes
                <span class="badge bg-secondary ms-1" x-text="attrCount">{{ $attributeCount }}</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab"
               href="#validators" role="tab">
                <i class="bi bi-shield-check me-1"></i>Validators
                <span class="badge bg-secondary ms-1">{{ $federation->validators->count() }}</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab"
               href="#tab-rules" role="tab">
                <i class="bi bi-shield-check me-1"></i>{{ __('app.tab_rules') }}
                <span class="badge bg-secondary ms-1">{{ $federation->ruleConfigs->count() }}</span>
            </a>
        </li>
        <li class="nav-item" role="presentation"
            x-data="{ contactCount: {{ $federation->contacts->count() }} }"
            x-on:contacts-updated.window="contactCount = $event.detail.count">
            <a class="nav-link" data-bs-toggle="tab"
               href="#tab-contacts" role="tab">
                <i class="bi bi-person-lines-fill me-1"></i>Contacts
                <span class="badge bg-secondary ms-1" x-text="contactCount">{{ $federation->contacts->count() }}</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab"
               href="#tab-managers" role="tab">
                <i class="bi bi-person-badge me-1"></i>Managers
                @if($managerCount > 0)
                    <span class="badge bg-secondary ms-1">{{ $managerCount }}</span>
                @endif
            </a>
        </li>
        @if(Auth::user()->hasRole('Admin') || $federation->managers->contains(Auth::id()))
        <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab"
               href="#tab-signing-keys" role="tab">
                <i class="bi bi-key me-1"></i>Signing Keys
                @php
                    $signingDriver = app(\App\Services\Signing\SigningDriverFactory::class)->make($federation);
                    $signingReady  = $signingDriver->hasKey($federation) && $signingDriver->hasCert($federation);
                @endphp
                @if($signingReady)
                    <i class="bi bi-check-circle-fill text-success ms-1" title="Key pair configured"></i>
                @else
                    <i class="bi bi-exclamation-circle text-warning ms-1" title="Key pair incomplete or not configured"></i>
                @endif
            </a>
        </li>
        @endif
    </ul>

    <div class="tab-content">

        {{-- ═══ TAB 1: General ══════════════════════════════════════════════ --}}
        <div class="tab-pane fade show active" id="general" role="tabpanel">
            <div class="row g-4">

                {{-- Left — details --}}
                <div class="col-md-8">
                    <div class="card h-100" x-data="{ editMode: {{ $errors->any() ? 'true' : 'false' }} }">
                        <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
                            <span class="fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                                <i class="bi bi-collection me-1"></i> Federation Details
                            </span>
                            @can('federation.edit')
                            <button @click="editMode = !editMode"
                                    class="btn btn-sm btn-outline-secondary"
                                    x-text="editMode ? 'Cancel' : 'Edit Details'"></button>
                            @endcan
                        </div>
                        <div class="card-body">

                            {{-- View mode --}}
                            <div x-show="!editMode">
                                <dl class="row mb-0 small">
                                    <dt class="col-4 text-muted">Name</dt>
                                    <dd class="col-8">{{ $federation->name }}</dd>

                                    <dt class="col-4 text-muted">Name in metadata (URI)</dt>
                                    <dd class="col-8 font-monospace small text-break">{{ $federation->uri }}</dd>

                                    <dt class="col-4 text-muted">Description</dt>
                                    <dd class="col-8">{{ $federation->description ?? '—' }}</dd>

                                    <dt class="col-4 text-muted">Status</dt>
                                    <dd class="col-8">
                                        @if($federation->status === 'active')
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </dd>

                                    <dt class="col-4 text-muted">Registration Policies</dt>
                                    <dd class="col-8">
                                        @if($federation->registrationPolicies->isEmpty())
                                            <span class="text-warning small">
                                                <i class="bi bi-exclamation-triangle me-1"></i>No policies defined
                                            </span>
                                            @can('federation.edit')
                                            — <a href="{{ route('federations.policies.index', $federation) }}" class="small">Add policy</a>
                                            @endcan
                                        @else
                                            @foreach($federation->registrationPolicies as $policy)
                                            <div>
                                                <span class="badge bg-secondary me-1">{{ strtoupper($policy->lang) }}</span>
                                                <a href="{{ $policy->url }}" target="_blank" rel="noopener" class="small">
                                                    {{ $policy->url }}
                                                </a>
                                            </div>
                                            @endforeach
                                        @endif
                                    </dd>

                                    <dt class="col-4 text-muted">Created</dt>
                                    <dd class="col-8">{{ $federation->created_at->diffForHumans() }}</dd>

                                    <dt class="col-4 text-muted">Download contacts</dt>
                                    <dd class="col-8"
                                        x-data="{
                                            entityType: 'all',
                                            contactType: 'all',
                                            format: 'csv',
                                            unique: false,
                                            get url() {
                                                return '{{ route('federations.contacts.download', $federation) }}'
                                                    + '?type=' + this.entityType
                                                    + '&contact_type=' + this.contactType
                                                    + '&format=' + this.format
                                                    + (this.unique ? '&unique=1' : '');
                                            }
                                        }">
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <select class="form-select form-select-sm" style="width:auto;" x-model="entityType">
                                                <option value="all">All entities</option>
                                                <option value="idp">IdPs only</option>
                                                <option value="sp">SPs only</option>
                                            </select>
                                            <select class="form-select form-select-sm" style="width:auto;" x-model="contactType">
                                                <option value="all">All contact types</option>
                                                <option value="technical">Technical</option>
                                                <option value="support">Support</option>
                                                <option value="security">Security</option>
                                                <option value="administrative">Administrative</option>
                                            </select>
                                            <select class="form-select form-select-sm" style="width:auto;" x-model="format">
                                                <option value="csv">CSV</option>
                                                <option value="txt">Plain text</option>
                                            </select>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="form-check form-check-inline mb-0">
                                                <input class="form-check-input" type="checkbox" id="unique-contacts" x-model="unique">
                                                <label class="form-check-label small" for="unique-contacts">Deduplicate emails</label>
                                            </div>
                                            <a :href="url" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-download me-1"></i>Download
                                            </a>
                                        </div>
                                    </dd>
                                </dl>
                            </div>

                            {{-- Edit mode --}}
                            <div x-show="editMode" x-cloak>
                                <form action="{{ route('federations.update', $federation) }}" method="POST">
                                    @csrf
                                    @method('PATCH')

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name"
                                               class="form-control form-control-sm @error('name') is-invalid @enderror"
                                               value="{{ old('name', $federation->name) }}" required>
                                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Registration Authority URI <span class="text-danger">*</span></label>
                                        <input type="url" name="uri"
                                               class="form-control form-control-sm @error('uri') is-invalid @enderror"
                                               value="{{ old('uri', $federation->uri) }}" required>
                                        @error('uri')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Description</label>
                                        <textarea name="description" rows="2"
                                                  class="form-control form-control-sm @error('description') is-invalid @enderror">{{ old('description', $federation->description) }}</textarea>
                                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Status</label>
                                        <select name="status" class="form-select form-select-sm @error('status') is-invalid @enderror">
                                            <option value="active"   {{ old('status', $federation->status) === 'active'   ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ old('status', $federation->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Metadata URL</label>
                                        <input type="url" name="metadata_url"
                                               class="form-control form-control-sm @error('metadata_url') is-invalid @enderror"
                                               value="{{ old('metadata_url', $federation->metadata_url) }}">
                                        @error('metadata_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                        <button type="button" @click="editMode = false" class="btn btn-outline-secondary btn-sm">Cancel</button>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Right — pie chart --}}
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                            <i class="bi bi-pie-chart me-1"></i> Members
                        </div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center">
                            <canvas id="entityPieChart" height="200"></canvas>
                            <div class="mt-3 d-flex gap-2 flex-wrap justify-content-center">
                                <span class="badge bg-primary">{{ $idpCount }} IdPs</span>
                                <span class="badge bg-success">{{ $spCount }} SPs</span>
                                @if($pendingCount > 0)
                                    <span class="badge bg-warning text-dark">{{ $pendingCount }} pending</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- eduGAIN Federation Status --}}
            @if(\App\Models\SystemPreference::get('edugain_checks_enabled', false) && \App\Models\SystemPreference::get('edugain_federation_code'))
            <div class="card mt-4"
                 x-data="{ data: null, loading: true }"
                 x-init="
                    fetch('/edugain/federation')
                        .then(r => r.json())
                        .then(d => { data = d; loading = false; })
                 ">
                <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="card-header-label">
                        <i class="bi bi-globe me-1"></i>
                        eduGAIN Status
                        <span class="badge bg-secondary ms-1">
                            {{ \App\Models\SystemPreference::get('edugain_federation_code') }}
                        </span>
                    </span>
                    <a href="https://technical.edugain.org/compliance_audit"
                       target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-secondary">
                        Compliance Audit
                        <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body">
                    <div x-show="loading">
                        <span class="spinner-border spinner-border-sm me-1"></span>Loading...
                    </div>
                    <div x-show="!loading && data && !data.error">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="fs-4 fw-bold" x-text="data?.entitycount ?? '—'"></div>
                                <div class="text-muted small">Total entities</div>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bold" x-text="data?.idpcount ?? '—'"></div>
                                <div class="text-muted small">IdPs</div>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bold" x-text="data?.spcount ?? '—'"></div>
                                <div class="text-muted small">SPs</div>
                            </div>
                        </div>
                    </div>
                    <div x-show="!loading && data?.error" class="text-muted small" x-text="data?.error"></div>
                </div>
            </div>
            @endif

        </div>{{-- /tab-pane general --}}

        {{-- ═══ TAB 2: Membership ════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="membership" role="tabpanel">
            @livewire('federation-membership', ['federationId' => $federation->id])
        </div>{{-- /tab-pane membership --}}

        {{-- ═══ TAB 3: Metadata ══════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="metadata" role="tabpanel">

            <div class="card mb-4">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-link-45deg me-1"></i> Metadata Endpoints
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Type</th>
                                <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">URL</th>
                                <th class="pe-3 text-end fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="ps-3">{{ __('app.metadata_feed_full') }}</td>
                                <td>
                                    <code class="small">{{ route('metadata.feed', $federation) }}</code>
                                </td>
                                <td class="pe-3 text-end">
                                    <a href="{{ route('metadata.feed', $federation) }}" target="_blank"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>Open
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-3">{{ __('app.metadata_feed_edugain') }}</td>
                                <td>
                                    <code class="small">{{ route('metadata.edugain', $federation) }}</code>
                                </td>
                                <td class="pe-3 text-end">
                                    <a href="{{ route('metadata.edugain', $federation) }}" target="_blank"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>Open
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-3">{{ __('app.metadata_download_cached') }}</td>
                                <td>
                                    <code class="small">{{ route('metadata.download', $federation) }}</code>
                                </td>
                                <td class="pe-3 text-end">
                                    <a href="{{ route('metadata.download', $federation) }}" target="_blank"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>Download
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-globe me-1"></i> {{ __('app.metadata_public_feeds_title') }}
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">{{ __('app.metadata_public_feeds_description') }}</p>
                    <div class="d-flex flex-column gap-2">
                        <div>
                            <span class="badge bg-secondary me-2">Full</span>
                            <code class="small">{{ route('metadata.feed', $federation) }}</code>
                        </div>
                        <div>
                            <span class="badge bg-info text-dark me-2">eduGAIN</span>
                            <code class="small">{{ route('metadata.edugain', $federation) }}</code>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Jagger compatibility endpoint --}}
            @can('update', $federation)
            @livewire('federation-jagger-compat', ['federationId' => $federation->id])
            @endcan

            @livewire('federation-metadata-sign', ['federationId' => $federation->id])

        </div>{{-- /tab-pane metadata --}}

        {{-- ═══ TAB 4: Attributes ════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="attributes" role="tabpanel">
            @livewire('federation-attributes', ['federationId' => $federation->id])
        </div>{{-- /tab-pane attributes --}}

        {{-- ═══ TAB 5: Validators ════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="validators" role="tabpanel">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-shield-check me-1"></i>
                    External Validators ({{ $federation->validators->count() }})
                </h6>
                @can('federation.edit')
                <a href="{{ route('federations.validators.index', $federation) }}"
                   class="btn btn-sm btn-outline-primary">
                    Manage Validators
                </a>
                @endcan
            </div>

            @if($federation->validators->isEmpty())
                <p class="text-muted small">
                    No validators configured.
                    @can('federation.edit')
                    <a href="{{ route('federations.validators.create', $federation) }}">Add a validator →</a>
                    @endcan
                </p>
            @else
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-sm table-hover table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Name</th>
                                <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">URL</th>
                                <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Method</th>
                                <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">On Registration</th>
                                <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Mandatory</th>
                                <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Enabled</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($federation->validators as $v)
                            <tr>
                                <td class="ps-3">{{ $v->name }}</td>
                                <td>
                                    <a href="{{ $v->url }}" target="_blank" rel="noopener"
                                       class="text-truncate d-inline-block small"
                                       style="max-width:250px;">
                                        {{ $v->url }}
                                    </a>
                                </td>
                                <td><span class="badge bg-secondary">{{ $v->http_method }}</span></td>
                                <td>
                                    @if($v->enabled_on_registration)
                                        <span class="badge bg-info text-dark">Yes</span>
                                    @else
                                        <span class="text-muted small">No</span>
                                    @endif
                                </td>
                                <td>
                                    @if($v->mandatory)
                                        <span class="badge bg-warning text-dark">Mandatory</span>
                                    @else
                                        <span class="text-muted small">Optional</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $v->enabled ? 'success' : 'secondary' }}">
                                        {{ $v->enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>{{-- /tab-pane validators --}}

        {{-- ═══ TAB 6: Rules ════════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="tab-rules" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                        <i class="bi bi-shield-check me-1"></i>{{ __('app.federation_rules_title') }}
                    </span>
                    @can('federation.edit')
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('federations.revalidate', $federation) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-repeat me-1"></i>Re-validate entities
                            </button>
                        </form>
                        <a href="{{ route('federations.rules.index', $federation) }}"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>{{ __('app.action_configure') }}
                        </a>
                    </div>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @if($federation->ruleConfigs->isEmpty())
                        <div class="p-3 text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            {{ __('app.federation_rules_using_defaults') }}
                        </div>
                    @else
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width:6rem">{{ __('app.label_rule_id') }}</th>
                                    <th>{{ __('app.label_rule_name') }}</th>
                                    <th style="width:7rem">{{ __('app.label_enabled') }}</th>
                                    <th style="width:8rem">{{ __('app.label_severity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($federation->ruleConfigs->sortBy('rule_id') as $config)
                                <tr>
                                    <td class="ps-3">
                                        <span class="badge bg-secondary">{{ $config->rule_id }}</span>
                                    </td>
                                    <td>{{ $config->rule->name ?? $config->rule_id }}</td>
                                    <td>
                                        @if($config->enabled)
                                            <span class="badge bg-success">On</span>
                                        @else
                                            <span class="badge bg-secondary">Off</span>
                                        @endif
                                    </td>
                                    <td>{{ $config->severity ?? __('app.label_default') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>{{-- /tab-pane rules --}}

        {{-- ═══ TAB 7: Contacts ════════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="tab-contacts" role="tabpanel">
            @livewire('federation-contacts', ['federation' => $federation])
        </div>{{-- /tab-pane contacts --}}

        {{-- ═══ TAB 8: Managers ════════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="tab-managers" role="tabpanel">
            @include('federations.partials.managers-tab')
        </div>{{-- /tab-pane managers --}}

        {{-- ═══ TAB 9: Signing Keys ════════════════════════════════════════════ --}}
        @if(Auth::user()->hasRole('Admin') || $federation->managers->contains(Auth::id()))
        <div class="tab-pane fade" id="tab-signing-keys" role="tabpanel">
            @include('federations.partials.signing-keys-tab')
        </div>{{-- /tab-pane signing-keys --}}
        @endif

    </div>{{-- /tab-content --}}

    {{-- Activate tab from URL hash synchronously — before first paint --}}
    <script>
    (function () {
        var hash = window.location.hash;
        if (!hash) return;
        var trigger = document.querySelector('.nav-tabs a[href="' + hash + '"]');
        if (!trigger) return;
        document.querySelectorAll('.nav-tabs .nav-link').forEach(function (el) {
            el.classList.remove('active');
            el.setAttribute('aria-selected', 'false');
        });
        document.querySelectorAll('.tab-pane').forEach(function (el) {
            el.classList.remove('show', 'active');
        });
        trigger.classList.add('active');
        trigger.setAttribute('aria-selected', 'true');
        var pane = document.querySelector(hash);
        if (pane) pane.classList.add('show', 'active');
    })();
    </script>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('entityPieChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Identity Providers', 'Service Providers'],
            datasets: [{
                data: [{{ $idpCount }}, {{ $spCount }}],
                backgroundColor: ['#0d6efd', '#198754'],
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
</script>
@endpush

<livewire:federation-deactivate-modal />
<livewire:federation-entity-suspend-modal />

@endsection
