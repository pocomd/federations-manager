@extends('layouts.app')

@section('title', 'Entity Operations — User Guide')

@section('content')

<script>
    window._guideSectionsIndex = @json($searchIndex);
    window._guideOpsIndex      = @json($opsIndex);
</script>

<div class="container-fluid py-4 px-4" style="max-width:1100px;">

    {{-- Breadcrumb + search --}}
    <div class="d-flex align-items-center justify-content-between mb-4 gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('guide.index') }}" class="text-muted text-decoration-none small">
                <i class="bi bi-book me-1"></i>User Guide
            </a>
            <i class="bi bi-chevron-right text-muted" style="font-size:.7rem;"></i>
            <a href="{{ route('guide.index') }}?tab=operations" class="text-muted text-decoration-none small">Operations</a>
            <i class="bi bi-chevron-right text-muted" style="font-size:.7rem;"></i>
            <span class="small fw-semibold">Entity Operations</span>
        </div>

        <div class="position-relative"
             x-data="guideSearch(window._guideSectionsIndex, window._guideOpsIndex)"
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
                <template x-for="r in results" :key="r.id">
                    <a :href="r.url" class="d-block px-3 py-2 text-decoration-none guide-search-result">
                        <div class="d-flex align-items-center gap-2">
                            <div class="fw-semibold small text-dark" x-text="r.title"></div>
                            <span class="badge rounded-pill text-bg-light fw-normal" style="font-size:.68rem;" x-text="r.badge"></span>
                        </div>
                        <div class="text-muted small mt-1" style="line-height:1.4" x-html="r.snippet"></div>
                    </a>
                </template>
            </div>
        </div>
    </div>

    {{-- Two-column layout --}}
    <div class="d-flex gap-0 border rounded" style="min-height:640px;"
         x-data="{ op: parseInt(new URLSearchParams(location.search).get('op') || '1') }">

        {{-- Left: operation list --}}
        <div class="flex-shrink-0 border-end" style="width:230px;overflow-y:auto;">
            <div class="px-3 pt-3 pb-1">
                <div class="text-uppercase text-muted fw-semibold" style="font-size:.68rem;letter-spacing:.05em;">
                    Entity Operations
                </div>
            </div>
            @php
            $ops = [
                 1 => 'Register a new entity',
                 2 => 'Import from XML',
                 3 => 'Edit entity details',
                 4 => 'Suspend an entity',
                 5 => 'Reactivate a suspended entity',
                 6 => 'Delete / restore an entity',
                 7 => 'Validate entity compliance',
                 8 => 'Preview entity XML',
                 9 => 'Manage SP requested attributes',
                10 => 'Configure IdP ARP',
                11 => 'Invite a contact as co-manager',
                12 => 'Send a direct invitation',
            ];
            @endphp
            <div class="list-group list-group-flush" style="font-size:.8125rem;">
                @foreach($ops as $n => $title)
                <button type="button"
                        class="list-group-item list-group-item-action border-0 py-2 px-3 text-start"
                        :class="op === {{ $n }} ? 'guide-op-active' : ''"
                        @click="op = {{ $n }}">
                    <span class="text-muted me-1" style="font-size:.72rem;">{{ $n }}.</span>{{ $title }}
                </button>
                @endforeach
            </div>
        </div>

        {{-- Right: detail panel --}}
        <div class="flex-grow-1 p-4 overflow-auto">
            @include('guide.operations.partials.entities')
        </div>{{-- /detail --}}
    </div>{{-- /two-column --}}

    <div class="border-top pt-3 mt-4">
        <a href="{{ route('guide.index') }}?tab=operations" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>All operation groups
        </a>
    </div>

</div>

@push('styles')
<style>
.guide-op-active { background-color: #0d6efd !important; color: #fff !important; }
.guide-op-active .text-muted { color: rgba(255,255,255,.65) !important; }
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
                const pos = item.text.toLowerCase().indexOf(q);
                if (!item.title.toLowerCase().includes(q) && pos === -1) continue;
                matches.push({ id: 'sec-' + item.key, title: item.title, badge: 'Guide section',
                    url: '{{ url('/guide') }}/' + item.key, snippet: pos !== -1 ? this.excerpt(item.text, pos, q.length) : '' });
            }
            for (const item of opsIndex) {
                if (matches.length >= 10) break;
                const pos = item.text.toLowerCase().indexOf(q);
                if (!item.title.toLowerCase().includes(q) && pos === -1) continue;
                matches.push({ id: 'op-' + item.group + '-' + item.op, title: item.title, badge: item.groupTitle,
                    url: '{{ url('/guide/operations') }}/' + item.group + '?op=' + item.op, snippet: pos !== -1 ? this.excerpt(item.text, pos, q.length) : '' });
            }
            this.results = matches;
        },
        excerpt(text, pos, len) {
            const s = Math.max(0, pos - 60), e = Math.min(text.length, pos + len + 80);
            return (s > 0 ? '&hellip;' : '') + this.esc(text.slice(s, pos))
                + '<mark>' + this.esc(text.slice(pos, pos + len)) + '</mark>'
                + this.esc(text.slice(pos + len, e)) + (e < text.length ? '&hellip;' : '');
        },
        clear() { this.query = ''; this.results = []; },
        esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); },
    };
}
</script>
@endpush

@endsection
