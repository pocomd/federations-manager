@extends('layouts.app')

@section('title', 'User Guide')

@section('content')

@php
$sections = [
    ['key' => 'dashboard',      'icon' => 'bi-house',            'title' => 'Dashboard',             'desc' => 'Overview cards, recent entities, certificate summary, recent audit events.'],
    ['key' => 'entities',       'icon' => 'bi-diagram-3',        'title' => 'Entities',              'desc' => 'Create, import, edit, validate, suspend and delete IdPs, SPs and OIDC clients.'],
    ['key' => 'federations',    'icon' => 'bi-share',            'title' => 'Federations',           'desc' => 'Manage federation membership, metadata generation, policies, validators and contacts.'],
    ['key' => 'certificates',   'icon' => 'bi-key',              'title' => 'Certificates',          'desc' => 'Monitor certificate expiry across all entities by severity.'],
    ['key' => 'metadata',       'icon' => 'bi-file-code',        'title' => 'Metadata',              'desc' => 'Generate and publish signed aggregate SAML metadata XML for federations.'],
    ['key' => 'invitations',    'icon' => 'bi-envelope-check',   'title' => 'Invitations',           'desc' => 'Send, resend, revoke and review co-manager invitation requests.'],
    ['key' => 'notifications',  'icon' => 'bi-bell',             'title' => 'Notifications',         'desc' => 'In-app notification centre and per-user notification preferences.'],
    ['key' => 'statistics',     'icon' => 'bi-bar-chart-line',   'title' => 'Statistics',            'desc' => 'Charts and CSV exports for entity trends, compliance scores and certificate forecasts.'],
    ['key' => 'webhooks',       'icon' => 'bi-broadcast',        'title' => 'Webhooks',              'desc' => 'HTTP event delivery to external systems with signature verification.'],
    ['key' => 'mail-templates', 'icon' => 'bi-envelope-paper',   'title' => 'Mail Templates',        'desc' => 'Reusable email templates with placeholder substitution for federation communications.'],
    ['key' => 'attributes',     'icon' => 'bi-tags',             'title' => 'Attribute Definitions', 'desc' => 'Global library of SAML attributes used for SP requested attributes and IdP ARP.'],
    ['key' => 'rules',          'icon' => 'bi-shield-check',     'title' => 'Compliance Rules',      'desc' => 'Enable, disable and configure global metadata validation rules.'],
    ['key' => 'import',         'icon' => 'bi-cloud-download',   'title' => 'Import from Jagger',    'desc' => 'Migrate federations and entities from a legacy Jagger (ResourceRegistry3) database.'],
    ['key' => 'scheduler',      'icon' => 'bi-gear',             'title' => 'Scheduler',             'desc' => 'Configure automated job timings for metadata generation, cert checks and eduGAIN sync.'],
    ['key' => 'preferences',    'icon' => 'bi-sliders',          'title' => 'System Preferences',    'desc' => 'Application-wide settings: name, mail, authentication, cookie consent and eduGAIN.'],
    ['key' => 'users',          'icon' => 'bi-people',           'title' => 'Users',                 'desc' => 'View users, change roles, suspend accounts and review per-user activity.'],
    ['key' => 'audit',          'icon' => 'bi-clock-history',    'title' => 'Audit Log',             'desc' => 'Full history of all significant actions with before/after diffs.'],
    ['key' => 'signing',        'icon' => 'bi-pen',              'title' => 'Metadata Signing',      'desc' => 'Install xmlsectool and configure signing keys for signed metadata aggregates.'],
    ['key' => 'simplesamlphp',  'icon' => 'bi-shield-lock',      'title' => 'SimpleSAMLphp Auth',    'desc' => 'Install and configure SimpleSAMLphp 2.4+ for institutional SAML2 login.'],
];
@endphp

<div class="container-fluid py-4 px-4" style="max-width:860px;"
     x-data="{ tab: new URLSearchParams(location.search).get('tab') || 'sections' }">

    <div class="mb-4">
        <h1 class="h4 fw-bold mb-1">User Guide</h1>
        <p class="text-muted small mb-0">Documentation for every section of the Federation Manager.</p>
    </div>

    {{-- Search --}}
    <script>
        window._guideSectionsIndex = @json($searchIndex);
        window._guideOpsIndex      = @json($opsIndex);
    </script>
    <div class="mb-4 position-relative"
         x-data="guideSearch(window._guideSectionsIndex, window._guideOpsIndex)"
         @click.outside="results = []">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="search"
                   class="form-control border-start-0 ps-0"
                   placeholder="Search the guide…"
                   x-model="query"
                   @input.debounce.200ms="search()"
                   @keydown.escape="clear()"
                   @focus="query.length >= 2 && search()"
                   autocomplete="off">
        </div>
        <div x-show="results.length > 0" x-cloak
             class="position-absolute w-100 bg-white border rounded-bottom shadow-sm"
             style="z-index:1050;max-height:420px;overflow-y:auto;top:100%;margin-top:-1px;">
            <template x-for="r in results" :key="r.id">
                <a :href="r.url"
                   class="d-block px-3 py-2 text-decoration-none guide-search-result">
                    <div class="d-flex align-items-center gap-2">
                        <div class="fw-semibold small text-dark" x-text="r.title"></div>
                        <span class="badge rounded-pill text-bg-light fw-normal" style="font-size:.68rem;"
                              x-text="r.badge"></span>
                    </div>
                    <div class="text-muted small mt-1" style="line-height:1.4" x-html="r.snippet"></div>
                </a>
            </template>
        </div>
    </div>

    {{-- Tab nav --}}
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <button type="button" class="nav-link" :class="tab === 'sections' ? 'active' : ''"
                    @click="tab = 'sections'">
                <i class="bi bi-layout-text-sidebar me-1"></i>Sections
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" :class="tab === 'operations' ? 'active' : ''"
                    @click="tab = 'operations'">
                <i class="bi bi-list-check me-1"></i>Operations
            </button>
        </li>
    </ul>

    {{-- ══════════════════════════════════
         TAB 1 — SECTIONS
         ══════════════════════════════════ --}}
    <div x-show="tab === 'sections'">
        <div class="row g-3">
            @foreach ($sections as $s)
            <div class="col-md-6">
                <a href="{{ route('guide.show', $s['key']) }}"
                   class="card border shadow-none text-decoration-none h-100 guide-card">
                    <div class="card-body d-flex gap-3 align-items-start">
                        <i class="bi {{ $s['icon'] }} fs-4 text-primary flex-shrink-0 mt-1"></i>
                        <div>
                            <div class="fw-semibold small text-dark">{{ $s['title'] }}</div>
                            <div class="text-muted small mt-1">{{ $s['desc'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ══════════════════════════════════
         TAB 2 — OPERATIONS (group cards)
         ══════════════════════════════════ --}}
    <div x-show="tab === 'operations'" x-cloak>
        <div class="row g-3">
            @foreach(\App\Http\Controllers\GuideController::OP_GROUPS as $key => $group)
            @php $hasPage = view()->exists('guide.operations.' . $key); @endphp
            <div class="col-md-6">
                @if($hasPage)
                <a href="{{ route('guide.operations', $key) }}"
                   class="card border shadow-none text-decoration-none h-100 guide-card">
                @else
                <div class="card border shadow-none h-100 guide-card-disabled">
                @endif
                    <div class="card-body d-flex gap-3 align-items-start">
                        <i class="bi {{ $group['icon'] }} fs-4 {{ $hasPage ? 'text-primary' : 'text-muted' }} flex-shrink-0 mt-1"></i>
                        <div>
                            <div class="fw-semibold small {{ $hasPage ? 'text-dark' : 'text-muted' }}">{{ $group['title'] }}</div>
                            <div class="text-muted small mt-1">{{ $group['desc'] }}</div>
                            @if(!$hasPage)
                            <span class="badge bg-light text-muted border mt-2" style="font-size:.68rem;">Coming soon</span>
                            @endif
                        </div>
                    </div>
                @if($hasPage)
                </a>
                @else
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

</div>{{-- /x-data --}}

@push('styles')
<style>
.guide-card          { transition: border-color .15s, box-shadow .15s; }
.guide-card:hover    { border-color: #0d6efd !important; box-shadow: 0 0 0 3px rgba(13,110,253,.08) !important; }
.guide-card-disabled { opacity: .6; }
.guide-search-result { border-bottom: 1px solid #f0f0f0; }
.guide-search-result:last-child { border-bottom: none; }
.guide-search-result:hover { background: #f8f9fa; }
.guide-search-result mark { background: #fff3cd; padding: 0 1px; border-radius: 2px; }
[x-cloak] { display: none !important; }
</style>
@endpush

@push('scripts')
<script>
function guideSearch(sectionsIndex, opsIndex) {
    return {
        query: '',
        results: [],
        search() {
            const q = this.query.trim().toLowerCase();
            if (q.length < 2) { this.results = []; return; }
            const matches = [];

            for (const item of sectionsIndex) {
                if (matches.length >= 10) break;
                const titleLow = item.title.toLowerCase();
                const textLow  = item.text.toLowerCase();
                const pos      = textLow.indexOf(q);
                if (!titleLow.includes(q) && pos === -1) continue;
                matches.push({
                    id:      'sec-' + item.key,
                    title:   item.title,
                    badge:   'Guide section',
                    url:     '{{ url('/guide') }}/' + item.key,
                    snippet: pos !== -1 ? this.excerpt(item.text, pos, q.length) : '',
                });
            }

            for (const item of opsIndex) {
                if (matches.length >= 10) break;
                const titleLow = item.title.toLowerCase();
                const textLow  = item.text.toLowerCase();
                const pos      = textLow.indexOf(q);
                if (!titleLow.includes(q) && pos === -1) continue;
                matches.push({
                    id:      'op-' + item.group + '-' + item.op,
                    title:   item.title,
                    badge:   item.groupTitle,
                    url:     '{{ url('/guide/operations') }}/' + item.group + '?op=' + item.op,
                    snippet: pos !== -1 ? this.excerpt(item.text, pos, q.length) : '',
                });
            }

            this.results = matches;
        },
        excerpt(text, pos, len) {
            const q    = text.slice(pos, pos + len);
            const start = Math.max(0, pos - 60);
            const end   = Math.min(text.length, pos + len + 80);
            return (start > 0 ? '&hellip;' : '')
                + this.esc(text.slice(start, pos))
                + '<mark>' + this.esc(q) + '</mark>'
                + this.esc(text.slice(pos + len, end))
                + (end < text.length ? '&hellip;' : '');
        },
        clear() { this.query = ''; this.results = []; },
        esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); },
    };
}
</script>
@endpush
@endsection
