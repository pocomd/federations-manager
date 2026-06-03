@extends('layouts.app')

@section('title', 'System Health')

@section('content')

@php
    $displayNames = [
        'database'    => 'Database',
        'redis'       => 'Redis / Cache',
        'xmlsectool'  => 'XML Signing (xmlsectool)',
        'scheduler'   => 'Scheduler Heartbeat',
        'cert_parser' => 'Certificate Parser',
        'queue'       => 'Queue Worker',
    ];
@endphp

{{-- ── Page header ─────────────────────────────────────────────────────────── --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 mb-1 fw-bold">System Health</h1>
        <p class="text-muted mb-0 small">Live status of application dependencies and background services.</p>
    </div>
    <button id="refresh-btn" class="btn btn-outline-secondary btn-sm" onclick="runChecks()">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
    </button>
</div>

{{-- ── Overall status banner ───────────────────────────────────────────────── --}}
<div id="overall-banner" class="alert alert-secondary d-flex align-items-center mb-4" role="alert">
    <div class="spinner-border spinner-border-sm me-2 text-secondary" role="status" aria-hidden="true"></div>
    <div>
        <strong>Running checks…</strong>
        <span class="text-muted ms-2 small" id="overall-time"></span>
    </div>
</div>

{{-- ── Check cards ─────────────────────────────────────────────────────────── --}}
<div class="row g-3">
    @foreach($checks as $name)
    @php $checkMeta = $meta[$name] ?? null; @endphp

    <div class="col-12 col-lg-6">
        <div class="card h-100" id="card-{{ $name }}">
            <div class="card-body">

                {{-- Header row --}}
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span id="icon-{{ $name }}">
                            <div class="spinner-border spinner-border-sm text-secondary" style="width:1.1rem;height:1.1rem;" role="status" aria-hidden="true"></div>
                        </span>
                        <h6 class="mb-0 fw-semibold">{{ $displayNames[$name] ?? ucfirst(str_replace('_', ' ', $name)) }}</h6>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="badge-{{ $name }}" class="badge bg-secondary rounded-pill">Checking</span>
                        <span id="duration-{{ $name }}" class="text-muted small"></span>
                    </div>
                </div>

                {{-- Result message --}}
                <p id="message-{{ $name }}" class="mb-0 small text-muted">Waiting for result…</p>

                @if($checkMeta)
                {{-- Description — always visible --}}
                <p class="mb-0 mt-2 small text-muted">{{ $checkMeta['description'] }}</p>

                {{-- Remediation steps — shown only on fail/warn --}}
                @if(!empty($checkMeta['actions']))
                <div id="actions-{{ $name }}" class="mt-3 pt-3 border-top" style="display:none!important">
                    <p class="mb-2 small fw-semibold" id="actions-label-{{ $name }}">
                        <i class="bi bi-wrench me-1"></i>Suggested actions
                    </p>
                    <ol class="mb-0 ps-3 small text-muted">
                        @foreach($checkMeta['actions'] as $action)
                        <li class="mb-3">
                            @if(is_string($action))
                                {!! $action !!}
                            @else
                                {{ $action['text'] }}
                                @foreach($action['cmds'] as $cmd)
                                <div class="position-relative mt-1">
                                    <pre class="bg-dark text-light rounded px-3 py-2 mb-0" style="font-size:.78rem;white-space:pre-wrap;word-break:break-all;padding-right:2.5rem!important"><code>{{ $cmd }}</code></pre>
                                    <button class="copy-cmd-btn btn btn-sm position-absolute top-0 end-0 m-1 text-white" title="Copy to clipboard" style="background:rgba(255,255,255,.15);border:none;line-height:1">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                @endforeach
                            @endif
                        </li>
                        @endforeach
                    </ol>
                </div>
                @endif
                @endif

            </div>
        </div>
    </div>
    @endforeach
</div>

@endsection

@push('scripts')
<script>
(function () {
    const checks  = @json($checks);
    const baseUrl = '{{ route('health.ui.check', ['name' => '__name__']) }}'.replace('__name__', '');

    function applyResult(name, data) {
        const status = data.status; // 'ok', 'warn', 'fail'

        const badgeClass = { ok: 'bg-success', warn: 'bg-warning text-dark', fail: 'bg-danger' }[status] ?? 'bg-secondary';
        const badgeLabel = { ok: 'OK', warn: 'Warn', fail: 'Fail' }[status] ?? status;
        const iconHtml   = {
            ok:   '<i class="bi bi-check-circle-fill text-success fs-5"></i>',
            warn: '<i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>',
            fail: '<i class="bi bi-x-circle-fill text-danger fs-5"></i>',
        }[status] ?? '';
        const msgClass = { ok: 'text-muted', warn: 'text-warning-emphasis', fail: 'text-danger' }[status] ?? 'text-muted';

        document.getElementById('badge-' + name).className    = 'badge ' + badgeClass + ' rounded-pill';
        document.getElementById('badge-' + name).textContent  = badgeLabel;
        document.getElementById('icon-' + name).innerHTML     = iconHtml;
        document.getElementById('duration-' + name).textContent = data.duration_ms + 'ms';

        const msgEl = document.getElementById('message-' + name);
        msgEl.textContent  = data.message;
        msgEl.className    = 'mb-0 small ' + msgClass;

        const card = document.getElementById('card-' + name);
        card.classList.remove('border-danger', 'border-warning');
        if (status === 'fail') card.classList.add('border-danger');
        if (status === 'warn') card.classList.add('border-warning');

        if (status === 'fail' || status === 'warn') {
            const actions    = document.getElementById('actions-' + name);
            const actionsLbl = document.getElementById('actions-label-' + name);
            if (actions) {
                actions.style.cssText = '';
                if (actionsLbl) {
                    actionsLbl.className = 'mb-2 small fw-semibold text-' + (status === 'fail' ? 'danger' : 'warning-emphasis');
                }
            }
        }
    }

    function updateOverall(results) {
        const banner  = document.getElementById('overall-banner');
        const hasFail = results.some(r => r.status === 'fail');
        const hasWarn = results.some(r => r.status === 'warn');

        let alertClass, icon, label;
        if (hasFail) {
            alertClass = 'alert-danger';
            icon       = '<i class="bi bi-x-circle-fill me-2 fs-5"></i>';
            label      = 'One or more checks failed';
        } else if (hasWarn) {
            alertClass = 'alert-warning';
            icon       = '<i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>';
            label      = 'Some checks need attention';
        } else {
            alertClass = 'alert-success';
            icon       = '<i class="bi bi-check-circle-fill me-2 fs-5"></i>';
            label      = 'All systems operational';
        }

        banner.className = 'alert ' + alertClass + ' d-flex align-items-center mb-4';
        banner.innerHTML = icon + '<div><strong>' + label + '</strong>'
            + '<span class="text-muted ms-2 small">Checked at ' + new Date().toLocaleTimeString() + '</span></div>';
    }

    window.runChecks = function () {
        const btn = document.getElementById('refresh-btn');
        btn.disabled = true;

        // Reset to pending state
        const banner = document.getElementById('overall-banner');
        banner.className = 'alert alert-secondary d-flex align-items-center mb-4';
        banner.innerHTML = '<div class="spinner-border spinner-border-sm me-2 text-secondary" role="status" aria-hidden="true"></div>'
            + '<div><strong>Running checks…</strong></div>';

        checks.forEach(function (name) {
            document.getElementById('badge-' + name).className   = 'badge bg-secondary rounded-pill';
            document.getElementById('badge-' + name).textContent = 'Checking';
            document.getElementById('icon-' + name).innerHTML    =
                '<div class="spinner-border spinner-border-sm text-secondary" style="width:1.1rem;height:1.1rem;" role="status" aria-hidden="true"></div>';
            document.getElementById('message-' + name).textContent = 'Waiting for result…';
            document.getElementById('message-' + name).className    = 'mb-0 small text-muted';
            document.getElementById('duration-' + name).textContent = '';
            const card = document.getElementById('card-' + name);
            card.classList.remove('border-danger', 'border-warning');
            const actions = document.getElementById('actions-' + name);
            if (actions) actions.style.cssText = 'display:none!important';
        });

        const promises = checks.map(function (name) {
            return fetch(baseUrl + name, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) { applyResult(name, data); return data; });
        });

        Promise.all(promises).then(function (results) {
            updateOverall(results);
            btn.disabled = false;
        }).catch(function () {
            btn.disabled = false;
        });
    };

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        // Fallback for HTTP / older browsers
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        return Promise.resolve();
    }

    function initCopyButtons() {
        document.querySelectorAll('.copy-cmd-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const code = btn.closest('div').querySelector('code');
                copyToClipboard(code.textContent.trim()).then(function () {
                    btn.innerHTML = '<i class="bi bi-clipboard-check text-success"></i>';
                    setTimeout(function () {
                        btn.innerHTML = '<i class="bi bi-clipboard"></i>';
                    }, 1500);
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        runChecks();
        initCopyButtons();
    });
})();
</script>
@endpush
