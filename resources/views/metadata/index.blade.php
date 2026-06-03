@extends('layouts.app')

@section('title', 'Metadata Management')

@section('content')
<div class="container-fluid py-4 px-4">

{{-- ── Page header ─────────────────────────────────────────────────────────── --}}
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h1 class="h4 mb-1 fw-bold">Metadata Management</h1>
        <p class="text-muted small mb-0">
            Generate, sign, and download federation metadata aggregates.
        </p>
    </div>
    <button type="button"
            class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
            data-bs-toggle="modal" data-bs-target="#signing-info-modal">
        <i class="bi bi-info-circle"></i> Signing info
    </button>
</div>

{{-- ── Signing info modal ───────────────────────────────────────────────────── --}}
@php
    $lastSigned = $federations->whereNotNull('metadata_generated_at')->sortByDesc('metadata_generated_at')->first()?->metadata_generated_at;
    $nextSigned = ($autoEnabled && $lastSigned) ? $lastSigned->addMinutes($autoInterval) : null;
@endphp
<div class="modal fade" id="signing-info-modal" tabindex="-1" aria-labelledby="signing-info-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-1">
                <h5 class="modal-title fw-semibold" id="signing-info-label">
                    <i class="bi bi-file-earmark-lock me-2 text-muted"></i>Automated Signing
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <table class="table table-sm table-bordered" style="font-size:.8125rem;">
                    <tbody>
                        <tr>
                            <th class="text-muted fw-normal w-50">Status</th>
                            <td>
                                @if($autoEnabled)
                                    <span class="badge bg-success">Enabled</span>
                                @else
                                    <span class="badge bg-secondary">Disabled</span>
                                @endif
                            </td>
                        </tr>
                        @if($autoEnabled)
                        <tr>
                            <th class="text-muted fw-normal">Interval</th>
                            <td>Every {{ $autoInterval }} minutes</td>
                        </tr>
                        @endif
                        <tr>
                            <th class="text-muted fw-normal">Cache duration</th>
                            <td>{{ $cacheDurationHours }} hours</td>
                        </tr>
                        <tr>
                            <th class="text-muted fw-normal">Valid until</th>
                            <td>{{ $validUntilHours }} hours from generation</td>
                        </tr>
                        <tr>
                            <th class="text-muted fw-normal">Last signed</th>
                            <td>
                                @if($lastSigned)
                                    {{ $lastSigned->format('d M Y H:i') }}
                                    <span class="text-muted">({{ $lastSigned->diffForHumans() }})</span>
                                @else
                                    <span class="text-muted">Never</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted fw-normal">Next scheduled</th>
                            <td>
                                @if($nextSigned)
                                    {{ $nextSigned->format('d M Y H:i') }}
                                    <span class="text-muted">({{ $nextSigned->diffForHumans() }})</span>
                                @elseif(!$autoEnabled)
                                    <span class="text-muted">Auto-signing disabled</span>
                                @else
                                    <span class="text-muted">Not yet generated</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
                @if(!$autoEnabled)
                @can('federation.create')
                <p class="text-muted small mb-0">
                    Enable automatic signing in
                    <a href="{{ route('scheduler.index') }}">Scheduler settings</a>.
                </p>
                @endcan
                @endif
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Section 1: Federation Metadata Status ──────────────────────────────── --}}
<div class="mb-4">
    <h5 class="fw-semibold mb-3">
        <i class="bi bi-file-code me-2 text-muted"></i>Federation Metadata Status
    </h5>

    @if ($federations->isEmpty())
        <div class="alert alert-info">No federations found.</div>
    @else
        <div class="row g-3">
            @foreach ($federations as $federation)
                @php $status = $cacheStatus[$federation->id] ?? ['ready' => false]; @endphp
                <div class="col-12 col-lg-6">
                    <div class="card border shadow-none h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div class="overflow-hidden me-3">
                                    <div class="fw-semibold text-truncate">{{ $federation->name }}</div>
                                    <div class="text-muted small font-monospace text-truncate">
                                        {{ $federation->uri }}
                                    </div>
                                </div>
                                @if ($status['ready'])
                                    <span class="badge bg-success flex-shrink-0">Ready</span>
                                @else
                                    <span class="badge bg-warning text-dark flex-shrink-0">Not generated</span>
                                @endif
                            </div>

                            <div class="d-flex align-items-center gap-2 text-muted small mb-3">
                                <i class="bi bi-diagram-3"></i>
                                <span>{{ $federation->active_entity_count }} active
                                    {{ Str::plural('entity', $federation->active_entity_count) }}</span>
                                @if ($federation->metadata_generated_at)
                                    <span class="text-muted opacity-50">|</span>
                                    <i class="bi bi-clock"></i>
                                    <span>Last generated
                                        {{ $federation->metadata_generated_at->diffForHumans() }}</span>
                                @endif
                            </div>

                            <div class="d-flex gap-2">
                                @can('metadata.generate')
                                    <form method="POST"
                                          action="{{ route('metadata.generate', $federation) }}">
                                        @csrf
                                        <button type="submit"
                                                class="btn btn-sm btn-primary d-flex align-items-center gap-1">
                                            <i class="bi bi-arrow-clockwise"></i>
                                            Generate &amp; Sign
                                        </button>
                                    </form>
                                @endcan

                                @if ($status['ready'])
                                    <a href="{{ route('metadata.download', $federation) }}"
                                       class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                                        <i class="bi bi-download"></i>
                                        Download XML
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ── Section 2: Validation Issues (last 24 h) ───────────────────────────── --}}
<div class="card border shadow-none mb-4"
     x-data="{ open: {{ $recentFailures->isNotEmpty() ? 'true' : 'false' }} }">
    <div class="card-header bg-transparent border-bottom d-flex align-items-center
                justify-content-between py-3"
         role="button"
         @click="open = !open"
         style="cursor:pointer;">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-exclamation-circle me-2 text-muted"></i>
            Validation Issues
            <small class="text-muted fw-normal">(last 24 h)</small>
            @if ($recentFailures->isNotEmpty())
                <span class="badge bg-danger ms-1">{{ $recentFailures->count() }}</span>
            @endif
        </h6>
        <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
    </div>

    <div x-show="open" x-collapse>
        @if ($recentFailures->isEmpty())
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 text-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>All entities passed validation in the last 24 hours.</span>
                </div>
            </div>
        @else
            <ul class="list-group list-group-flush">
                @foreach ($recentFailures as $entityId => $results)
                    @php
                        $entity     = $results->first()->entity;
                        $errorCount = $results->sum(fn($r) => count($r->errors));
                    @endphp
                    <li class="list-group-item px-4 py-3 d-flex align-items-center
                               justify-content-between gap-3">
                        <div class="overflow-hidden">
                            <div class="fw-medium text-truncate">
                                {{ $entity?->getDisplayName('en') ?? $entityId }}
                            </div>
                            <div class="text-muted small font-monospace text-truncate">
                                {{ $entity?->entity_id ?? $entityId }}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <span class="badge bg-danger rounded-1">
                                {{ $errorCount }} {{ Str::plural('error', $errorCount) }}
                            </span>
                            @if ($entity)
                                <a href="{{ route('entities.show', $entity) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

{{-- ── Section 3: Quick Entity Lookup ─────────────────────────────────────── --}}
<div class="card border shadow-none">
    <div class="card-header bg-transparent border-bottom py-2">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-search me-2 text-muted"></i>Quick Entity Lookup
        </h6>
    </div>
    <div class="card-body">
        @livewire('metadata-preview')
    </div>
</div>

</div>{{-- /container-fluid --}}
@endsection
