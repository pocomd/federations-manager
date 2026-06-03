{{--
    resources/views/entities/show.blade.php
    Entity detail page — read-only view with metadata XML preview.
--}}
@extends('layouts.app')

@section('title', ($entity->getDisplayName() ?? $entity->entity_id) . ' — Federation Registry')

@section('content')

<div class="container-fluid py-4 px-4">

    {{-- Breadcrumb + actions --}}
    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('entities.index') }}">Entities</a></li>
                    <li class="breadcrumb-item active" aria-current="page">
                        {{ $entity->getDisplayName() ?? $entity->entity_id }}
                    </li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-1">
                {{ $entity->getDisplayName() ?? $entity->entity_id }}
                @php
                    $headerBadge = match($entity->status) {
                        'active'    => 'success',
                        'suspended' => 'danger',
                        'pending'   => 'warning',
                        default     => 'secondary',
                    };
                @endphp
                <span class="badge bg-{{ $headerBadge }} align-middle ms-2" style="font-size:.7rem;">{{ ucfirst($entity->status) }}</span>
            </h1>
            <p class="text-muted mb-0 small font-monospace">{{ $entity->entity_id }}</p>
        </div>
        <div class="d-flex gap-2">
            @can('entity.edit')
            <a href="{{ route('entities.edit', $entity) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            @endcan
            <a href="{{ route('entities.validate', $entity) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-check2-circle me-1"></i> Validate
            </a>
            @can('metadata.view')
            <a href="#metadata-xml-card" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-filetype-xml me-1"></i> Metadata XML
            </a>
            @endcan
            @if ($entity->type === 'sp')
            <a href="{{ route('entities.requested-attributes', $entity) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-check me-1"></i> Requested Attributes
            </a>
            @endif
            @if ($entity->type === 'idp')
            <a href="{{ route('entities.arp', $entity) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-shield-check me-1"></i> Attribute Release Policy
            </a>
            @endif
            <a href="{{ route('entities.rules.index', $entity) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-shield-check me-1"></i> {{ __('app.action_rules') }}
            </a>
            @can('entity.edit')
            <div x-data="{ status: '{{ $entity->status }}' }"
                 @entity-suspended.window="status = 'suspended'"
                 @entity-reactivated.window="status = 'active'"
                 class="d-flex gap-2">
                <template x-if="status === 'active'">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-warning btn-sm"
                                onclick="Livewire.dispatch('open-suspend-modal', { entityId: '{{ $entity->id }}' })">
                            <i class="bi bi-pause-circle me-1"></i> {{ __('app.action_suspend') }}
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm"
                                disabled
                                title="{{ __('app.entity_delete_blocked_active') }}">
                            <i class="bi bi-trash me-1"></i> {{ __('app.action_delete') }}
                        </button>
                    </div>
                </template>
                <template x-if="status === 'suspended'">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm"
                                onclick="Livewire.dispatch('open-reactivate-modal', { entityId: '{{ $entity->id }}' })">
                            <i class="bi bi-play-circle me-1"></i> {{ __('app.action_reactivate') }}
                        </button>
                        <form method="POST"
                              action="{{ route('entities.destroy', $entity) }}"
                              class="d-inline"
                              onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.entity.delete_title, text: Lang.entity.delete_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash me-1"></i> {{ __('app.action_delete') }}
                            </button>
                        </form>
                    </div>
                </template>
                @if(in_array($entity->status, ['draft', 'pending']))
                <form method="POST"
                      action="{{ route('entities.destroy', $entity) }}"
                      class="d-inline"
                      onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.entity.delete_title, text: Lang.entity.delete_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> {{ __('app.action_delete') }}
                    </button>
                </form>
                @endif
            </div>
            @endcan
        </div>
    </div>

    <div class="row g-3">

        {{-- Core details --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-info-circle me-1"></i> Core Details
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5 text-muted">Type</dt>
                        <dd class="col-sm-7">
                            @if($entity->type === 'idp')
                                <span class="badge bg-info text-dark">
                                    <i class="bi bi-shield-lock me-1"></i> IdP
                                </span>
                            @else
                                <span class="badge bg-success">
                                    <i class="bi bi-window-desktop me-1"></i> SP
                                </span>
                            @endif
                        </dd>

                        <dt class="col-sm-5 text-muted">Status</dt>
                        <dd class="col-sm-7">
                            @php
                                $statusColor = match($entity->status) {
                                    'active'    => 'success',
                                    'draft'     => 'secondary',
                                    'pending'   => 'warning',
                                    'suspended' => 'danger',
                                    default     => 'secondary',
                                };
                            @endphp
                            @php
                                $dotCls = match($entity->status) {
                                    'active'    => 'status-dot-success',
                                    'suspended' => 'status-dot-danger',
                                    'pending'   => 'status-dot-warning',
                                    default     => 'status-dot-muted',
                                };
                            @endphp
                            <span class="d-inline-flex align-items-center gap-1">
                                <span class="status-dot {{ $dotCls }}"></span>
                                <span>{{ ucfirst($entity->status) }}</span>
                            </span>
                        </dd>

                        <dt class="col-sm-5 text-muted">eduGAIN</dt>
                        <dd class="col-sm-7">
                            @if($entity->edugain)
                                <span class="badge bg-primary">Exported</span>
                                @if($entity->certificates->isEmpty())
                                    <span class="badge bg-danger ms-1" title="This entity has no certificates and is excluded from the eduGAIN metadata feed.">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>No certificates — excluded from feed
                                    </span>
                                @endif
                                @if(!$entity->hasSecurityContact())
                                    <span class="badge bg-danger ms-1" title="This entity has no security contact and is excluded from the eduGAIN metadata feed.">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>No security contact — excluded from feed
                                    </span>
                                @endif
                            @else
                                <span class="text-muted">No</span>
                            @endif
                        </dd>

                        @php
                            $orgDisplayName = $entity->uiInfo
                                ->where('field', 'org_display_name')
                                ->where('lang', 'en')
                                ->first()?->value;
                            $orgUrl = $entity->uiInfo
                                ->where('field', 'org_url')
                                ->where('lang', 'en')
                                ->first()?->value;
                        @endphp

                        @if($orgDisplayName)
                        <dt class="col-sm-5 text-muted">Organisation</dt>
                        <dd class="col-sm-7">
                            @if($orgUrl)
                                <a href="{{ $orgUrl }}" target="_blank" rel="noopener">{{ $orgDisplayName }}</a>
                            @else
                                {{ $orgDisplayName }}
                            @endif
                        </dd>
                        @endif

                        @if($entity->scope)
                        <dt class="col-sm-5 text-muted">Scope</dt>
                        <dd class="col-sm-7 font-monospace">{{ $entity->scope }}</dd>
                        @endif

                        <dt class="col-sm-5 text-muted">Registered</dt>
                        <dd class="col-sm-7">{{ $entity->created_at->format('d M Y') }}</dd>

                        <dt class="col-sm-5 text-muted">Last Updated</dt>
                        <dd class="col-sm-7">{{ $entity->updated_at->format('d M Y') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- UI Info / Display names --}}
        @php
            $uiFields = ['display_name', 'description', 'information_url', 'privacy_url'];
            $hasMultiLang = $entity->uiInfo->where('lang', '!=', 'en')->isNotEmpty();
        @endphp
        @if($hasMultiLang)
        <div class="col-8">
            <div class="card">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-translate me-1"></i> Display Names &amp; Descriptions
                    <span class="badge bg-secondary ms-1">multi-language</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                    @foreach($uiFields as $field)
                        @php
                            $variants = $entity->uiInfo->where('field', $field)->sortBy('lang');
                        @endphp
                        @if($variants->isNotEmpty())
                        <dt class="col-sm-3 text-muted">{{ ucwords(str_replace('_', ' ', $field)) }}</dt>
                        <dd class="col-sm-9">
                            @foreach($variants as $variant)
                                <div class="mb-1">
                                    <span class="badge bg-secondary me-1">{{ strtoupper($variant->lang) }}</span>
                                    {{ $variant->value }}
                                </div>
                            @endforeach
                        </dd>
                        @endif
                    @endforeach
                    </dl>
                </div>
            </div>
        </div>
        @endif

        {{-- Certificates --}}
        <div class="col-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-shield-lock me-1"></i> Certificates
                    <span class="badge bg-secondary ms-1">{{ $entity->certificates->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @forelse($entity->certificates as $cert)
                    <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="badge bg-secondary">{{ $cert->use }}</span>
                            @php
                                $daysLeft = $cert->not_after ? now()->diffInDays($cert->not_after, false) : null;
                                $certColor = match(true) {
                                    $daysLeft === null  => 'secondary',
                                    $daysLeft < 0       => 'danger',
                                    $daysLeft <= 14     => 'danger',
                                    $daysLeft <= 30     => 'warning',
                                    default             => 'success',
                                };
                            @endphp
                            <span class="badge bg-{{ $certColor }}">
                                @if($daysLeft === null)
                                    Unknown expiry
                                @elseif($daysLeft < 0)
                                    Expired {{ abs((int) $daysLeft) }}d ago
                                @else
                                    Expires in {{ (int) $daysLeft }}d
                                @endif
                            </span>
                        </div>
                        <div class="small text-muted">
                            <div>{{ $cert->subject }}</div>
                            @if($cert->not_after)
                                <div>Not after: {{ $cert->not_after->format('d M Y') }}</div>
                            @endif
                            @if($cert->key_bits)
                                <div>{{ $cert->key_algorithm }} {{ $cert->key_bits }}-bit</div>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div class="p-3 text-muted small">No certificates registered.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Federations --}}
        <div class="col-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-collection me-1"></i> Federations
                    <span class="badge bg-secondary ms-1">{{ $entity->federations->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @forelse($entity->federations as $fed)
                    <div class="p-3 d-flex justify-content-between align-items-center {{ !$loop->last ? 'border-bottom' : '' }}">
                        <a href="{{ route('federations.show', $fed) }}" class="text-decoration-none">
                            {{ $fed->name }}
                        </a>
                        @php
                            $pivotStatus = $fed->pivot->status ?? 'pending';
                            $pivotColor  = match($pivotStatus) {
                                'active'   => 'success',
                                'pending'  => 'warning',
                                'rejected' => 'danger',
                                default    => 'secondary',
                            };
                        @endphp
                        <span class="badge bg-{{ $pivotColor }}">{{ $pivotStatus }}</span>
                    </div>
                    @empty
                    <div class="p-3 text-muted small">Not a member of any federation.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Pending federation invitations --}}
        @if($pendingInvitations->isNotEmpty())
        <div class="col-12">
            <div class="card border-primary mb-2">
                <div class="card-header bg-primary-subtle border-bottom py-2 px-3 fw-semibold small text-uppercase" style="letter-spacing:.04em;">
                    <i class="bi bi-envelope-open me-1"></i>
                    Federation Invitations
                    <span class="badge bg-primary ms-1">{{ $pendingInvitations->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @foreach($pendingInvitations as $inv)
                    <div class="p-3 d-flex justify-content-between align-items-start {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="fw-semibold">
                                <a href="{{ route('federations.show', $inv->federation) }}" class="text-decoration-none">
                                    {{ $inv->federation->name }}
                                </a>
                                <span class="text-muted fw-normal small ms-1">wants this entity to join their federation</span>
                            </div>
                            @if($inv->note)
                            <div class="text-muted small mt-1 fst-italic">"{{ $inv->note }}"</div>
                            @endif
                            <div class="text-muted small mt-1">
                                Invited by {{ $inv->invitedBy?->name ?? 'Federation Manager' }}
                                · {{ $inv->created_at->diffForHumans() }}
                            </div>
                        </div>
                        <div class="d-flex gap-2 ms-3 flex-shrink-0">
                            <form method="POST" action="{{ route('federation-entity-invitations.accept', $inv) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-check-lg me-1"></i>Accept
                                </button>
                            </form>
                            <form method="POST" action="{{ route('federation-entity-invitations.reject', $inv) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-x-lg me-1"></i>Decline
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- REFEDS attributes --}}
        @if($entity->attributes->isNotEmpty())
        <div class="col-4">
            <div class="card">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-tags me-1"></i> REFEDS Attributes
                </div>
                <div class="card-body">
                    @foreach($entity->attributes as $attr)
                    <div class="small mb-1">
                        <span class="badge bg-light text-dark border">{{ $attr->attribute_name }}</span>
                        <span class="font-monospace text-muted d-block ms-1" style="font-size:0.7rem;word-break:break-all;">
                            {{ $attr->attribute_value }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Endpoints --}}
        @if($entity->endpoints->isNotEmpty())
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-link-45deg me-1"></i> Endpoints
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Type</th>
                                <th>Binding</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($entity->endpoints->sortBy(['type', 'index']) as $ep)
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-secondary">{{ strtoupper($ep->type) }}</span>
                                </td>
                                <td class="small text-muted">
                                    {{ str_replace('urn:oasis:names:tc:SAML:2.0:bindings:', '', $ep->binding) }}
                                </td>
                                <td class="small">
                                    <a href="{{ $ep->location }}" target="_blank" rel="noopener"
                                       class="text-break">{{ $ep->location }}</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Contacts --}}
        @if($entity->contacts->isNotEmpty())
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-person-lines-fill me-1"></i> Contacts
                </div>
                <div class="card-body p-0">
                    @foreach($entity->contacts as $contact)
                    <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="small text-muted text-uppercase">{{ $contact->type }}</div>
                        <a href="mailto:{{ $contact->email }}" class="small">{{ $contact->email }}</a>
                        @if(isset($contactUserEmails) && $contactUserEmails->contains($contact->email))
                            <span class="badge bg-primary ms-1" title="This contact has a Jagger account"><i class="bi bi-person-check"></i></span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Request Co-Manager --}}
        @can('entity.requestContactInvitation')
        @if($entity->contacts->isNotEmpty())
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-person-plus me-1"></i> Request Co-Manager
                </div>
                <div class="card-body p-0">
                    @foreach($entity->contacts as $contact)
                    <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="small text-muted text-uppercase mb-1">{{ $contact->type }}</div>
                        <div class="small mb-2">{{ $contact->email }}</div>
                        <button class="btn btn-outline-primary btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#requestInvModal{{ $loop->index }}">
                            <i class="bi bi-send me-1"></i>Request Invitation
                        </button>

                        {{-- Modal for this contact --}}
                        <div class="modal fade" id="requestInvModal{{ $loop->index }}"
                             tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-sm">
                                <div class="modal-content">
                                    <form method="POST"
                                          action="{{ route('invitation-requests.store', $entity) }}">
                                        @csrf
                                        <input type="hidden" name="contact_email" value="{{ $contact->email }}">
                                        <input type="hidden" name="contact_type"  value="{{ $contact->type }}">
                                        <input type="hidden" name="contact_name"  value="{{ $contact->name ?? '' }}">
                                        <div class="modal-header">
                                            <h6 class="modal-title small fw-semibold">Request Invitation</h6>
                                            <button type="button" class="btn-close btn-sm"
                                                    data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="small mb-2">
                                                Send a co-manager invitation to
                                                <strong>{{ $contact->email }}</strong>.
                                            </p>
                                            @if($entity->federations->count() === 1)
                                                <input type="hidden" name="federation_id" value="{{ $entity->federations->first()->id }}">
                                                <p class="small text-muted mb-2">
                                                    Federation: <strong>{{ $entity->federations->first()->name }}</strong>
                                                </p>
                                            @else
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Federation</label>
                                                <select name="federation_id"
                                                        class="form-select form-select-sm" required>
                                                    <option value="">— Select —</option>
                                                    @foreach($entity->federations as $fed)
                                                    <option value="{{ $fed->id }}">{{ $fed->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            @endif
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary btn-sm">Submit Request</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
        @endcan

        {{-- Metadata XML --}}
        @can('metadata.view')
        <div class="col-12" id="metadata-xml-card">
            <div class="card">
                <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex justify-content-between align-items-center fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <span><i class="bi bi-filetype-xml me-1"></i> Generated Metadata XML</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                onclick="copyToClipboard(document.getElementById('metadata-xml-pre').textContent)"
                                title="Copy to clipboard">
                            <i class="bi bi-clipboard me-1"></i> Copy
                        </button>
                        <a href="{{ route('entities.metadata.download', $entity) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-download me-1"></i> Download
                        </a>
                        <a href="{{ route('entities.metadata', $entity) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open raw
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <pre id="metadata-xml-pre" class="mb-0 p-3 small" style="max-height:400px;overflow:auto;font-size:0.72rem;">{{ $metadataXml }}</pre>
                </div>
            </div>
        </div>
        @endcan

        {{-- Audit log --}}
        @if($entity->auditLogs->isNotEmpty())
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-clock-history me-1"></i> Recent Audit Log
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">When</th>
                                <th>Action</th>
                                <th>User</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($entity->auditLogs as $log)
                            <tr>
                                <td class="ps-3 small text-muted">{{ $log->created_at->diffForHumans() }}</td>
                                <td class="small"><span class="badge bg-secondary">{{ __('app.action_' . $log->action) }}</span></td>
                                <td class="small">{{ $log->user_id ?? 'system' }}</td>
                                <td class="small text-muted">{{ $log->ip_address }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

    </div>

    {{-- eduGAIN Status panel --}}
    @if(\App\Models\SystemPreference::get('edugain_checks_enabled', false))
    <div class="card mb-3 mt-4">
        <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex justify-content-between align-items-center small text-uppercase text-secondary fw-semibold" style="letter-spacing:.04em;">
            <span>
                <i class="bi bi-globe me-1"></i>
                eduGAIN Status
            </span>
            <a href="https://technical.edugain.org/entities?e={{ urlencode($entity->entity_id) }}"
               target="_blank"
               rel="noopener"
               class="btn btn-sm btn-outline-secondary">
                View on eduGAIN
                <i class="bi bi-box-arrow-up-right ms-1"></i>
            </a>
        </div>
        <div class="card-body"
             x-data="{ data: null, loading: true }"
             x-init="
                fetch('/edugain/entity/{{ $entity->id }}')
                    .then(r => r.json())
                    .then(d => { data = d; loading = false; })
             ">

            <div x-show="loading" class="text-center py-2">
                <span class="spinner-border spinner-border-sm me-1"></span>
                Checking eduGAIN status...
            </div>

            <div x-show="!loading && data?.enabled === false" class="text-muted small">
                eduGAIN integration disabled.
                <a href="{{ route('preferences.index') }}">Enable in Preferences</a>
            </div>

            <div x-show="!loading && data?.enabled">

                {{-- Entity presence --}}
                <div class="mb-2" x-show="data?.presence !== undefined">
                    <strong class="me-2">In eduGAIN:</strong>
                    <span x-show="data?.presence?.present"
                          class="badge bg-success">Yes</span>
                    <span x-show="!data?.presence?.present"
                          class="badge bg-warning text-dark">Not found</span>
                    <span class="text-muted small ms-2"
                          x-text="data?.presence?.message ?? ''"></span>
                </div>

                {{-- ECCS (IdP only) --}}
                @if($entity->type === 'idp')
                <div class="mb-2" x-show="data?.eccs !== undefined">
                    <strong class="me-2">ECCS connectivity:</strong>
                    <span :class="{
                        'badge bg-success':    data?.eccs?.status === 'OK',
                        'badge bg-danger':     data?.eccs?.status === 'ERROR',
                        'badge bg-secondary': !['OK','ERROR'].includes(data?.eccs?.status)
                    }" x-text="data?.eccs?.status ?? 'Unknown'"></span>
                </div>
                @endif

                {{-- External tool links --}}
                <div class="mt-3 pt-2 border-top">
                    <strong class="d-block mb-2">External tools:</strong>
                    @if($entity->type === 'idp')
                    <a href="https://release-check.edugain.org/?idp={{ urlencode($entity->entity_id) }}"
                       target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Release Check
                    </a>
                    <a href="https://technical.edugain.org/eccs?entityID={{ urlencode($entity->entity_id) }}"
                       target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-box-arrow-up-right me-1"></i> ECCS Details
                    </a>
                    @endif
                    @if($entity->type === 'sp')
                    <a href="https://access-check.edugain.org/?sp={{ urlencode($entity->entity_id) }}"
                       target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Access Check
                    </a>
                    @endif
                    <a href="https://technical.edugain.org/entities?e={{ urlencode($entity->entity_id) }}"
                       target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right me-1"></i> eduGAIN DB
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
</div>

<livewire:entity-suspend-modal />
<livewire:entity-reactivate-modal />

@endsection
