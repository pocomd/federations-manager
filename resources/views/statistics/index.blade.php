@extends('layouts.app')

@section('title', 'Statistics & Reports')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Statistics &amp; Reports</h1>
    </div>

    {{-- Row 1: stat cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-diagram-3 fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Entities</div>
                        <div class="fs-3 fw-bold">{{ $totalEntities }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Active Entities</div>
                        <div class="fs-3 fw-bold">{{ $activeEntities }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Entities</div>
                        <div class="fs-3 fw-bold">{{ $pendingEntities }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-key fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Critical Certs (≤14d)</div>
                        <div class="fs-3 fw-bold">{{ $criticalCerts }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 2: Registration trend | Members per Federation --}}
    <div class="row g-3 mb-4">
        <div class="{{ $federationCount === 1 ? 'col-lg-8' : 'col-lg-6' }}">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom fw-semibold">
                    Entity Registrations (last 12 months)
                </div>
                <div class="card-body">
                    <canvas id="registrationChart" height="200"></canvas>
                </div>
            </div>
        </div>

        {{-- Single federation: card layout --}}
        @if($federationCount === 1)
            @php $fed = $federations[0]; @endphp
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-bottom fw-semibold">
                        Members per Federation
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center gap-3">
                        <div class="text-center fw-semibold text-secondary">{{ $fed['name'] }}</div>
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <div class="rounded-3 p-3 bg-primary bg-opacity-10">
                                    <div class="text-muted small mb-1">Total</div>
                                    <div class="fs-3 fw-bold text-primary">{{ $fed['total_count'] }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="rounded-3 p-3 bg-success bg-opacity-10">
                                    <div class="text-muted small mb-1">Active</div>
                                    <div class="fs-3 fw-bold text-success">{{ $fed['active_count'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        {{-- Multiple federations: grouped bar chart --}}
        @elseif($federationCount > 1)
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-bottom fw-semibold">
                        Members per Federation
                    </div>
                    <div class="card-body">
                        <canvas id="federationChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Row 3: Export buttons --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom fw-semibold">
            Export Data
        </div>
        <div class="card-body d-flex flex-wrap gap-2">
            <a href="{{ route('statistics.export.entities') }}" class="btn btn-outline-primary">
                <i class="bi bi-download me-1"></i> Export Entities (CSV)
            </a>
            <a href="{{ route('statistics.export.certificates') }}" class="btn btn-outline-primary">
                <i class="bi bi-download me-1"></i> Export Certificates (CSV)
            </a>
            <a href="{{ route('statistics.export.memberships') }}" class="btn btn-outline-primary">
                <i class="bi bi-download me-1"></i> Export Memberships (CSV)
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const intTicks = { ticks: { precision: 0 } };

    // Registration trend — Bar
    new Chart(document.getElementById('registrationChart'), {
        type: 'bar',
        data: {
            labels: @json(array_column($registrationTrend, 'month')),
            datasets: [{
                label: 'New Entities',
                data:  @json(array_column($registrationTrend, 'total')),
                backgroundColor: 'rgba(13, 110, 253, 0.6)',
                borderColor:     'rgba(13, 110, 253, 1)',
                borderWidth: 1,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: intTicks },
        },
    });

    @if($federationCount > 1)
    // Members per Federation — grouped bar (total + active)
    new Chart(document.getElementById('federationChart'), {
        type: 'bar',
        data: {
            labels: @json(array_column($federations, 'name')),
            datasets: [
                {
                    label: 'Total',
                    data:  @json(array_column($federations, 'total_count')),
                    backgroundColor: 'rgba(13, 110, 253, 0.5)',
                    borderColor:     'rgba(13, 110, 253, 1)',
                    borderWidth: 1,
                },
                {
                    label: 'Active',
                    data:  @json(array_column($federations, 'active_count')),
                    backgroundColor: 'rgba(25, 135, 84, 0.5)',
                    borderColor:     'rgba(25, 135, 84, 1)',
                    borderWidth: 1,
                },
            ],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: { x: intTicks },
        },
    });
    @endif
})();
</script>
@endpush
