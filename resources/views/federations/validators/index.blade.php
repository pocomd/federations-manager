@extends('layouts.app')

@section('title', 'Validators — ' . $federation->name . ' — Federation Registry')

@section('content')
<div x-data="{
    open: false,
    validatorId: null,
    validatorName: '',
    entityId: '',
    running: false,
    result: null,
    openTestModal(id, name) {
        this.validatorId = id;
        this.validatorName = name;
        this.entityId = '';
        this.result = null;
        this.open = true;
    },
    async run() {
        if (!this.entityId) return;
        this.running = true;
        this.result = null;
        try {
            const url = '{{ url('federations/' . $federation->id . '/validators') }}/' + this.validatorId + '/run';
            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ entity_id: this.entityId })
            });
            this.result = await resp.json();
        } catch(e) {
            this.result = { status: 'unreachable', message: e.message };
        }
        this.running = false;
    }
}"
@php $autoTest = session('auto_test_validator'); @endphp
x-init="@if(session('auto_test_validator')) $nextTick(() => openTestModal(@js(session('auto_test_validator')['id']), @js(session('auto_test_validator')['name']))) @endif">

<div class="container-fluid py-4 px-4">

    {{-- Header --}}
    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federations.index') }}">Federations</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('federations.show', $federation) }}">{{ $federation->name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Validators</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-0">
                <i class="bi bi-shield-check me-2"></i>Validators
            </h1>
            <p class="text-muted small mb-0">External metadata validators for {{ $federation->name }}</p>
        </div>
        @can('federation.edit')
        <a href="{{ route('federations.validators.create', $federation) }}"
           class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Validator
        </a>
        @endcan
    </div>

    {{-- Lifecycle info --}}
    <div class="alert alert-info small mb-4" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i> How validators work</div>
        <ul class="mb-0 ps-3">
            <li><strong>Manual test</strong> — click the <i class="bi bi-play-circle"></i> button to run a validator against any federation entity on demand.</li>
            <li><strong>Run on Registration</strong> — when enabled on a validator, it runs automatically whenever an entity is registered, updated, or approved. The entity is never blocked by the result.</li>
            <li><strong>Mandatory</strong> — if a mandatory validator reports an error or critical result, a notification is sent to all federation managers. Results are also recorded in the audit log.</li>
        </ul>
    </div>

    {{-- Table --}}
    @if($validators->isEmpty())
        <div class="alert alert-secondary">
            <i class="bi bi-shield-check me-2"></i>
            No validators configured yet. Add one to start running external metadata checks.
        </div>
    @else
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Name</th>
                        <th>URL</th>
                        <th>Method</th>
                        <th>On Registration</th>
                        <th>Mandatory</th>
                        <th>Active</th>
                        <th class="text-center pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($validators as $v)
                    <tr class="{{ $v->enabled ? '' : 'opacity-50' }}">
                        <td class="ps-3 fw-semibold">{{ $v->name }}</td>
                        <td>
                            <span class="text-truncate d-inline-block small font-monospace"
                                  style="max-width:260px;" title="{{ $v->url }}">
                                {{ $v->url }}
                            </span>
                        </td>
                        <td><span class="badge bg-secondary">{{ $v->http_method }}</span></td>
                        <td>
                            @if($v->enabled_on_registration)
                                <span class="badge bg-info text-dark">Yes</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($v->mandatory)
                                <span class="badge bg-warning text-dark">Yes</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($v->enabled)
                                <span class="badge bg-success">ON</span>
                            @else
                                <span class="badge bg-secondary">OFF</span>
                            @endif
                        </td>
                        <td class="text-center pe-3">
                            <div>
                                <button class="btn btn-sm btn-outline-info"
                                        @click="openTestModal(@js($v->id), @js($v->name))"
                                        title="Test">
                                    <i class="bi bi-play-circle"></i>
                                </button>
                                <a href="{{ route('federations.validators.edit', [$federation, $v]) }}"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST"
                                      action="{{ route('federations.validators.destroy', [$federation, $v]) }}"
                                      style="display:contents"
                                      onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.federation.validator_del_title, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Info box --}}
    <div class="card mt-4">
        <div class="card-body small text-muted">
            <strong>Validators are compatible with Jagger format.</strong>
            Expected XML response:
            <pre class="bg-light p-3 rounded small mt-2 mb-0">&lt;?xml version="1.0"?&gt;
&lt;validation&gt;
  &lt;returncode&gt;0&lt;/returncode&gt;
  &lt;message&gt;Validation passed&lt;/message&gt;
&lt;/validation&gt;</pre>
            Return codes: <code>0</code> = success, <code>1</code> = warning,
            <code>2</code> = error, <code>3</code> = critical.
        </div>
    </div>

</div>

{{-- Test modal (Alpine) --}}
<div x-show="open" x-cloak
   class="modal fade show d-block"
   style="background:rgba(0,0,0,.5);"
   @keydown.escape.window="open = false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-play-circle me-2"></i>
                    Test Validator: <span x-text="validatorName"></span>
                </h5>
                <button type="button" class="btn-close" @click="open = false"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select entity to validate</label>
                    <select class="form-select" x-model="entityId">
                        <option value="">— Select entity —</option>
                        @foreach($federation->entities()->with('uiInfo')->get() as $ent)
                        <option value="{{ $ent->id }}">
                            [{{ strtoupper($ent->type) }}] {{ $ent->entity_id }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary btn-sm"
                        @click="run()"
                        :disabled="!entityId || running">
                    <span x-show="running" class="spinner-border spinner-border-sm me-1"></span>
                    Run Validator
                </button>

                <div x-show="result" class="mt-3">
                    <div :class="{
                        'alert-success':  result?.status === 'success',
                        'alert-warning':  result?.status === 'warning',
                        'alert-danger':   ['error', 'critical', 'http_error'].includes(result?.status),
                        'alert-secondary':['unreachable', 'timeout', 'ssl_error'].includes(result?.status)
                    }" class="alert mb-0">
                        <strong x-text="result?.status?.toUpperCase().replace('_', ' ')"></strong>:
                        <span x-text="result?.message"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" @click="open = false">Close</button>
            </div>
        </div>
    </div>
</div>

</div>{{-- /x-data --}}
@endsection
