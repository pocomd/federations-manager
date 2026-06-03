@extends('layouts.app')

@section('title', 'Certificate Monitor — Federation Registry')

@section('content')
<div class="container-fluid py-4 px-4">

    <h1 class="h4 mb-1 fw-bold">Certificate Monitor</h1>
    <p class="text-muted small mb-4">
        X.509 certificate expiry dashboard across all active entities.
        <span class="ms-2">Last checked: {{ \Carbon\Carbon::parse($summary['checked_at'])->diffForHumans() }}</span>
    </p>

    @php
        $defaultTab = match(true) {
            $summary['expired']  > 0 => 'tab-expired',
            $summary['critical'] > 0 => 'tab-critical',
            $summary['warning']  > 0 => 'tab-warning',
            $summary['advisory'] > 0 => 'tab-advisory',
            default                  => 'tab-advisory',
        };
        $allHealthy = $summary['expired'] + $summary['critical'] + $summary['warning'] + $summary['advisory'] === 0;
    @endphp

    {{-- ── Summary cards ─────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-danger h-100 {{ $summary['expired'] > 0 ? 'shadow-sm' : 'opacity-50' }}"
                 role="button" onclick="activateCertTab('tab-expired')" style="cursor:pointer;transition:box-shadow .15s">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold text-danger">{{ $summary['expired'] }}</div>
                    <div class="small text-muted">Expired</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-warning h-100 {{ $summary['critical'] > 0 ? 'shadow-sm' : 'opacity-50' }}"
                 role="button" onclick="activateCertTab('tab-critical')" style="cursor:pointer;transition:box-shadow .15s">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold text-warning">{{ $summary['critical'] }}</div>
                    <div class="small text-muted">Critical (&le;14d)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-info h-100 {{ $summary['warning'] > 0 ? 'shadow-sm' : 'opacity-50' }}"
                 role="button" onclick="activateCertTab('tab-warning')" style="cursor:pointer;transition:box-shadow .15s">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold text-info">{{ $summary['warning'] }}</div>
                    <div class="small text-muted">Warning (&le;30d)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-success h-100 opacity-{{ $summary['healthy'] > 0 ? '100' : '50' }}">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold text-success">{{ $summary['healthy'] }}</div>
                    <div class="small text-muted">Healthy</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── All-healthy banner (shown instead of tabs when no cert needs attention) --}}
    @if($allHealthy)
    <div class="card border-success">
        <div class="card-body text-center py-5">
            <i class="bi bi-shield-check text-success d-block mb-3" style="font-size:3rem"></i>
            <h5 class="fw-bold text-success mb-1">All certificates are healthy</h5>
            <p class="text-muted mb-0 small">
                No certificates are expiring within the next {{ \App\Http\Controllers\CertificateMonitoringController::THRESHOLD_INFO }} days.
                {{ $summary['healthy'] }} certificate{{ $summary['healthy'] === 1 ? '' : 's' }} across all active entities.
            </p>
        </div>
    </div>
    @else

    {{-- ── Tabs ────────────────────────────────────────────────────────────── --}}
    <div id="cert-tabs-section">

        <ul class="nav nav-tabs mb-0" id="certTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $defaultTab === 'tab-expired' ? 'active' : '' }}"
                        id="tab-expired-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-expired"
                        type="button" role="tab">
                    <i class="bi bi-x-circle me-1"></i>Expired
                    @if($summary['expired'] > 0)
                        <span class="badge bg-danger ms-1">{{ $summary['expired'] }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $defaultTab === 'tab-critical' ? 'active' : '' }}"
                        id="tab-critical-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-critical"
                        type="button" role="tab">
                    <i class="bi bi-exclamation-triangle me-1"></i>Critical
                    @if($summary['critical'] > 0)
                        <span class="badge bg-warning text-dark ms-1">{{ $summary['critical'] }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $defaultTab === 'tab-warning' ? 'active' : '' }}"
                        id="tab-warning-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-warning"
                        type="button" role="tab">
                    <i class="bi bi-clock me-1"></i>Warning
                    @if($summary['warning'] > 0)
                        <span class="badge bg-info text-dark ms-1">{{ $summary['warning'] }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $defaultTab === 'tab-advisory' ? 'active' : '' }}"
                        id="tab-advisory-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-advisory"
                        type="button" role="tab">
                    <i class="bi bi-info-circle me-1"></i>Advisory
                    @if($summary['advisory'] > 0)
                        <span class="badge bg-secondary ms-1">{{ $summary['advisory'] }}</span>
                    @endif
                </button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 rounded-bottom" id="certTabsContent">

            {{-- ── Expired ──────────────────────────────────────────────────── --}}
            <div class="tab-pane fade {{ $defaultTab === 'tab-expired' ? 'show active' : '' }}"
                 id="tab-expired" role="tabpanel">
                @if(empty($expired))
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-check-circle text-success d-block mb-2" style="font-size:2rem"></i>
                        No expired certificates.
                    </div>
                @else
                    {{-- Proposed actions --}}
                    <div class="alert alert-danger rounded-0 border-start-0 border-end-0 border-top-0 mb-0 px-4 py-3">
                        <div class="d-flex gap-3">
                            <i class="bi bi-exclamation-octagon-fill flex-shrink-0 mt-1" style="font-size:1.25rem"></i>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="fw-bold">Immediate Action Required</div>
                                    <button class="btn btn-sm btn-link text-danger p-0 ms-3 text-decoration-none"
                                            type="button" data-bs-toggle="collapse"
                                            data-bs-target="#rec-expired"
                                            aria-expanded="true">
                                        <i class="bi bi-chevron-up" id="rec-expired-icon"></i>
                                    </button>
                                </div>
                                <div class="collapse show" id="rec-expired">
                                    <p class="mb-0 small mt-1">
                                        These certificates have expired. While many federation peers continue to authenticate
                                        using fingerprint validation, expired certificates breach eduGAIN compliance and must
                                        be replaced immediately.
                                    </p>
                                    <ol class="mb-0 small mt-2">
                                        <li>Contact the entity's technical administrator (use the <strong>Email Admin</strong> button below).</li>
                                        <li>Request generation of a new certificate and upload to their identity provider / service provider.</li>
                                        <li>Once the entity's metadata is updated, regenerate federation metadata to publish the change.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                    @include('certificates._cert_table', [
                        'certs'         => $expired,
                        'expiresLabel'  => 'Expired On',
                        'daysLabel'     => 'Overdue',
                        'daysBadge'     => 'bg-danger',
                        'daysPrefix'    => '',
                        'daysSuffix'    => 'd overdue',
                        'daysAbs'       => true,
                        'dateClass'     => 'text-danger fw-semibold',
                    ])
                @endif
            </div>

            {{-- ── Critical ─────────────────────────────────────────────────── --}}
            <div class="tab-pane fade {{ $defaultTab === 'tab-critical' ? 'show active' : '' }}"
                 id="tab-critical" role="tabpanel">
                @if(empty($critical))
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-check-circle text-success d-block mb-2" style="font-size:2rem"></i>
                        No certificates in critical state.
                    </div>
                @else
                    {{-- Proposed actions --}}
                    <div class="alert alert-warning rounded-0 border-start-0 border-end-0 border-top-0 mb-0 px-4 py-3">
                        <div class="d-flex gap-3">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1" style="font-size:1.25rem"></i>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="fw-bold">Urgent — Certificates Expiring Within 14 Days</div>
                                    <button class="btn btn-sm btn-link text-warning p-0 ms-3 text-decoration-none"
                                            type="button" data-bs-toggle="collapse"
                                            data-bs-target="#rec-critical"
                                            aria-expanded="true">
                                        <i class="bi bi-chevron-up" id="rec-critical-icon"></i>
                                    </button>
                                </div>
                                <div class="collapse show" id="rec-critical">
                                    <p class="mb-0 small mt-1">
                                        Begin rotation immediately to avoid service interruption.
                                        Rotating a live certificate without coordination can briefly disrupt authentication.
                                    </p>
                                    <ol class="mb-0 small mt-2">
                                        <li>Contact each entity's administrator now and schedule a maintenance window.</li>
                                        <li>Upload the <strong>new certificate alongside the existing one</strong> (dual-cert overlap) before removing the expiring certificate — this prevents downtime during propagation.</li>
                                        <li>Regenerate and republish federation metadata after each certificate update.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                    @include('certificates._cert_table', [
                        'certs'        => $critical,
                        'expiresLabel' => 'Expires On',
                        'daysLabel'    => 'Time Left',
                        'daysBadge'    => 'bg-warning text-dark',
                        'daysPrefix'   => '',
                        'daysSuffix'   => 'd left',
                        'daysAbs'      => false,
                        'dateClass'    => 'text-warning fw-semibold',
                    ])
                @endif
            </div>

            {{-- ── Warning ──────────────────────────────────────────────────── --}}
            <div class="tab-pane fade {{ $defaultTab === 'tab-warning' ? 'show active' : '' }}"
                 id="tab-warning" role="tabpanel">
                @if(empty($warning))
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-check-circle text-success d-block mb-2" style="font-size:2rem"></i>
                        No certificates in warning state.
                    </div>
                @else
                    @include('certificates._cert_table', [
                        'certs'        => $warning,
                        'expiresLabel' => 'Expires On',
                        'daysLabel'    => 'Time Left',
                        'daysBadge'    => 'bg-info text-dark',
                        'daysPrefix'   => '',
                        'daysSuffix'   => 'd left',
                        'daysAbs'      => false,
                        'dateClass'    => '',
                    ])
                @endif
            </div>

            {{-- ── Advisory ─────────────────────────────────────────────────── --}}
            <div class="tab-pane fade {{ $defaultTab === 'tab-advisory' ? 'show active' : '' }}"
                 id="tab-advisory" role="tabpanel">
                @if(empty($advisory))
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-check-circle text-success d-block mb-2" style="font-size:2rem"></i>
                        No certificates in advisory state.
                    </div>
                @else
                    @include('certificates._cert_table', [
                        'certs'        => $advisory,
                        'expiresLabel' => 'Expires On',
                        'daysLabel'    => 'Time Left',
                        'daysBadge'    => 'bg-secondary',
                        'daysPrefix'   => '',
                        'daysSuffix'   => 'd left',
                        'daysAbs'      => false,
                        'dateClass'    => '',
                    ])
                @endif
            </div>

        </div>{{-- /tab-content --}}
    </div>{{-- /cert-tabs-section --}}

    @endif{{-- /allHealthy --}}

</div>

@push('scripts')
<script>
function activateCertTab(tabId) {
    const trigger = document.querySelector('[data-bs-target="#' + tabId + '"]');
    if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
    document.getElementById('cert-tabs-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

['rec-expired', 'rec-critical'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('hide.bs.collapse', () => {
        document.getElementById(id + '-icon')?.classList.replace('bi-chevron-up', 'bi-chevron-down');
    });
    el.addEventListener('show.bs.collapse', () => {
        document.getElementById(id + '-icon')?.classList.replace('bi-chevron-down', 'bi-chevron-up');
    });
});
</script>
@endpush

@endsection
