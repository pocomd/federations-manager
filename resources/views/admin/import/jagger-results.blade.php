@extends('layouts.app')

@section('title', 'Import Results — Jagger')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">Import Results</h1>
            <p class="text-muted small mb-0">Jagger → Federation Registry migration summary</p>
        </div>
        <a href="{{ route('import.jagger') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Run another import
        </a>
    </div>

    {{-- Summary cards --}}
    @php
        $totalCreated = collect($stats)->sum('created');
        $totalSkipped = collect($stats)->sum('skipped');
        $totalErrors  = collect($stats)->sum('errors');
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h3 fw-bold text-success mb-0">{{ $totalCreated }}</div>
                <div class="small text-muted">Records created</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h3 fw-bold text-secondary mb-0">{{ $totalSkipped }}</div>
                <div class="small text-muted">Records skipped</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h3 fw-bold {{ $totalErrors > 0 ? 'text-danger' : 'text-success' }} mb-0">{{ $totalErrors }}</div>
                <div class="small text-muted">Errors</div>
            </div>
        </div>
    </div>

    {{-- Per-step breakdown --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom pt-3 pb-2">
            <h6 class="fw-semibold mb-0">Step-by-step breakdown</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="small">Step</th>
                        <th class="small text-end">Created</th>
                        <th class="small text-end">Skipped</th>
                        <th class="small text-end">Errors</th>
                        <th class="small text-end">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $labels = [
                            'federations'       => 'Federations',
                            'entities'          => 'Entities',
                            'memberships'       => 'Federation memberships',
                            'certificates'      => 'Certificates',
                            'contacts'          => 'Contacts',
                            'endpoints'         => 'Endpoints',
                            'attributes'        => 'Attribute definitions',
                            'attr_requirements' => 'Attribute requirements',
                        ];
                    @endphp
                    @foreach($labels as $key => $label)
                    @php $row = $stats[$key] ?? []; @endphp
                    <tr>
                        <td class="small">{{ $label }}</td>
                        <td class="small text-end {{ ($row['created'] ?? 0) > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
                            {{ $row['created'] ?? 0 }}
                        </td>
                        <td class="small text-end text-muted">{{ $row['skipped'] ?? 0 }}</td>
                        <td class="small text-end {{ ($row['errors'] ?? 0) > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                            {{ $row['errors'] ?? 0 }}
                        </td>
                        <td class="small text-end text-muted">
                            @if($key === 'entities' && ($row['both_type'] ?? 0) > 0)
                                {{ $row['both_type'] }} imported as <code>idp</code> (were BOTH)
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Error log --}}
    @if(!empty($errors))
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom pt-3 pb-2">
            <h6 class="fw-semibold mb-0 text-danger">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Error log ({{ count($errors) }})
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush" style="max-height:400px;overflow-y:auto">
                @foreach($errors as $err)
                <div class="list-group-item list-group-item-danger py-1 px-3 small font-monospace">
                    {{ $err }}
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @else
    <div class="alert alert-success small">
        <i class="bi bi-check-circle-fill me-1"></i>
        Import completed with no errors.
    </div>
    @endif

    {{-- Warnings (non-fatal: bad certs, duplicate memberships, etc.) --}}
    @if(!empty($warnings))
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-transparent border-bottom pt-3 pb-2">
            <h6 class="fw-semibold mb-0 text-warning-emphasis">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Warnings ({{ count($warnings) }}) — data imported, these rows were skipped
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush" style="max-height:300px;overflow-y:auto">
                @foreach($warnings as $w)
                <div class="list-group-item list-group-item-warning py-1 px-3 small font-monospace">
                    {{ $w }}
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="mt-3 d-flex gap-2">
        <a href="{{ route('federations.index') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-share me-1"></i> View Federations
        </a>
        <a href="{{ route('entities.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-diagram-3 me-1"></i> View Entities
        </a>
    </div>

</div>
@endsection
