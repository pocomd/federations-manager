@extends('layouts.app')

@section('title', 'Import from Jagger')

@section('content')
<div class="container-fluid py-4 px-4" x-data="jaggerImport()">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">Import from Jagger</h1>
            <p class="text-muted small mb-0">
                Migrate federations, entities and related data from a legacy
                <a href="https://github.com/Edugate/Jagger" target="_blank" rel="noopener" class="text-decoration-none">Jagger (ResourceRegistry 3)</a>
                MySQL database.
            </p>
        </div>
        <a href="{{ route('guide.show', 'import') }}" target="_blank"
           class="btn btn-outline-secondary btn-sm text-nowrap">
            <i class="bi bi-table me-1"></i>Field mapping
        </a>
    </div>

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
        @foreach($errors->all() as $err)
            <div>{{ $err }}</div>
        @endforeach
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('import_result') && !session('import_result')['success'])
    @php $failedResult = session('import_result'); @endphp
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="bi bi-x-circle-fill me-1"></i>Import failed — all changes were discarded.</strong>
        <p class="mb-0 mt-1 small">The database was rolled back completely. No records were saved. Fix the errors below and try again.</p>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <div class="card border-danger shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom pt-3 pb-2">
            <h6 class="fw-semibold mb-0 text-danger">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Import errors ({{ count($failedResult['errors']) }})
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush" style="max-height:300px;overflow-y:auto">
                @foreach($failedResult['errors'] as $err)
                <div class="list-group-item list-group-item-danger py-1 px-3 small font-monospace">{{ $err }}</div>
                @endforeach
            </div>
        </div>
    </div>

    @if(!empty($failedResult['warnings']))
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom pt-3 pb-2">
            <h6 class="fw-semibold mb-0 text-warning-emphasis">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Warnings before rollback ({{ count($failedResult['warnings']) }})
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush" style="max-height:200px;overflow-y:auto">
                @foreach($failedResult['warnings'] as $w)
                <div class="list-group-item list-group-item-warning py-1 px-3 small font-monospace">{{ $w }}</div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
    @endif

    <div class="row g-4">

        {{-- ── Left column: form ─────────────────────────────────────────────── --}}
        <div class="col-lg-6">

            {{-- Connection --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">
                        <i class="bi bi-database me-1 text-primary"></i>
                        Jagger Database Connection
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-semibold mb-1">Host</label>
                            <input type="text" class="form-control form-control-sm font-monospace"
                                   x-model="creds.host"
                                   placeholder="127.0.0.1"
                                   value="{{ old('host') }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold mb-1">Port</label>
                            <input type="number" class="form-control form-control-sm font-monospace"
                                   x-model="creds.port"
                                   placeholder="3306" min="1" max="65535"
                                   value="{{ old('port', '3306') }}">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1">Database name</label>
                        <input type="text" class="form-control form-control-sm font-monospace"
                               x-model="creds.database"
                               placeholder="jagger"
                               value="{{ old('database') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1">Username</label>
                        <input type="text" class="form-control form-control-sm font-monospace"
                               x-model="creds.username"
                               placeholder="root"
                               value="{{ old('username') }}"
                               autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">Password</label>
                        <input type="password" class="form-control form-control-sm"
                               x-model="creds.password"
                               autocomplete="new-password">
                    </div>

                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            @click="testConnection"
                            :disabled="testing">
                        <span x-show="!testing">
                            <i class="bi bi-plug me-1"></i> Test Connection &amp; Preview
                        </span>
                        <span x-show="testing" x-cloak>
                            <span class="spinner-border spinner-border-sm me-1"></span> Connecting…
                        </span>
                    </button>

                    {{-- Connection result badge --}}
                    <span x-show="testResult !== null" x-cloak class="ms-2 small">
                        <span x-show="testResult === 'ok'" class="text-success fw-semibold">
                            <i class="bi bi-check-circle-fill"></i> Connected
                        </span>
                        <span x-show="testResult === 'fail'" class="text-danger fw-semibold">
                            <i class="bi bi-x-circle-fill"></i>
                            <span x-text="testError"></span>
                        </span>
                    </span>
                </div>
            </div>

            {{-- Options --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">
                        <i class="bi bi-sliders me-1 text-primary"></i>
                        Import Options
                    </h6>
                </div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="onlyLocal"
                               x-model="opts.only_local" checked>
                        <label class="form-check-label small" for="onlyLocal">
                            <strong>Local entities only</strong>
                            <span class="text-muted d-block">Skip providers marked as external / remote (is_local = 0)</span>
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="skipExisting"
                               x-model="opts.skip_existing" checked>
                        <label class="form-check-label small" for="skipExisting">
                            <strong>Skip existing records</strong>
                            <span class="text-muted d-block">Entities matched by entity_id and federations matched by URN are skipped rather than overwritten</span>
                        </label>
                    </div>
                    <div class="form-check border-top pt-2 mt-1">
                        <input class="form-check-input border-danger" type="checkbox" id="clearFirst"
                               x-model="opts.clear_first"
                               @change="if (opts.clear_first) { $nextTick(() => document.getElementById('clearConfirmModal').showModal()) }">
                        <label class="form-check-label small" for="clearFirst">
                            <strong class="text-danger">Clear existing data before import</strong>
                            <span class="text-muted d-block">Deletes all federations and all entities tagged <code>source = imported</code> (and their certificates, contacts, endpoints, memberships) before running. Use to start fresh.</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- What will be imported info box --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        What gets imported
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="small mb-0 ps-3">
                        <li><strong>Federations</strong> — name, URN (→ URI), description, active status</li>
                        <li><strong>Entities</strong> — entity ID, type (IDP/SP), status, scope, NameID formats, signing preferences, registration authority; display names, org names, descriptions and URLs in all languages</li>
                        <li><strong>Memberships</strong> — federation ↔ entity links with join-state mapped to active / suspended / rejected</li>
                        <li><strong>Certificates</strong> — PEM parsed from bare base64; subject, issuer, validity, key algorithm, SHA-256 fingerprint</li>
                        <li><strong>Contacts</strong> — technical, administrative, support, billing contacts per entity (with name and email)</li>
                        <li><strong>Endpoints</strong> — SSO, ACS, SLO, Artifact endpoints with binding, location, index</li>
                        <li><strong>Attribute definitions</strong> — name, full name, OID, URN (skips duplicates)</li>
                        <li><strong>Attribute requirements</strong> — per-SP requested attributes and per-federation required attributes</li>
                    </ul>
                    <p class="small text-muted mt-2 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Entities of type <em>BOTH</em> are imported as <code>idp</code>.
                        Users, ACL roles and notification subscriptions are not imported.
                        All imported entities are tagged <code>source = imported</code>.
                    </p>
                </div>
            </div>

        </div>

        {{-- ── Right column: preview + run ───────────────────────────────────── --}}
        <div class="col-lg-6">

            {{-- Preview card (shown after test) --}}
            <div class="card border-0 shadow-sm mb-4" x-show="preview !== null" x-cloak>
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">
                        <i class="bi bi-eye me-1 text-primary"></i>
                        Jagger Source — Row Counts
                    </h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="small ps-3">Federations</td>
                                <td class="small text-end pe-3 fw-semibold" x-text="preview?.federations ?? '—'"></td>
                            </tr>
                            <tr>
                                <td class="small ps-3">Entities (all)</td>
                                <td class="small text-end pe-3 fw-semibold" x-text="preview?.entities ?? '—'"></td>
                            </tr>
                            <tr>
                                <td class="small ps-3 text-muted">— local only</td>
                                <td class="small text-end pe-3 text-muted" x-text="preview?.local_entities ?? '—'"></td>
                            </tr>
                            <tr>
                                <td class="small ps-3">Federation memberships</td>
                                <td class="small text-end pe-3 fw-semibold" x-text="preview?.memberships ?? '—'"></td>
                            </tr>
                            <tr>
                                <td class="small ps-3">Certificates</td>
                                <td class="small text-end pe-3 fw-semibold" x-text="preview?.certificates ?? '—'"></td>
                            </tr>
                            <tr>
                                <td class="small ps-3">Contacts</td>
                                <td class="small text-end pe-3 fw-semibold" x-text="preview?.contacts ?? '—'"></td>
                            </tr>
                            <tr>
                                <td class="small ps-3">Endpoints</td>
                                <td class="small text-end pe-3 fw-semibold" x-text="preview?.endpoints ?? '—'"></td>
                            </tr>
                            <tr>
                                <td class="small ps-3">Attribute definitions</td>
                                <td class="small text-end pe-3 fw-semibold" x-text="preview?.attributes ?? '—'"></td>
                            </tr>
                            <tr>
                                <td class="small ps-3">Attribute requirements</td>
                                <td class="small text-end pe-3 fw-semibold" x-text="preview?.attr_requirements ?? '—'"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Run import form --}}
            <div class="card border-0 shadow-sm" x-show="testResult === 'ok'" x-cloak>
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">
                        <i class="bi bi-play-circle me-1 text-success"></i>
                        Run Import
                    </h6>
                </div>
                <div class="card-body">
                    @if(session('import_result') && !session('import_result')['success'])
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Your credentials are pre-filled from the previous attempt. You can start the import again directly, or re-test the connection first.
                    </div>
                    @endif
                    <p class="small text-muted mb-3">
                        The import runs synchronously. Large Jagger databases may take a minute or two.
                        Do not navigate away during the import.
                    </p>

                    <form method="POST" action="{{ route('import.jagger.run') }}"
                          @submit="running = true">
                        @csrf

                        {{-- Carry credentials from Alpine state as hidden inputs --}}
                        <input type="hidden" name="host"     :value="creds.host">
                        <input type="hidden" name="port"     :value="creds.port">
                        <input type="hidden" name="database" :value="creds.database">
                        <input type="hidden" name="username" :value="creds.username">
                        <input type="hidden" name="password" :value="creds.password">
                        <input type="hidden" name="only_local"    :value="opts.only_local    ? '1' : '0'">
                        <input type="hidden" name="skip_existing" :value="opts.skip_existing ? '1' : '0'">
                        <input type="hidden" name="clear_first"   :value="opts.clear_first   ? '1' : '0'">

                        <button type="submit" class="btn btn-sm"
                                :class="opts.clear_first ? 'btn-danger' : 'btn-success'"
                                :disabled="running">
                            <span x-show="!running">
                                <template x-if="opts.clear_first">
                                    <span><i class="bi bi-trash me-1"></i> Clear &amp; Import</span>
                                </template>
                                <template x-if="!opts.clear_first">
                                    <span><i class="bi bi-cloud-download me-1"></i> Start Import</span>
                                </template>
                            </span>
                            <span x-show="running" x-cloak>
                                <span class="spinner-border spinner-border-sm me-1"></span>
                                Importing — please wait…
                            </span>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Clear-first confirmation modal --}}
<dialog id="clearConfirmModal" style="border:none;border-radius:.5rem;padding:0;box-shadow:0 .5rem 2rem rgba(0,0,0,.25);max-width:460px;width:100%">
    <div class="p-4">
        <h6 class="fw-bold text-danger mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>Confirm: Clear existing data</h6>
        <p class="small mb-1">This will permanently delete before importing:</p>
        <ul class="small mb-3">
            <li>All <strong>federations</strong> and their attribute requirements</li>
            <li>All <strong>entities</strong> tagged <code>source = imported</code></li>
            <li>Their certificates, contacts, endpoints, UI info, attributes, and memberships</li>
        </ul>
        <p class="small text-danger mb-3"><strong>This cannot be undone.</strong> Manually created entities (source ≠ imported) are preserved.</p>
        <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('clearConfirmModal').close(); document.getElementById('clearFirst').checked = false;" x-on:click="opts.clear_first = false">Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('clearConfirmModal').close()">Yes, clear before import</button>
        </div>
    </div>
</dialog>

@push('scripts')
<script>
function jaggerImport() {
    return {
        creds: {
            host:     @json(old('host', '')),
            port:     @json(old('port', '3306')),
            database: @json(old('database', '')),
            username: @json(old('username', '')),
            password: @json(old('password', '')),
        },
        opts: {
            only_local:    true,
            skip_existing: true,
            clear_first:   false,
        },
        testing:    false,
        testResult: @json(session()->has('import_result') ? 'ok' : null),
        testError:  '',
        preview:    null,
        running:    false,

        testConnection() {
            this.testing    = true;
            this.testResult = null;
            this.preview    = null;

            const data = new FormData();
            data.append('_token',   '{{ csrf_token() }}');
            data.append('host',     this.creds.host);
            data.append('port',     this.creds.port);
            data.append('database', this.creds.database);
            data.append('username', this.creds.username);
            data.append('password', this.creds.password);

            fetch('{{ route('import.jagger.test') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: data,
            })
                .then(r => r.json())
                .then(json => {
                    this.testing = false;
                    if (json.ok) {
                        this.testResult = 'ok';
                        this.preview    = json.preview;
                    } else {
                        const msg = json.message
                            ?? Object.values(json.errors ?? {}).flat().join(' ')
                            ?? 'Connection failed';
                        this.testResult = 'fail';
                        this.testError  = msg;
                    }
                })
                .catch(err => {
                    this.testing    = false;
                    this.testResult = 'fail';
                    this.testError  = err.toString();
                });
        },
    };
}
</script>
@endpush

@endsection
