@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

<div class="py-3">

{{-- ── Stat cards ──────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">

    {{-- Total entities --}}
    <div class="col-6 col-md-3">
        <a href="{{ route('entities.index') }}" class="text-decoration-none">
            <div class="card border shadow-none h-100">
                <div class="card-body text-center py-3">
                    <div class="fw-bold fs-2 text-dark lh-1 mb-1">{{ $totalEntities }}</div>
                    <div class="text-muted small">{{ __('app.dashboard_total_entities') }}</div>
                </div>
            </div>
        </a>
    </div>

    {{-- Active entities --}}
    <div class="col-6 col-md-3">
        <a href="{{ route('entities.index', ['status' => 'active']) }}" class="text-decoration-none">
            <div class="card border shadow-none h-100">
                <div class="card-body text-center py-3">
                    <div class="fw-bold fs-2 text-success lh-1 mb-1">{{ $activeEntities }}</div>
                    <div class="text-muted small">{{ __('app.dashboard_active') }}</div>
                </div>
            </div>
        </a>
    </div>

    {{-- Federations --}}
    <div class="col-6 col-md-3">
        <a href="{{ route('federations.index') }}" class="text-decoration-none">
            <div class="card border shadow-none h-100">
                <div class="card-body text-center py-3">
                    <div class="fw-bold fs-2 text-primary lh-1 mb-1">{{ $totalFederations }}</div>
                    <div class="text-muted small">{{ __('app.dashboard_federations') }}</div>
                </div>
            </div>
        </a>
    </div>

    {{-- Critical certs --}}
    <div class="col-6 col-md-3">
        <a href="{{ route('certificates.monitor') }}" class="text-decoration-none">
            <div class="card border shadow-none h-100 {{ $criticalCerts > 0 ? 'border-danger' : '' }}">
                <div class="card-body text-center py-3">
                    <div class="fw-bold fs-2 lh-1 mb-1 {{ $criticalCerts > 0 ? 'text-danger' : 'text-dark' }}">
                        {{ $criticalCerts }}
                        @if($criticalCerts > 0)
                            <i class="bi bi-exclamation-triangle-fill" style="font-size:1.1rem;vertical-align:middle;"></i>
                        @endif
                    </div>
                    <div class="text-muted small">{{ __('app.dashboard_critical_certs') }}</div>
                </div>
            </div>
        </a>
    </div>

</div>

{{-- ── Row 1: Recent Entities + Cert Expiry ───────────────────────────────── --}}
<div class="row g-3 mb-3">

    {{-- Recent Entities --}}
    <div class="col-12 col-lg-7">
        <div class="card border shadow-none h-100">
            <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
                <span class="card-header-label">Recent Entities</span>
                <a href="{{ route('entities.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem;padding:.2rem .5rem;">
                    View all
                </a>
            </div>
            <div class="card-body p-0">
                @if ($recentEntities->isEmpty())
                    <p class="text-muted text-center py-4 mb-0 small">No entities yet.</p>
                @else
                    <table class="table table-sm table-hover table-bordered mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Entity</th>
                                <th style="width:55px;">Type</th>
                                <th style="width:70px;">Status</th>
                                <th style="width:90px;">Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentEntities as $entity)
                            <tr>
                                <td>
                                    <a href="{{ route('entities.show', $entity) }}"
                                       class="text-decoration-none fw-medium text-dark d-block text-truncate"
                                       style="max-width:280px;">
                                        {{ $entity->getDisplayName('en') ?? $entity->entity_id }}
                                    </a>
                                    <span class="font-monospace text-muted d-block text-truncate"
                                          style="font-size:.68rem;max-width:280px;">
                                        {{ $entity->entity_id }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge rounded-1
                                        {{ $entity->type === 'idp' ? 'bg-info text-dark' : 'bg-secondary' }}"
                                          style="font-size:.65rem;">
                                        {{ strtoupper($entity->type) }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $dotClass = match($entity->status) {
                                            'active'    => 'status-dot-success',
                                            'suspended' => 'status-dot-danger',
                                            'pending'   => 'status-dot-warning',
                                            default     => 'status-dot-muted',
                                        };
                                    @endphp
                                    <span class="d-inline-flex align-items-center gap-1">
                                        <span class="status-dot {{ $dotClass }}"></span>
                                        <span>{{ ucfirst($entity->status) }}</span>
                                    </span>
                                </td>
                                <td class="text-muted">{{ $entity->created_at->diffForHumans(null, true) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- Certificate Expiry Summary --}}
    <div class="col-12 col-lg-5">
        <div class="card border shadow-none h-100">
            <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
                <span class="card-header-label">Certificate Expiry</span>
                <a href="{{ route('certificates.monitor') }}" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem;padding:.2rem .5rem;">
                    Monitor
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-bordered mb-0 small">
                    <tbody>
                        @foreach ([
                            ['label' => 'Expired',             'key' => 'expired',  'dot' => 'status-dot-danger',   'icon' => 'bi-x-circle'],
                            ['label' => 'Critical (≤ 14 days)','key' => 'critical', 'dot' => 'status-dot-danger',   'icon' => 'bi-exclamation-triangle'],
                            ['label' => 'Warning (≤ 30 days)', 'key' => 'warning',  'dot' => 'status-dot-warning',  'icon' => 'bi-exclamation-circle'],
                            ['label' => 'Advisory (≤ 60 days)','key' => 'advisory', 'dot' => 'status-dot-advisory', 'icon' => 'bi-info-circle'],
                        ] as $row)
                        <tr class="{{ $certSummary[$row['key']] > 0 && in_array($row['key'], ['expired','critical']) ? 'table-danger' : '' }}">
                            <td class="ps-3">
                                <span class="d-inline-flex align-items-center gap-1">
                                    <span class="status-dot {{ $row['dot'] }}"></span>
                                    {{ $row['label'] }}
                                </span>
                            </td>
                            <td class="text-end pe-3 fw-semibold">
                                {{ $certSummary[$row['key']] }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- ── Row 2: Recent Audit Log ─────────────────────────────────────────────── --}}
<div class="card border shadow-none">
    <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
        <span class="card-header-label">Recent Activity</span>
        <a href="{{ route('audit.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem;padding:.2rem .5rem;">
            Full log
        </a>
    </div>
    @if ($recentAuditLogs->isEmpty())
        <div class="card-body">
            <p class="text-muted text-center mb-0 small">No activity recorded yet.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">When</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentAuditLogs as $log)
                    <tr>
                        <td class="ps-3 text-muted">{{ $log->created_at->diffForHumans() }}</td>
                        <td>{{ $log->user?->name ?? '<system>' }}</td>
                        <td>
                            @php
                                $badgeClass = str_contains($log->action, 'created') || str_contains($log->action, 'imported') || str_contains($log->action, 'restored')
                                    ? 'bg-success'
                                    : (str_contains($log->action, 'deleted') ? 'bg-danger'
                                    : (str_contains($log->action, 'updated') ? 'bg-warning text-dark' : 'bg-secondary'));
                            @endphp
                            <span class="badge rounded-1 {{ $badgeClass }}" style="font-size:.65rem;">
                                {{ __('app.action_' . $log->action) }}
                            </span>
                        </td>
                        <td>
                            @if ($log->entity)
                                <a href="{{ route('entities.show', $log->entity) }}"
                                   class="text-decoration-none text-truncate d-inline-block"
                                   style="max-width:280px;">
                                    {{ $log->entity->getDisplayName('en') ?? $log->entity->entity_id }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

</div>
@endsection
