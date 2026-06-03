{{--
    resources/views/livewire/entity-search.blade.php

    Livewire + Alpine + Bootstrap 5 entity search, filter, and inline validation UI.

    Livewire handles:  reactive filtering, server-side pagination, inline validation
    Alpine handles:    collapse/expand animations, client-side UI state, copy-to-clipboard
    Bootstrap handles: layout, typography, utility classes
--}}

<div>

<style>
  [x-cloak] { display: none !important; }
</style>

{{-- ═══════════════════════════════════════════════════════════════
     SUMMARY STATS ROW
═══════════════════════════════════════════════════════════════ --}}
<div class="d-flex flex-wrap align-items-center gap-3 mb-3 small text-secondary">
    <span>
        <span class="fw-semibold text-dark">{{ $this->summaryCounts['total'] }}</span>
        <span class="ms-1">Total</span>
    </span>
    <span class="text-muted">·</span>
    <button wire:click="toggleType('idp')"
            class="btn btn-link btn-sm p-0 text-decoration-none {{ $type === 'idp' ? 'text-orange fw-semibold' : 'text-secondary' }}">
        <span class="fw-semibold text-dark">{{ $this->summaryCounts['idp'] }}</span>
        <span class="ms-1">IdPs</span>
    </button>
    <span class="text-muted">·</span>
    <button wire:click="toggleType('sp')"
            class="btn btn-link btn-sm p-0 text-decoration-none {{ $type === 'sp' ? 'text-orange fw-semibold' : 'text-secondary' }}">
        <span class="fw-semibold text-dark">{{ $this->summaryCounts['sp'] }}</span>
        <span class="ms-1">SPs</span>
    </button>
    <span class="text-muted">·</span>
    <span>
        <span class="d-inline-flex align-items-center gap-1">
            <span class="status-dot status-dot-success"></span>
            <span class="fw-semibold text-dark">{{ $this->summaryCounts['active'] }}</span>
        </span>
        <span class="ms-1">Active</span>
    </span>
    @if($this->summaryCounts['cert_critical'] > 0)
    <span class="text-muted">·</span>
    <button wire:click="toggleCertExpiry"
            class="btn btn-link btn-sm p-0 text-decoration-none text-danger">
        <i class="bi bi-exclamation-triangle-fill me-1" style="font-size:.75rem;"></i>
        <span class="fw-semibold">{{ $this->summaryCounts['cert_critical'] }}</span>
        <span class="ms-1">cert critical</span>
    </button>
    @endif
    @if($this->summaryCounts['edugain'] > 0)
    <span class="text-muted">·</span>
    <button wire:click="toggleEdugain"
            class="btn btn-link btn-sm p-0 text-decoration-none text-secondary">
        <span class="fw-semibold text-dark">{{ $this->summaryCounts['edugain'] }}</span>
        <span class="ms-1">eduGAIN</span>
    </button>
    @endif
</div>

{{-- ═══════════════════════════════════════════════════════════════
     FILTER PANEL
═══════════════════════════════════════════════════════════════ --}}
<div class="card card-body mb-3 p-2">
    <div class="row g-2 align-items-center">

        {{-- Free-text search — col grows to fill remaining space --}}
        <div class="col">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       class="form-control form-control-sm border-start-0 ps-0"
                       placeholder="{{ __('entityID, display name, organisation…') }}">
                @if($search)
                    <button wire:click="$set('search', '')" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x"></i>
                    </button>
                @endif
            </div>
        </div>

        {{-- Type --}}
        <div class="col-auto">
            <select wire:model.live="type" class="form-select form-select-sm">
                <option value="">{{ __('All types') }}</option>
                <option value="idp">IdP</option>
                <option value="sp">SP</option>
            </select>
        </div>

        {{-- Status --}}
        <div class="col-auto">
            <select wire:model.live="status" class="form-select form-select-sm">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="draft">Draft</option>
                <option value="pending">{{ __('Pending') }}</option>
                <option value="suspended">Suspended</option>
            </select>
        </div>

        {{-- Federation --}}
        <div class="col-auto">
            <select wire:model.live="federation" class="form-select form-select-sm">
                <option value="">{{ __('All federations') }}</option>
                @foreach($federations as $fed)
                    <option value="{{ $fed->id }}">{{ $fed->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Cert expiry --}}
        <div class="col-auto">
            <select wire:model.live="certExpiry" class="form-select form-select-sm">
                <option value="0">{{ __('Any expiry') }}</option>
                <option value="14">≤ 14 days</option>
                <option value="30">≤ 30 days</option>
                <option value="60">≤ 60 days</option>
                <option value="90">≤ 90 days</option>
            </select>
        </div>

        {{-- REFEDS / attributes multiselect --}}
        {{-- @click.outside is on the x-data wrapper so the toggle button (inside the
             wrapper) does NOT trigger it — avoids the open-then-immediately-close bug.
             We avoid Bootstrap's .dropdown-menu class because its CSS display:none
             conflicts with Alpine x-show (which removes the inline style when showing,
             falling back to CSS display:none). Plain styling is used instead. --}}
        @php $refedsCount = collect([$sirtfi, $rs, $coco, $edugain, $validOnly])->filter()->count(); @endphp
        <div class="col-auto">
            <div x-data="{ open: false }"
                 @click.outside="open = false"
                 style="position:relative;">
                <button class="btn btn-sm btn-outline-secondary"
                        type="button"
                        @click="open = !open">
                    <i class="bi bi-tags me-1"></i>Attributes
                    @if($refedsCount > 0)
                        <span class="badge bg-primary ms-1">{{ $refedsCount }}</span>
                    @endif
                </button>
                <div x-show="open" x-cloak
                     class="bg-white border rounded shadow-sm p-2"
                     style="position:absolute;top:calc(100% + 4px);left:0;min-width:200px;z-index:1050;">
                    <div class="form-check mb-1">
                        <input wire:model.live="edugain" class="form-check-input" type="checkbox" id="f-edugain">
                        <label class="form-check-label small" for="f-edugain">
                            <span class="badge" style="background:#6f42c1">eduGAIN</span>
                        </label>
                    </div>
                    <div class="form-check mb-1">
                        <input wire:model.live="sirtfi" class="form-check-input" type="checkbox" id="f-sirtfi">
                        <label class="form-check-label small" for="f-sirtfi">
                            <span class="badge bg-dark">SIRTFI</span>
                        </label>
                    </div>
                    <div class="form-check mb-1">
                        <input wire:model.live="rs" class="form-check-input" type="checkbox" id="f-rs">
                        <label class="form-check-label small" for="f-rs">
                            <span class="badge bg-info text-dark">R&amp;S</span>
                        </label>
                    </div>
                    <div class="form-check mb-1">
                        <input wire:model.live="coco" class="form-check-input" type="checkbox" id="f-coco">
                        <label class="form-check-label small" for="f-coco">
                            <span class="badge bg-secondary">CoCo v2</span>
                        </label>
                    </div>
                    <div class="form-check">
                        <input wire:model.live="validOnly" class="form-check-input" type="checkbox" id="f-valid">
                        <label class="form-check-label small" for="f-valid">
                            <span class="badge bg-success">Valid only</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Clear filters — always rendered, disabled when nothing is active --}}
        <div class="col-auto">
            <button wire:click="resetFilters"
                    class="btn btn-sm btn-outline-secondary"
                    title="{{ __('Clear all filters') }}"
                    {{ $this->hasActiveFilters() ? '' : 'disabled' }}>
                <i class="bi bi-x-lg me-1"></i>Clear
            </button>
        </div>

    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     RESULTS TOOLBAR
═══════════════════════════════════════════════════════════════ --}}
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="text-muted small">
    <span wire:loading.delay.shortest class="align-items-center gap-1">
        <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
        Loading…
    </span>
    <span wire:loading.remove.delay.shortest>
        Showing {{ $this->entities->firstItem() }}–{{ $this->entities->lastItem() }}
        of {{ $this->entities->total() }} entities
    </span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <label class="small text-muted mb-0">{{ __('Per page:') }}</label>
        <select wire:model.live="perPage" class="form-select form-select-sm" style="width:auto">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     ENTITY TABLE
═══════════════════════════════════════════════════════════════ --}}
<div class="card border shadow-none">
    <div class="table-responsive">
        <table class="table table-sm table-hover table-bordered mb-0 align-middle" style="font-size:.8125rem;">
            <thead class="table-light border-bottom">
                <tr>
                    <th scope="col" style="width:2.5rem"></th>

                    <th scope="col" class="py-2" style="min-width:220px">
                        <button wire:click="sort('name_en')"
                                class="btn btn-link btn-sm text-dark text-decoration-none fw-semibold p-0">
                            {{ __('Entity') }}
                            @if($sortBy === 'name_en')
                                <i class="bi bi-caret-{{ $sortDir === 'asc' ? 'up' : 'down' }}-fill ms-1"></i>
                            @else
                                <i class="bi bi-caret-up opacity-25 ms-1"></i>
                            @endif
                        </button>
                    </th>

                    <th scope="col" class="py-2" style="width:80px">
                        <button wire:click="sort('type')"
                                class="btn btn-link btn-sm text-dark text-decoration-none fw-semibold p-0">
                            {{ __('Type') }}
                            @if($sortBy === 'type')
                                <i class="bi bi-caret-{{ $sortDir === 'asc' ? 'up' : 'down' }}-fill ms-1"></i>
                            @else
                                <i class="bi bi-caret-up opacity-25 ms-1"></i>
                            @endif
                        </button>
                    </th>

                    <th scope="col" class="py-2" style="width:110px">
                        <button wire:click="sort('status')"
                                class="btn btn-link btn-sm text-dark text-decoration-none fw-semibold p-0">
                            {{ __('Status') }}
                            @if($sortBy === 'status')
                                <i class="bi bi-caret-{{ $sortDir === 'asc' ? 'up' : 'down' }}-fill ms-1"></i>
                            @else
                                <i class="bi bi-caret-up opacity-25 ms-1"></i>
                            @endif
                        </button>
                    </th>

                    <th scope="col" class="py-2 d-none d-lg-table-cell">{{ __('Attributes') }}</th>

                    <th scope="col" class="py-2" style="width:110px">{{ __('Certs') }}</th>

                    <th scope="col" class="py-2" style="width:110px">{{ __('Metadata') }}</th>

                    <th scope="col" class="py-2 text-center" style="width:120px">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>

                @forelse($this->entities as $entity)

                    {{-- MAIN ROW --}}
                    <tr wire:key="entity-{{ $entity->id }}"
                        class="{{ $expandedEntityId === $entity->id ? 'table-active' : '' }}"
                        style="cursor:pointer"
                        wire:click="toggleExpand('{{ $entity->id }}')">
                        <td class="text-center text-muted py-2">
                            <i class="bi bi-chevron-{{ $expandedEntityId === $entity->id ? 'down' : 'right' }}"
                               style="transition:transform .2s"></i>
                        </td>

                        {{-- Entity name + entityID --}}
                        <td class="py-2">
                            <div class="fw-semibold text-dark lh-sm">{{ $entity->getDisplayName() ?: '(no display name)' }}</div>
                            <div class="text-muted" style="font-size:.75rem; font-family:monospace; word-break:break-all">
                                {{ $entity->entity_id }}
                            </div>
                            @php $orgName = $entity->uiInfo->where('field', 'org_name')->where('lang', 'en')->first()?->value; @endphp
                            @if($orgName)
                                <div class="text-muted" style="font-size:.75rem">{{ $orgName }}</div>
                            @endif
                        </td>

                        {{-- Type badge --}}
                        <td class="py-2">
                            @if($entity->type === 'idp')
                                <span class="badge bg-info text-dark">{{ __('IdP') }}</span>
                            @else
                                <span class="badge bg-success">{{ __('SP') }}</span>
                            @endif
                        </td>

                        {{-- Status dot + text --}}
                        <td class="py-2">
                            @php
                                [$dotCls, $label] = match($entity->status) {
                                    'active'    => ['status-dot-success', 'Active'],
                                    'suspended' => ['status-dot-danger',  'Suspended'],
                                    'pending'   => ['status-dot-warning', 'Pending'],
                                    default     => ['status-dot-muted',   ucfirst($entity->status)],
                                };
                            @endphp
                            <span class="d-inline-flex align-items-center gap-1">
                                <span class="status-dot {{ $dotCls }}"></span>
                                <span>{{ $label }}</span>
                            </span>
                        </td>

                        {{-- Attribute badges --}}
                        <td class="py-2 d-none d-lg-table-cell">
                            <div class="d-flex flex-wrap gap-1">
                                @if($entity->edugain)
                                    <span class="badge" style="background:#6f42c1;font-size:.65rem">{{ __('eduGAIN') }}</span>
                                    @if($entity->certificates->isEmpty())
                                        <span class="badge bg-danger" style="font-size:.65rem" title="Excluded from eduGAIN feed — no certificates"><i class="bi bi-exclamation-triangle-fill"></i></span>
                                    @elseif(!$entity->hasSecurityContact())
                                        <span class="badge bg-danger" style="font-size:.65rem" title="Excluded from eduGAIN feed — no security contact"><i class="bi bi-exclamation-triangle-fill"></i></span>
                                    @endif
                                @endif
                                @if($entity->sirtfi)
                                    <span class="badge bg-dark" style="font-size:.65rem">{{ __('SIRTFI') }}</span>
                                @endif
                                @if(in_array('http://refeds.org/category/research-and-scholarship', $entity->entity_categories ?? []))
                                    <span class="badge bg-info text-dark" style="font-size:.65rem">R&amp;S</span>
                                @endif
                                @if(in_array('https://refeds.org/category/code-of-conduct/v2', $entity->entity_categories ?? []))
                                    <span class="badge bg-secondary" style="font-size:.65rem">CoCo</span>
                                @endif
                                @if(in_array('https://refeds.org/profile/mfa', $entity->entity_categories ?? []))
                                    <span class="badge bg-warning text-dark" style="font-size:.65rem">MFA</span>
                                @endif
                            </div>
                        </td>

                        {{-- Certificate health --}}
                        <td class="py-2">
                            @php
                                $critCerts = $entity->certificates->filter(fn($c) => $c->not_after <= now()->addDays(14));
                                $warnCerts = $entity->certificates->filter(fn($c) => $c->not_after > now()->addDays(14) && $c->not_after <= now()->addDays(30));
                                $expiredCerts = $entity->certificates->filter(fn($c) => $c->not_after < now());
                            @endphp
                            @if($expiredCerts->isNotEmpty())
                                <span class="badge bg-danger" title="{{ $expiredCerts->count() }} expired">
                                    <i class="bi bi-x-circle-fill me-1"></i>{{ $expiredCerts->count() }} expired
                                </span>
                            @elseif($critCerts->isNotEmpty())
                                <span class="badge bg-warning text-dark" title="{{ $critCerts->count() }} cert(s) critical">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>≤14d
                                </span>
                            @elseif($warnCerts->isNotEmpty())
                                <span class="badge bg-warning bg-opacity-50 text-dark">≤30d</span>
                            @else
                                <span class="text-success small">
                                    <i class="bi bi-patch-check-fill"></i>
                                    {{ $entity->certificates->count() }}
                                </span>
                            @endif
                        </td>

                        {{-- Metadata validation status + validate button --}}
                        <td class="py-2">
                            <div class="d-flex align-items-center gap-1">
                                <button wire:click.stop="validateEntity('{{ $entity->id }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="validateEntity('{{ $entity->id }}')"
                                        class="btn btn-sm btn-outline-info"
                                        title="{{ __('Validate metadata') }}">
                                    <span wire:loading.remove wire:target="validateEntity('{{ $entity->id }}')">
                                        <i class="bi bi-check2-circle"></i>
                                    </span>
                                    <span wire:loading wire:target="validateEntity('{{ $entity->id }}')">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                    </span>
                                </button>
                                @if(isset($validationResults[$entity->id]))
                                    @if($validationResults[$entity->id]['passed'])
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i>Valid
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="bi bi-x-circle me-1"></i>
                                            {{ count($validationResults[$entity->id]['errors']) }} error(s)
                                        </span>
                                    @endif
                                @elseif($entity->metadata_validated_at)
                                    @if($entity->metadata_valid)
                                        <span class="text-success small" title="{{ __('Validated') }} {{ $entity->metadata_validated_at->diffForHumans() }}">
                                            <i class="bi bi-check-circle-fill"></i>
                                        </span>
                                    @else
                                        <span class="text-danger small" title="{{ __('Issues found') }} {{ $entity->metadata_validated_at->diffForHumans() }}">
                                            <i class="bi bi-x-circle-fill"></i>
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>

                        {{-- Actions --}}
                        <td class="py-2 text-center" @click.stop>
                            <div>
                                @can('entity.edit')
                                    <a href="{{ route('entities.edit', $entity) }}"
                                       class="btn btn-sm btn-outline-primary"
                                       title="{{ __('Edit') }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                                @can('entity.edit')
                                @if($entity->status === 'active')
                                    <button type="button"
                                            class="btn btn-sm btn-outline-warning"
                                            title="{{ __('app.action_suspend') }}"
                                            @click.stop="$dispatch('open-suspend-modal', { entityId: '{{ $entity->id }}' })">
                                        <i class="bi bi-pause-circle"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            disabled
                                            title="{{ __('app.entity_delete_blocked_active') }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @elseif($entity->status === 'suspended')
                                    <button type="button"
                                            class="btn btn-sm btn-success"
                                            title="{{ __('app.action_reactivate') }}"
                                            @click.stop="$dispatch('open-reactivate-modal', { entityId: '{{ $entity->id }}' })">
                                        <i class="bi bi-play-circle"></i>
                                    </button>
                                    <form method="POST"
                                          action="{{ route('entities.destroy', $entity) }}"
                                          style="display:contents"
                                          onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.entity.delete_title, text: Lang.entity.delete_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="btn btn-sm btn-danger"
                                                title="{{ __('app.action_delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @elseif(in_array($entity->status, ['draft', 'pending']))
                                    <form method="POST"
                                          action="{{ route('entities.destroy', $entity) }}"
                                          style="display:contents"
                                          onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.entity.delete_title, text: Lang.entity.delete_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                title="{{ __('app.action_delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>

                    {{-- EXPANDED DETAIL ROW --}}
                    @if($expandedEntityId === $entity->id)
                        <tr wire:key="entity-detail-{{ $entity->id }}" class="table-active">
                            <td colspan="8" class="p-0 border-0">
                                <div class="px-4 pt-3 pb-4" style="background:var(--surface-alt); border-top:2px solid var(--orange);">

                                    <div class="row g-4">

                                        {{-- LEFT: Entity details --}}
                                        <div class="col-12 col-md-6 col-xl-4">
                                            <h6 class="text-muted text-uppercase fw-semibold mb-3"
                                                style="font-size:.7rem; letter-spacing:.08em">
                                                <i class="bi bi-info-circle me-1"></i>{{ __('Entity Details') }}
                                            </h6>

                                            <dl class="row row-cols-1 g-1 mb-0" style="font-size:.8rem">
                                                <div class="d-flex gap-2 py-1 border-bottom">
                                                    <dt class="text-muted" style="min-width:130px">entityID</dt>
                                                    <dd class="mb-0 text-break font-monospace"
                                                        x-data="{
                                                            doCopy(url) {
                                                                const el = document.createElement('textarea');
                                                                el.value = url;
                                                                el.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
                                                                document.body.appendChild(el);
                                                                el.select();
                                                                document.execCommand('copy');
                                                                document.body.removeChild(el);
                                                            }
                                                        }"
                                                        @click.stop="doCopy('{{ addslashes($entity->entity_id) }}')"
                                                        title="{{ __('Click to copy') }}"
                                                        style="cursor:copy">
                                                        {{ $entity->entity_id }}
                                                        <i class="bi bi-clipboard ms-1 text-muted" style="font-size:.7rem"></i>
                                                    </dd>
                                                </div>
                                                @php
                                                    $ui         = $entity->uiInfo;
                                                    $nameEn     = $ui->where('field','display_name')->where('lang','en')->first()?->value;
                                                    $descEn     = $ui->where('field','description')->where('lang','en')->first()?->value;
                                                    $orgName    = $ui->where('field','org_display_name')->where('lang','en')->first()?->value
                                                                ?: $ui->where('field','org_name')->where('lang','en')->first()?->value;
                                                    $orgUrl     = $ui->where('field','org_url')->where('lang','en')->first()?->value;
                                                    $altNames   = $ui->where('field','display_name')->where('lang','!=','en');
                                                @endphp
                                                <div class="d-flex gap-2 py-1 border-bottom">
                                                    <dt class="text-muted" style="min-width:130px">{{ __('Display Name (EN)') }}</dt>
                                                    <dd class="mb-0">{{ $nameEn ?: '—' }}</dd>
                                                </div>
                                                @foreach($altNames as $alt)
                                                    <div class="d-flex gap-2 py-1 border-bottom">
                                                        <dt class="text-muted" style="min-width:130px">{{ __('Display Name') }} ({{ strtoupper($alt->lang) }})</dt>
                                                        <dd class="mb-0">{{ $alt->value }}</dd>
                                                    </div>
                                                @endforeach
                                                <div class="d-flex gap-2 py-1 border-bottom">
                                                    <dt class="text-muted" style="min-width:130px">{{ __('Description') }}</dt>
                                                    <dd class="mb-0 text-muted">{{ $descEn ? Str::limit($descEn, 120) : '—' }}</dd>
                                                </div>
                                                @if($entity->type === 'idp' && $entity->scope)
                                                    <div class="d-flex gap-2 py-1 border-bottom">
                                                        <dt class="text-muted" style="min-width:130px">{{ __('Scope') }}</dt>
                                                        <dd class="mb-0 font-monospace">{{ $entity->scope }}</dd>
                                                    </div>
                                                @endif
                                                <div class="d-flex gap-2 py-1 border-bottom">
                                                    <dt class="text-muted" style="min-width:130px">{{ __('Organisation') }}</dt>
                                                    <dd class="mb-0">
                                                        @if($orgUrl)
                                                            <a href="{{ $orgUrl }}" target="_blank" rel="noopener" class="text-decoration-none">
                                                                {{ $orgName ?: $orgUrl }}
                                                                <i class="bi bi-box-arrow-up-right ms-1" style="font-size:.65rem"></i>
                                                            </a>
                                                        @else
                                                            {{ $orgName ?: '—' }}
                                                        @endif
                                                    </dd>
                                                </div>
                                                <div class="d-flex gap-2 py-1">
                                                    <dt class="text-muted" style="min-width:130px">{{ __('Federations') }}</dt>
                                                    <dd class="mb-0">
                                                        @forelse($entity->federations as $fed)
                                                            <span class="badge bg-light text-dark border me-1">{{ $fed->name }}</span>
                                                        @empty
                                                            <span class="text-muted">{{ __('None') }}</span>
                                                        @endforelse
                                                    </dd>
                                                </div>
                                            </dl>
                                        </div>

                                        {{-- MIDDLE: Certificates --}}
                                        <div class="col-12 col-md-6 col-xl-4">
                                            <h6 class="text-muted text-uppercase fw-semibold mb-3"
                                                style="font-size:.7rem; letter-spacing:.08em">
                                                <i class="bi bi-key me-1"></i>{{ __('Certificates') }}
                                            </h6>
                                            @forelse($entity->certificates as $cert)
                                                @php
                                                    $daysLeft = now()->diffInDays($cert->not_after, false);
                                                    $certColor = match(true) {
                                                        $daysLeft < 0  => 'danger',
                                                        $daysLeft <= 14 => 'danger',
                                                        $daysLeft <= 30 => 'warning',
                                                        $daysLeft <= 60 => 'info',
                                                        default        => 'success',
                                                    };
                                                @endphp
                                                <div class="border rounded p-2 mb-2 bg-white" style="font-size:.78rem">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <span class="badge bg-{{ $certColor === 'warning' ? 'warning text-dark' : $certColor }}-subtle text-{{ $certColor }} border border-{{ $certColor }}-subtle me-1">
                                                                {{ ucfirst($cert->use) }}
                                                            </span>
                                                            @if($cert->debian_weak)
                                                                <span class="badge bg-danger">{{ __('DEBIAN WEAK KEY') }}</span>
                                                            @endif
                                                        </div>
                                                        <span class="text-{{ $certColor }} fw-semibold">
                                                            {{ $daysLeft >= 0 ? $daysLeft.__(__('d left')) : __('EXPIRED') }}
                                                        </span>
                                                    </div>
                                                    <div class="text-muted mt-1 text-truncate" title="{{ $cert->subject }}">
                                                        {{ $cert->subject }}
                                                    </div>
                                                    <div class="text-muted">
                                                        Expires: {{ $cert->not_after->format('Y-m-d') }}
                                                        &bull; {{ $cert->key_bits }}b
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="text-danger small">
                                                    <i class="bi bi-exclamation-circle me-1"></i>{{ __('No certificates registered') }}
                                                </div>
                                            @endforelse
                                        </div>

                                        {{-- RIGHT: Validation results --}}
                                        <div class="col-12 col-xl-4">
                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <h6 class="text-muted text-uppercase fw-semibold mb-0"
                                                    style="font-size:.7rem; letter-spacing:.08em">
                                                    <i class="bi bi-clipboard2-check me-1"></i>{{ __('Metadata Validation') }}
                                                </h6>
                                                <button wire:click.stop="validateEntity('{{ $entity->id }}')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="validateEntity('{{ $entity->id }}')"
                                                        class="btn btn-sm btn-outline-primary py-0 px-2"
                                                        style="font-size:.75rem">
                                                    <span wire:loading.remove wire:target="validateEntity('{{ $entity->id }}')">
                                                        <i class="bi bi-arrow-clockwise me-1"></i>{{ __('Run') }}
                                                    </span>
                                                    <span wire:loading wire:target="validateEntity('{{ $entity->id }}')">
                                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                                        {{ __('Validating…') }}
                                                    </span>
                                                </button>
                                            </div>

                                            @if(isset($validationResults[$entity->id]))
                                                @php
                                                    $vr         = $validationResults[$entity->id];
                                                    $vrSummary  = $vr['summary'] ?? [];
                                                    $vrErrors   = $vr['errors'] ?? [];
                                                    $vrWarnings = $vr['warnings'] ?? [];
                                                @endphp

                                                {{-- Summary bar --}}
                                                <div class="d-flex gap-2 mb-3">
                                                    <span class="badge {{ $vr['passed'] ? 'bg-success' : 'bg-danger' }} px-2">
                                                        {{ $vr['passed'] ? __('PASSED') : __('FAILED') }}
                                                    </span>
                                                    @if(count($vrErrors) > 0)
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                            {{ count($vrErrors) }} error(s)
                                                        </span>
                                                    @endif
                                                    @if(count($vrWarnings) > 0)
                                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                            {{ count($vrWarnings) }} warning(s)
                                                        </span>
                                                    @endif
                                                    <span class="badge bg-light text-muted border">
                                                        {{ $vrSummary['passed'] ?? 0 }}/{{ $vrSummary['total'] ?? 0 }} passed
                                                    </span>
                                                </div>

                                                {{-- Check groups --}}
                                                @foreach([
                                                    ['label' => __('Errors'),   'items' => $vrErrors,   'level' => 'danger',  'icon' => 'x-circle-fill'],
                                                    ['label' => __('Warnings'), 'items' => $vrWarnings, 'level' => 'warning', 'icon' => 'exclamation-triangle-fill'],
                                                ] as $group)
                                                    @if(count($group['items']) > 0)
                                                        <div class="mb-2"
                                                             x-data="{ open: true }">
                                                            <button class="btn btn-sm w-100 text-start d-flex justify-content-between align-items-center border-0 px-0 py-1"
                                                                    @click="open = !open"
                                                                    style="font-size:.78rem; font-weight:600; color: var(--bs-{{ $group['level'] }})">
                                                                <span>
                                                                    <i class="bi bi-{{ $group['icon'] }} me-1"></i>
                                                                    {{ $group['label'] }} ({{ count($group['items']) }})
                                                                </span>
                                                                <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                                            </button>
                                                            <div x-show="open" x-collapse>
                                                                @foreach($group['items'] as $check)
                                                                    <div class="border-start border-{{ $group['level'] }} border-2 ps-2 mb-1"
                                                                         style="font-size:.76rem">
                                                                        <div class="fw-semibold">
                                                                            <span class="badge bg-{{ $group['level'] === 'danger' ? 'danger' : 'warning' }}-subtle
                                                                                         text-{{ $group['level'] }}
                                                                                         border border-{{ $group['level'] }}-subtle me-1"
                                                                                  style="font-size:.65rem">{{ $check['id'] ?? '' }}</span>
                                                                            {{ $check['message'] ?? '' }}
                                                                        </div>
                                                                        @if($check['detail'] ?? null)
                                                                            <div class="text-muted">{{ $check['detail'] }}</div>
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach

                                                {{-- All passed --}}
                                                @if($vr['passed'] && count($vrWarnings) === 0)
                                                    <div class="text-success small">
                                                        <i class="bi bi-check-circle-fill me-1"></i>
                                                        All {{ $vrSummary['total'] ?? 0 }} checks {{ __('passed — metadata is valid') }}
                                                    </div>
                                                @elseif($vr['passed'])
                                                    <div class="text-success small">
                                                        <i class="bi bi-check-circle-fill me-1"></i>
                                                        {{ __('No blocking errors — review warnings above') }}
                                                    </div>
                                                @endif

                                                <div class="text-muted mt-2" style="font-size:.7rem">
                                                    {{ __('Validated') }} {{ now()->diffForHumans() }}
                                                </div>

                                            @elseif($entity->metadata_validated_at)
                                                <div class="text-muted small">
                                                    {{ __('Last validated') }} {{ $entity->metadata_validated_at->diffForHumans() }}.
                                                    @if($entity->metadata_valid)
                                                        <span class="text-success">{{ __('Passed.') }}</span>
                                                    @else
                                                        <span class="text-danger">{{ __('Issues found.') }}</span>
                                                    @endif
                                                    <br>Click <strong>{{ __('Run') }}</strong> {{ __('to validate now.') }}
                                                </div>
                                            @else
                                                <div class="text-muted small">
                                                    {{ __('Not yet validated. Click ') }}<strong>{{ __('Run') }}</strong> {{ __('to check this entity.') }}
                                                </div>
                                            @endif
                                        </div>

                                    </div>

                                    {{-- Bottom action bar --}}
                                    <div class="d-flex gap-2 mt-3 pt-3 border-top">
                                        @can('entity.view')
                                            <a href="{{ route('entities.show', $entity) }}"
                                               class="btn btn-sm btn-outline-dark"
                                               @click.stop>
                                                <i class="bi bi-eye me-1"></i>{{ __('Full Details') }}
                                            </a>
                                        @endcan
                                        @can('entity.edit')
                                            <a href="{{ route('entities.edit', $entity) }}"
                                               class="btn btn-sm btn-outline-primary"
                                               @click.stop>
                                                <i class="bi bi-pencil me-1"></i>{{ __('Edit') }}
                                            </a>
                                        @endcan
                                        @can('metadata.view')
                                            <a href="{{ route('entities.metadata', $entity) }}"
                                               target="_blank"
                                               class="btn btn-sm btn-outline-secondary"
                                               @click.stop>
                                                <i class="bi bi-code-slash me-1"></i>{{ __('View XML') }}
                                            </a>
                                        @endcan
                                        @can('entity.edit')
                                        @if($entity->status === 'active')
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-warning ms-auto"
                                                    @click.stop="$dispatch('open-suspend-modal', { entityId: '{{ $entity->id }}' })">
                                                <i class="bi bi-pause-circle me-1"></i>{{ __('app.action_suspend') }}
                                            </button>
                                        @elseif($entity->status === 'suspended')
                                            <button type="button"
                                                    class="btn btn-sm btn-success ms-auto"
                                                    @click.stop="$dispatch('open-reactivate-modal', { entityId: '{{ $entity->id }}' })">
                                                <i class="bi bi-play-circle me-1"></i>{{ __('app.action_reactivate') }}
                                            </button>
                                            <form method="POST"
                                                  action="{{ route('entities.destroy', $entity) }}"
                                                  class="d-inline"
                                                  onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.entity.delete_title, text: Lang.entity.delete_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-sm btn-danger"
                                                        title="{{ __('app.action_delete') }}">
                                                    <i class="bi bi-trash me-1"></i>{{ __('app.action_delete') }}
                                                </button>
                                            </form>
                                        @elseif(in_array($entity->status, ['draft', 'pending']))
                                            <form method="POST"
                                                  action="{{ route('entities.destroy', $entity) }}"
                                                  class="d-inline ms-auto"
                                                  onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.entity.delete_title, text: Lang.entity.delete_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="{{ __('app.action_delete') }}">
                                                    <i class="bi bi-trash me-1"></i>{{ __('app.action_delete') }}
                                                </button>
                                            </form>
                                        @endif
                                        @endcan
                                    </div>

                                </div>
                            </td>
                        </tr>
                    @endif

                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            {{ __('No entities match your filters.') }}
                            @if($this->hasActiveFilters())
                                <br>
                                <button wire:click="resetFilters" class="btn btn-sm btn-link mt-1">
                                    {{ __('Clear all filters') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($this->entities->hasPages())
        <div class="card-footer bg-transparent border-top d-flex align-items-center justify-content-between py-2 px-3">
            <div class="text-muted small">
                Page {{ $this->entities->currentPage() }} of {{ $this->entities->lastPage() }}
            </div>
            <div>{{ $this->entities->links('vendor.pagination.bootstrap-5') }}</div>
        </div>
    @endif
</div>

<livewire:entity-suspend-modal />
<livewire:entity-reactivate-modal />

</div>
