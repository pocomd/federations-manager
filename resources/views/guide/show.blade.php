@php
$titles = [
    'dashboard'     => 'Dashboard',
    'entities'      => 'Entities',
    'federations'   => 'Federations',
    'certificates'  => 'Certificates',
    'metadata'      => 'Metadata',
    'invitations'   => 'Invitations',
    'statistics'    => 'Statistics',
    'mail-templates'=> 'Mail Templates',
    'attributes'    => 'Attribute Definitions',
    'rules'         => 'Compliance Rules',
    'webhooks'      => 'Webhooks',
    'import'        => 'Import from Jagger',
    'scheduler'     => 'Scheduler',
    'preferences'   => 'System Preferences',
    'users'         => 'Users',
    'audit'         => 'Audit Log',
    'notifications' => 'Notifications',
    'signing'       => 'Metadata Signing (xmlsectool)',
];
$pageTitle = $titles[$page] ?? ucfirst($page);
@endphp

@extends('layouts.app')

@section('title', $pageTitle . ' — User Guide')

@section('content')
<div class="container-fluid py-4 px-4" style="max-width:860px;">

    <script>window._guideSearchIndex = @json($searchIndex);</script>

    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('guide.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-book me-1"></i>User Guide
        </a>
        <i class="bi bi-chevron-right text-muted" style="font-size:.7rem;"></i>
        <span class="small fw-semibold">{{ $pageTitle }}</span>

        <div class="ms-auto position-relative"
             x-data="guideSearch(window._guideSearchIndex)"
             @click.outside="results = []">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 py-1">
                    <i class="bi bi-search text-muted" style="font-size:.8rem;"></i>
                </span>
                <input type="search"
                       class="form-control border-start-0 ps-0 py-1"
                       style="width:210px;"
                       placeholder="Search guide…"
                       x-model="query"
                       @input.debounce.200ms="search()"
                       @keydown.escape="clear()"
                       @focus="query.length >= 2 && search()"
                       autocomplete="off">
            </div>
            <div x-show="results.length > 0" x-cloak
                 class="position-absolute bg-white border rounded-bottom shadow-sm"
                 style="z-index:1050;max-height:420px;overflow-y:auto;top:100%;margin-top:-1px;min-width:320px;right:0;">
                <template x-for="r in results" :key="r.key">
                    <a :href="'{{ url('/guide') }}/' + r.key"
                       class="d-block px-3 py-2 text-decoration-none guide-search-result">
                        <div class="fw-semibold small text-dark" x-text="r.title"></div>
                        <div class="text-muted small mt-1" style="line-height:1.4" x-html="r.snippet"></div>
                    </a>
                </template>
            </div>
        </div>
    </div>

    @include('guide.sections.' . $page)

    <div class="border-top pt-3 mt-5 d-flex justify-content-between">
        <a href="{{ route('guide.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>All topics
        </a>
    </div>

</div>

@push('styles')
<style>
.guide-search-result { border-bottom: 1px solid #f0f0f0; }
.guide-search-result:last-child { border-bottom: none; }
.guide-search-result:hover { background: #f8f9fa; }
.guide-search-result mark { background: #fff3cd; padding: 0 1px; border-radius: 2px; }
</style>
@endpush

@push('scripts')
<script>
function guideSearch(index) {
    return {
        query: '',
        results: [],
        index: index,
        search() {
            const q = this.query.trim().toLowerCase();
            if (q.length < 2) { this.results = []; return; }
            const matches = [];
            for (const item of this.index) {
                const titleLow = item.title.toLowerCase();
                const textLow  = item.text.toLowerCase();
                const inTitle  = titleLow.includes(q);
                const pos      = textLow.indexOf(q);
                if (!inTitle && pos === -1) continue;

                let snippet = '';
                if (pos !== -1) {
                    const start = Math.max(0, pos - 60);
                    const end   = Math.min(item.text.length, pos + q.length + 80);
                    snippet = (start > 0 ? '&hellip;' : '') +
                              this.escapeHtml(item.text.slice(start, pos)) +
                              '<mark>' + this.escapeHtml(item.text.slice(pos, pos + q.length)) + '</mark>' +
                              this.escapeHtml(item.text.slice(pos + q.length, end)) +
                              (end < item.text.length ? '&hellip;' : '');
                }
                matches.push({ key: item.key, title: item.title, snippet });
                if (matches.length >= 10) break;
            }
            this.results = matches;
        },
        clear() { this.query = ''; this.results = []; },
        escapeHtml(str) {
            return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        },
    };
}
</script>
@endpush

@endsection
