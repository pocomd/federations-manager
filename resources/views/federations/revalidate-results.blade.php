@extends('layouts.app')

@section('title', 'Compliance Re-check — ' . $federation->name)

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('federations.index') }}">Federations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('federations.show', $federation) }}#tab-rules">{{ $federation->name }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Compliance Re-check</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-shield-exclamation me-2 text-danger"></i>Compliance Re-check Results
        </h1>
        <p class="text-muted small mb-0">{{ $federation->name }}</p>
    </div>

    {{-- Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-auto">
            <div class="card border-0 bg-light px-3 py-2 text-center" style="min-width:110px;">
                <div class="fs-4 fw-bold">{{ $entities->count() }}</div>
                <div class="small text-muted">Checked</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card border-0 bg-success-subtle px-3 py-2 text-center" style="min-width:110px;">
                <div class="fs-4 fw-bold text-success">{{ $entities->count() - count($failures) }}</div>
                <div class="small text-muted">Passed</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card border-0 bg-danger-subtle px-3 py-2 text-center" style="min-width:110px;">
                <div class="fs-4 fw-bold text-danger">{{ count($failures) }}</div>
                <div class="small text-muted">Failed</div>
            </div>
        </div>
    </div>

    {{-- Metadata note --}}
    <div class="alert alert-warning small mb-4">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Note:</strong> Failing entities are still included in signed metadata.
        Compliance checks are advisory — only suspending or removing an entity from the federation excludes it from the metadata feed.
        Contact the entity operators below to resolve the issues.
    </div>

    {{-- Notification toolbar --}}
    @can('update', $federation)
    <div class="d-flex align-items-center gap-2 mb-3" id="notify-toolbar">
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-select-entities">
            <i class="bi bi-envelope-check me-1"></i>Select entities to notify
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-preview-template"
                data-bs-toggle="modal" data-bs-target="#previewModal"
                data-preview-url="" style="display:none">
            <i class="bi bi-eye me-1"></i>Preview template
        </button>
        <button type="button" class="btn btn-sm btn-primary" id="btn-send-notifications" style="display:none" disabled>
            <i class="bi bi-send me-1"></i>Send notifications
        </button>
        <span class="text-muted small ms-2" id="selection-count" style="display:none">0 selected</span>
    </div>
    @endcan

    {{-- Failing entities --}}
    <form method="POST" action="{{ route('federations.compliance-notify', $federation) }}" id="notify-form">
        @csrf
        @foreach($failures as $item)
        @php
            /** @var \App\Models\Entity $entity */
            $entity   = $item['entity'];
            $errors   = $item['errors'];
            $warnings = $item['warnings'];
            $contacts = $entity->contacts->whereIn('type', ['technical', 'support'])->values();
        @endphp
        <div class="card mb-3 border-danger-subtle">
            <div class="card-header d-flex justify-content-between align-items-start py-2 bg-danger-subtle">
                <div class="d-flex align-items-center gap-2">
                    <div class="entity-checkbox-wrapper" style="display:none">
                        <input type="checkbox"
                               class="form-check-input entity-notify-checkbox"
                               name="entity_ids[]"
                               value="{{ $entity->id }}"
                               id="check-{{ $entity->id }}">
                    </div>
                    <div>
                        <code class="fw-bold">{{ $entity->entity_id }}</code>
                        @if($entity->getDisplayName())
                            <span class="text-muted small ms-2">{{ $entity->getDisplayName() }}</span>
                        @endif
                    </div>
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    @if(count($errors))
                        <span class="badge bg-danger">{{ count($errors) }} error{{ count($errors) !== 1 ? 's' : '' }}</span>
                    @endif
                    @if(count($warnings))
                        <span class="badge bg-warning text-dark">{{ count($warnings) }} warning{{ count($warnings) !== 1 ? 's' : '' }}</span>
                    @endif
                    <a href="{{ route('entities.show', $entity) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row g-3">

                    {{-- Errors & warnings --}}
                    <div class="col-md-7">
                        @if(count($errors))
                        <ul class="list-unstyled mb-2">
                            @foreach($errors as $msg)
                            <li class="small"><i class="bi bi-x-circle-fill text-danger me-1"></i>{{ $msg }}</li>
                            @endforeach
                        </ul>
                        @endif
                        @if(count($warnings))
                        <ul class="list-unstyled mb-0">
                            @foreach($warnings as $msg)
                            <li class="small"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>{{ $msg }}</li>
                            @endforeach
                        </ul>
                        @endif
                    </div>

                    {{-- Contacts --}}
                    <div class="col-md-5">
                        @if($contacts->isNotEmpty())
                        <div class="small fw-semibold text-muted mb-1">Contacts</div>
                        @foreach($contacts as $contact)
                        <div class="small mb-1">
                            <span class="badge bg-secondary me-1">{{ $contact->type }}</span>
                            @if($contact->given_name || $contact->sur_name)
                                {{ trim($contact->given_name . ' ' . $contact->sur_name) }}
                            @endif
                            @if($contact->email)
                                <a href="mailto:{{ $contact->email }}?subject={{ urlencode('Compliance issue: ' . $entity->entity_id) }}&body={{ urlencode('Dear operator,\n\nDuring a compliance re-check of the ' . $federation->name . ' federation, your entity ' . $entity->entity_id . ' failed the following checks:\n\n' . implode('\n', $errors) . (count($warnings) ? '\n\nWarnings:\n' . implode('\n', $warnings) : '') . '\n\nPlease review and resolve these issues.\n\nRegards,\n' . $federation->name . ' team') }}"
                                   class="text-decoration-none">{{ $contact->email }}</a>
                            @endif
                        </div>
                        @endforeach
                        @else
                        <div class="small text-muted"><i class="bi bi-person-x me-1"></i>No technical/support contacts on record.</div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
        @endforeach
    </form>

    <div class="mt-3">
        <a href="{{ route('federations.show', $federation) }}#tab-rules" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to {{ $federation->name }}
        </a>
    </div>

</div>

{{-- Preview modal --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">
                    <i class="bi bi-envelope me-2"></i>Compliance notification preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="preview-loading" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                    Loading preview…
                </div>
                <div id="preview-content" style="display:none">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted text-uppercase letter-spacing-1">Subject</label>
                        <div class="border rounded px-3 py-2 bg-light small font-monospace" id="preview-subject"></div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold small text-muted text-uppercase letter-spacing-1">Body</label>
                        <pre class="border rounded px-3 py-2 bg-light small" style="white-space:pre-wrap;word-break:break-word" id="preview-body"></pre>
                    </div>
                </div>
                <div id="preview-error" class="alert alert-danger" style="display:none"></div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto" id="preview-entity-hint"></small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@can('update', $federation)
<script>
(function () {
    var previewUrls = {
        @foreach($failures as $item)
        '{{ $item['entity']->id }}': '{{ route('entities.compliance-notify.preview', $item['entity']) }}',
        @endforeach
    };

    var selectBtn  = document.getElementById('btn-select-entities');
    var previewBtn = document.getElementById('btn-preview-template');
    var sendBtn    = document.getElementById('btn-send-notifications');
    var countLabel = document.getElementById('selection-count');
    var selecting  = false;

    selectBtn.addEventListener('click', function () {
        selecting = !selecting;
        document.querySelectorAll('.entity-checkbox-wrapper').forEach(function (el) {
            el.style.display = selecting ? 'block' : 'none';
        });
        if (!selecting) {
            document.querySelectorAll('.entity-notify-checkbox').forEach(function (cb) { cb.checked = false; });
        }
        previewBtn.style.display  = selecting ? '' : 'none';
        sendBtn.style.display     = selecting ? '' : 'none';
        countLabel.style.display  = selecting ? '' : 'none';
        sendBtn.disabled          = true;
        countLabel.textContent    = '0 selected';
        selectBtn.innerHTML       = selecting
            ? '<i class="bi bi-x-circle me-1"></i>Cancel selection'
            : '<i class="bi bi-envelope-check me-1"></i>Select entities to notify';
    });

    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('entity-notify-checkbox')) return;
        var checked = document.querySelectorAll('.entity-notify-checkbox:checked').length;
        sendBtn.disabled       = checked === 0;
        countLabel.textContent = checked + ' selected';
        // keep preview URL pointed at first checked entity
        if (checked > 0) {
            var firstChecked = document.querySelector('.entity-notify-checkbox:checked');
            previewBtn.dataset.previewUrl = previewUrls[firstChecked.value] || '';
        }
    });

    // Seed preview URL with first failure so it works before any checkbox is ticked
    var firstId = Object.keys(previewUrls)[0];
    if (firstId) previewBtn.dataset.previewUrl = previewUrls[firstId];

    // Fetch preview when modal starts opening — no bootstrap global needed
    document.getElementById('previewModal').addEventListener('show.bs.modal', function () {
        var url = previewBtn.dataset.previewUrl;
        document.getElementById('preview-loading').style.display  = '';
        document.getElementById('preview-content').style.display  = 'none';
        document.getElementById('preview-error').style.display    = 'none';
        document.getElementById('preview-entity-hint').textContent = '';
        if (!url) return;
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) throw new Error(data.error);
                document.getElementById('preview-subject').textContent = data.subject;
                document.getElementById('preview-body').textContent    = data.body;
                document.getElementById('preview-entity-hint').textContent =
                    'Preview for first selected entity';
                document.getElementById('preview-loading').style.display = 'none';
                document.getElementById('preview-content').style.display = '';
            })
            .catch(function (err) {
                document.getElementById('preview-loading').style.display = 'none';
                var errEl = document.getElementById('preview-error');
                errEl.textContent   = 'Failed to load preview: ' + err.message;
                errEl.style.display = '';
            });
    });

    sendBtn.addEventListener('click', function () {
        if (document.querySelectorAll('.entity-notify-checkbox:checked').length === 0) return;
        document.getElementById('notify-form').submit();
    });
})();
</script>
@endcan
@endpush
