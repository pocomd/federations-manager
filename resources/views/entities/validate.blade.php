@extends('layouts.app')

@section('title', 'Validation — ' . ($entity->getDisplayName() ?? $entity->entity_id))

@section('content')
<div class="container-fluid py-4 px-4">

    {{-- Header ──────────────────────────────────────────────────────────── --}}
    <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('entities.index') }}">Entities</a></li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('entities.show', $entity) }}">
                            {{ $entity->getDisplayName() ?? $entity->entity_id }}
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Validation</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-1">
                {{ $entity->getDisplayName() ?? $entity->entity_id }}
                <span class="badge bg-secondary ms-1 fw-normal fs-6">
                    {{ strtoupper($entity->type) }}
                </span>
            </h1>
            <p class="text-muted mb-1 small font-monospace">{{ $entity->entity_id }}</p>

            {{-- Overall result badge --}}
            @php
                $errors        = $summary['errors']   ?? 0;
                $warnings      = $summary['warnings']  ?? 0;
                $passed        = $result['passed']     ?? false;
                $techContacts  = $entity->contacts()->where('type', 'technical')->get();
                $hasIssues     = $errors > 0 || $warnings > 0;
            @endphp
            @if ($passed && $warnings === 0)
                <span class="badge bg-success fs-6 px-3 py-2">
                    <i class="bi bi-check-circle me-1"></i> All checks passed
                </span>
            @elseif ($passed)
                <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i> Passed with warnings
                </span>
            @else
                <span class="badge bg-danger fs-6 px-3 py-2">
                    <i class="bi bi-x-circle me-1"></i> Validation failed
                </span>
            @endif

            <div class="text-muted small mt-2">
                <i class="bi bi-clock me-1"></i>
                Checked at {{ \Illuminate\Support\Carbon::parse($summary['checked_at'] ?? now())->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}
            </div>
        </div>
        <div class="d-flex gap-2 shrink-0">
            <a href="{{ route('entities.validate', [$entity, 'force' => 1]) }}"
               class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i> Re-validate
            </a>
            <button type="button" class="btn btn-outline-dark btn-sm" id="btn-download-json"
                    data-url="{{ route('entities.validate', $entity) }}">
                <i class="bi bi-download me-1"></i> Download JSON
            </button>
            @if($hasIssues && $techContacts->isNotEmpty())
            @can('update', $entity)
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-preview-compliance"
                    data-url="{{ route('entities.compliance-notify.preview', $entity) }}"
                    data-bs-toggle="modal" data-bs-target="#compliancePreviewModal">
                <i class="bi bi-eye me-1"></i> Preview notification
            </button>
            @php
                $popoverContent = $techContacts->map(function ($c) {
                    $name  = trim(($c->given_name ?? '') . ' ' . ($c->sur_name ?? ''));
                    $email = $c->email ?? '';
                    $line  = $name ? '<strong>' . e($name) . '</strong><br>' . e($email) : e($email);
                    return '<div class="small">' . $line . '</div>';
                })->implode('');
            @endphp
            <form method="POST" action="{{ route('entities.compliance-notify', $entity) }}" id="compliance-send-form">
                @csrf
                <button type="submit"
                        class="btn btn-warning btn-sm notify-contact-info"
                        data-bs-toggle="popover"
                        data-bs-trigger="hover focus"
                        data-bs-placement="bottom"
                        data-bs-html="true"
                        data-bs-title="Will be notified"
                        data-bs-content="{!! e($popoverContent) !!}">
                    <i class="bi bi-send me-1"></i> Notify contacts <i class="bi bi-info-circle ms-1" style="font-size:.85rem"></i>
                </button>
            </form>
            @endcan
            @endif
            @can('entity.edit')
            <a href="{{ route('entities.edit', $entity) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit Entity
            </a>
            @endcan
            <a href="{{ route('entities.show', $entity) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Entity
            </a>
        </div>
    </div>

    {{-- Summary cards ────────────────────────────────────────────────────── --}}
    @php
        $totalChecks   = $summary['total']          ?? count($checks);
        $passedCount   = $summary['passed']          ?? 0;
        $errorCount    = $summary['errors']          ?? 0;
        $warningCount  = $summary['warnings']        ?? 0;
        $naCount       = $summary['not_applicable']  ?? 0;
    @endphp
    <div class="row g-3 mb-4">
        <div class="col-6 col-md col-lg">
            <div class="card border shadow-none text-center">
                <div class="card-body py-3">
                    <div class="h2 fw-bold text-primary mb-0">{{ $totalChecks }}</div>
                    <div class="small text-muted">Total Checks</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md col-lg">
            <div class="card border shadow-none text-center">
                <div class="card-body py-3">
                    <div class="h2 fw-bold text-success mb-0">{{ $passedCount }}</div>
                    <div class="small text-muted">Passed</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md col-lg">
            <div class="card border shadow-none text-center">
                <div class="card-body py-3">
                    <div class="h2 fw-bold {{ $errorCount > 0 ? 'text-danger' : 'text-success' }} mb-0">
                        {{ $errorCount }}
                    </div>
                    <div class="small text-muted">Errors</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md col-lg">
            <div class="card border shadow-none text-center">
                <div class="card-body py-3">
                    <div class="h2 fw-bold {{ $warningCount > 0 ? 'text-warning' : 'text-success' }} mb-0">
                        {{ $warningCount }}
                    </div>
                    <div class="small text-muted">Warnings</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md col-lg">
            <div class="card border shadow-none text-center">
                <div class="card-body py-3">
                    <div class="h2 fw-bold text-secondary mb-0">{{ $naCount }}</div>
                    <div class="small text-muted">Not Applicable</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Partition checks into 4 sections ─────────────────────────────────── --}}
    @php
        $checksFail = collect($checks)->filter(fn($c) => ($c['status'] ?? '') === 'fail')->values();
        $checksWarn = collect($checks)->filter(fn($c) => ($c['status'] ?? '') === 'warning')->values();
        $checksPass = collect($checks)->filter(fn($c) => ($c['status'] ?? '') === 'pass')->values();
        $checksNa   = collect($checks)->filter(fn($c) => ($c['status'] ?? '') === 'not_applicable')->values();

        $badgeClass = fn(string $id) => match(substr($id, 0, 1)) {
            'S' => 'bg-secondary',
            'C' => 'bg-dark',
            'R' => 'bg-primary',
            'X' => 'bg-info text-dark',
            default => 'bg-secondary',
        };
    @endphp

    @php
        $sections = [
            ['label' => 'Errors', 'icon' => 'bi-x-circle-fill text-danger', 'header' => 'table-danger', 'items' => $checksFail, 'show' => true],
            ['label' => 'Warnings', 'icon' => 'bi-exclamation-triangle-fill text-warning', 'header' => 'table-warning', 'items' => $checksWarn, 'show' => true],
            ['label' => 'Passed', 'icon' => 'bi-check-circle-fill text-success', 'header' => 'table-success', 'items' => $checksPass, 'show' => $checksPass->isNotEmpty()],
            ['label' => 'Not Applicable', 'icon' => 'bi-dash-circle text-secondary', 'header' => 'table-light', 'items' => $checksNa, 'show' => $checksNa->isNotEmpty()],
        ];
    @endphp

    @foreach($sections as $section)
        @if($section['show'] && $section['items']->isNotEmpty())
        <div class="card border shadow-none mb-3">
            <div class="card-header {{ $section['header'] }} py-2">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi {{ $section['icon'] }} me-2"></i>
                    {{ $section['label'] }}
                    <span class="badge bg-secondary ms-2">{{ $section['items']->count() }}</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width:6rem">Rule</th>
                                <th>Message</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($section['items'] as $check)
                            @php
                                $id   = $check['id'] ?? ($check['code'] ?? '?');
                                $desc = $descriptions[$id] ?? null;
                            @endphp
                            <tr>
                                <td class="ps-3 text-nowrap">
                                    <span class="badge {{ $badgeClass($id) }}">{{ $id }}</span>
                                    @if($desc)
                                    @php
                                        $popContent = e($desc['description']);
                                        if ($desc['spec_url']) {
                                            $popContent .= '<br><a href="' . e($desc['spec_url']) . '" target="_blank" rel="noopener" class="small">Spec &nearr;</a>';
                                        }
                                    @endphp
                                    <button type="button"
                                            class="btn btn-link btn-sm p-0 ms-1 text-muted align-baseline rule-info"
                                            data-bs-toggle="popover"
                                            data-bs-trigger="hover focus"
                                            data-bs-placement="right"
                                            data-bs-html="true"
                                            data-bs-title="{{ e($desc['name']) }}"
                                            data-bs-content="{{ $popContent }}"
                                            aria-label="Rule description">
                                        <i class="bi bi-info-circle" style="font-size:.85rem"></i>
                                    </button>
                                    @endif
                                </td>
                                <td>{{ $check['message'] ?? '' }}</td>
                                <td class="text-muted small">{{ $check['detail'] ?? '' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    @endforeach

    @if(empty($checks))
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>No checks were run.
        </div>
    @endif

</div>

{{-- Compliance notification preview modal --}}
@if($hasIssues && $techContacts->isNotEmpty())
@can('update', $entity)
<div class="modal fade" id="compliancePreviewModal" tabindex="-1" aria-labelledby="compliancePreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="compliancePreviewLabel">
                    <i class="bi bi-envelope me-2"></i>Compliance notification preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cp-loading" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                    Loading preview…
                </div>
                <div id="cp-content" style="display:none">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Subject</label>
                        <div class="border rounded px-3 py-2 bg-light small font-monospace" id="cp-subject"></div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold small text-muted text-uppercase">Body</label>
                        <pre class="border rounded px-3 py-2 bg-light small" style="white-space:pre-wrap;word-break:break-word" id="cp-body"></pre>
                    </div>
                </div>
                <div id="cp-error" class="alert alert-danger" style="display:none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endcan
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
        new bootstrap.Popover(el, { sanitize: false });
    });
});

document.getElementById('btn-download-json').addEventListener('click', function () {
    const url = this.dataset.url;
    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const a    = document.createElement('a');
            a.href     = URL.createObjectURL(blob);
            a.download = 'validation-{{ $entity->id }}.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        });
});

@if($hasIssues && $techContacts->isNotEmpty())
@can('update', $entity)
// Fetch preview when modal starts opening — no bootstrap global needed
(function () {
    var modal = document.getElementById('compliancePreviewModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function () {
        var url = document.getElementById('btn-preview-compliance').dataset.url;
        document.getElementById('cp-loading').style.display = '';
        document.getElementById('cp-content').style.display = 'none';
        document.getElementById('cp-error').style.display   = 'none';
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) throw new Error(data.error);
                document.getElementById('cp-subject').textContent = data.subject;
                document.getElementById('cp-body').textContent    = data.body;
                document.getElementById('cp-loading').style.display = 'none';
                document.getElementById('cp-content').style.display = '';
            })
            .catch(function (err) {
                document.getElementById('cp-loading').style.display = 'none';
                var errEl = document.getElementById('cp-error');
                errEl.textContent   = 'Failed to load preview: ' + err.message;
                errEl.style.display = '';
            });
    });
})();
@endcan
@endif
</script>
@endpush
