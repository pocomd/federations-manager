{{--
    resources/views/livewire/federation-manager.blade.php

    FederationManager Livewire component.
    Filter card + table of federations; per-row metadata generation.
    Per-row "Check status" button manually polls GenerateMetadataJob cache key.
--}}

<div>

{{-- ── Filter card ────────────────────────────────────────────────────────── --}}
<div class="card card-body mb-3 p-2">
    <div class="row g-2 align-items-end">

        {{-- Search --}}
        <div class="col-12 col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       class="form-control form-control-sm border-start-0 ps-0"
                       placeholder="Search by name or URI…">
                @if($search)
                    <button wire:click="$set('search', '')" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x"></i>
                    </button>
                @endif
            </div>
        </div>

        {{-- Status filter --}}
        <div class="col-6 col-md-3">
            <select wire:model.live="statusFilter" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        {{-- Clear --}}
        @if($search || $statusFilter)
        <div class="col-auto">
            <button wire:click="resetFilters" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg"></i> Clear
            </button>
        </div>
        @endif

    </div>
</div>

{{-- ── Federation table ───────────────────────────────────────────────────── --}}
<div class="card border shadow-none">

    <div wire:loading.delay.shortest class="text-center py-3 text-muted small border-bottom">
        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
        Loading…
    </div>

    <div wire:loading.remove.delay.shortest>
    @if($this->federations->isEmpty())
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-collection fs-1 opacity-25 d-block mb-2"></i>
            @if($search || $statusFilter)
                No federations match your filters.
                <div class="mt-2">
                    <button wire:click="resetFilters" class="btn btn-sm btn-link">Clear filters</button>
                </div>
            @else
                No federations found.
                @can('federation.create')
                <div class="mt-2">
                    <a href="{{ route('federations.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> Create First Federation
                    </a>
                </div>
                @endcan
            @endif
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered mb-0 align-middle" style="font-size:.8125rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Name</th>
                        <th class="fw-semibold text-secondary text-uppercase d-none d-md-table-cell" style="font-size:.7rem;letter-spacing:.05em;">URI</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Status</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Entities</th>
                        <th class="text-center fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->federations as $federation)
                    @php
                        $isGenerating  = $generatingFor[$federation->id] ?? false;
                        $metadataReady = $this->hasMetadata($federation->id);
                        $pendingCount  = $federation->pending_entities_count ?? 0;
                    @endphp
                    <tr wire:key="fed-{{ $federation->id }}">

                        <td class="ps-3">
                            <a href="{{ route('federations.show', $federation) }}"
                               class="fw-semibold text-dark text-decoration-none">
                                {{ $federation->name }}
                            </a>
                            @if($federation->description)
                                <div class="text-muted" style="font-size:.72rem;">
                                    {{ \Illuminate\Support\Str::limit($federation->description, 60) }}
                                </div>
                            @endif
                        </td>

                        <td class="d-none d-md-table-cell">
                            <span class="font-monospace text-muted" style="font-size:.72rem;">
                                {{ $federation->uri }}
                            </span>
                        </td>

                        <td>
                            @php $dot = $federation->status === 'active' ? 'status-dot-success' : 'status-dot-muted'; @endphp
                            <span class="d-inline-flex align-items-center gap-1">
                                <span class="status-dot {{ $dot }}"></span>
                                <span>{{ $federation->status === 'active' ? 'Active' : 'Inactive' }}</span>
                            </span>
                        </td>

                        <td>
                            <span class="badge bg-primary me-1">
                                {{ $federation->active_entities_count ?? 0 }} active
                            </span>
                            @if($pendingCount > 0)
                                <span class="badge bg-warning text-dark">{{ $pendingCount }} pending</span>
                            @endif
                        </td>

                        <td class="text-center">
                            <div>
                                @can('metadata.generate')
                                    @if($isGenerating)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-warning"
                                            wire:click="pollMetadataStatus"
                                            wire:loading.attr="disabled"
                                            wire:target="pollMetadataStatus"
                                            title="Generating… click to check if ready">
                                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            wire:loading.attr="disabled"
                                            wire:target="generateMetadata('{{ $federation->id }}')"
                                            title="{{ $metadataReady ? 'Regenerate metadata' : 'Generate metadata' }}"
                                            @click="SwalDefault.fire({title: Lang.federation.metadata_title, text: Lang.federation.metadata_text, icon: 'info', showCancelButton: true, confirmButtonText: Lang.confirm.confirm}).then(r => { if (r.isConfirmed) $wire.generateMetadata('{{ $federation->id }}') })">
                                            <i class="bi bi-file-code"></i>
                                        </button>
                                    @endif
                                @endcan
                                @if($metadataReady)
                                    <a href="{{ route('metadata.download', $federation) }}"
                                       class="btn btn-sm btn-outline-success"
                                       target="_blank"
                                       title="Download XML">
                                        <i class="bi bi-download"></i>
                                    </a>
                                @endif
                                <a href="{{ route('federations.show', $federation) }}"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Open">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @can('federation.edit')
                                    @if($federation->status === 'active')
                                        <button type="button"
                                                class="btn btn-sm btn-outline-warning"
                                                title="{{ __('app.action_deactivate') }}"
                                                wire:click="$dispatch('open-deactivate-modal', { federationId: '{{ $federation->id }}' })">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                disabled
                                                title="{{ __('app.federation_delete_blocked_active') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @elseif($federation->status === 'inactive')
                                        <form method="POST"
                                            action="{{ route('federations.destroy', $federation) }}"
                                            style="display:contents"
                                            onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.federation.delete_title, text: Lang.federation.delete_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
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
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($this->federations->hasPages())
            <div class="card-footer bg-transparent border-top d-flex align-items-center justify-content-between py-2 px-3">
                <div class="text-muted small">
                    {{ $this->federations->total() }} federation{{ $this->federations->total() === 1 ? '' : 's' }}
                </div>
                <div>{{ $this->federations->links('vendor.pagination.bootstrap-5') }}</div>
            </div>
        @endif
    @endif
    </div>

</div>{{-- /card --}}

<livewire:federation-deactivate-modal />

</div>{{-- /wire:poll --}}
