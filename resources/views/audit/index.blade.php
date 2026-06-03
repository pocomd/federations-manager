{{--
    resources/views/audit/index.blade.php
    Audit log — paginated, filterable. Expandable rows show old/new value diff.
    Expansion handled by Alpine.js (already in stack).
--}}
@extends('layouts.app')

@section('title', 'Audit Log — Federation Registry')

@section('content')

<div class="container-fluid py-4 px-4">

    {{-- Header ─────────────────────────────────────────────────────────── --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">Audit Log</h1>
            <p class="text-muted small mb-0">Full change history for all federation objects</p>
        </div>
    </div>

    {{-- Filters ─────────────────────────────────────────────────────────── --}}
    <div class="bg-light border rounded p-3 mb-3">
        <form method="GET" action="{{ route('audit.index') }}" class="row g-2 align-items-end">

                {{-- Entity --}}
                <div class="col-md-3">
                    <label class="form-label form-label-sm text-muted mb-1">Entity</label>
                    <select name="entity_id" class="form-select form-select-sm">
                        <option value="">All entities</option>
                        @foreach($entities as $entity)
                            <option value="{{ $entity->id }}"
                                {{ request('entity_id') === $entity->id ? 'selected' : '' }}>
                                {{ $entity->entity_id }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- User (admins/operators only) --}}
                @if($canViewAll)
                <div class="col-md-2">
                    <label class="form-label form-label-sm text-muted mb-1">User</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">All users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}"
                                {{ request('user_id') === $u->id ? 'selected' : '' }}>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Action --}}
                <div class="col-md-2">
                    <label class="form-label form-label-sm text-muted mb-1">Action</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">All actions</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}"
                                {{ request('action') === $action ? 'selected' : '' }}>
                                {{ __('app.action_' . $action) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Date from --}}
                <div class="col-md-2">
                    <label class="form-label form-label-sm text-muted mb-1">From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="form-control form-control-sm">
                </div>

                {{-- Date to --}}
                <div class="col-md-2">
                    <label class="form-label form-label-sm text-muted mb-1">To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                           class="form-control form-control-sm">
                </div>

                {{-- Buttons --}}
                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search"></i>
                    </button>
                    @if(request()->hasAny(['entity_id', 'user_id', 'action', 'date_from', 'date_to']))
                        <a href="{{ route('audit.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>

            </form>
    </div>

    {{-- Results count --}}
    <p class="text-muted small mb-2">
        Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }}
        of {{ $logs->total() }} entries
    </p>

    {{-- Audit log table ──────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Timestamp</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">User</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Action</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Entity</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Changes</th>
                        <th class="pe-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">IP</th>
                    </tr>
                </thead>
                {{-- One <tbody> per log entry so Alpine x-data scope spans both rows --}}
                @forelse($logs as $log)
                @php
                    $changedKeys = array_unique(array_merge(
                        array_keys(is_array($log->old_values) ? $log->old_values : []),
                        array_keys(is_array($log->new_values) ? $log->new_values : [])
                    ));
                    $actionColor = match(true) {
                        str_contains($log->action, 'created') => 'bg-success',
                        str_contains($log->action, 'deleted') => 'bg-danger',
                        str_contains($log->action, 'updated') => 'bg-primary',
                        default                                => 'bg-secondary',
                    };
                @endphp
                <tbody x-data="{ open: false }">
                    <tr>
                        {{-- Timestamp --}}
                        <td class="ps-3 small text-muted text-nowrap">
                            {{ $log->created_at->format('d M Y H:i:s') }}
                        </td>

                        {{-- User --}}
                        <td class="small">
                            @if($log->user)
                                @if($canViewAll)
                                    <a href="{{ route('users.show', $log->user) }}"
                                       class="text-decoration-none">
                                        {{ $log->user->name }}
                                    </a>
                                @else
                                    {{ $log->user->name }}
                                @endif
                            @else
                                <span class="text-muted">System</span>
                            @endif
                        </td>

                        {{-- Action --}}
                        <td>
                            <span class="badge {{ $actionColor }}">{{ __('app.action_' . $log->action) }}</span>
                        </td>

                        {{-- Entity --}}
                        <td class="small font-monospace">
                            @if($log->entity)
                                <a href="{{ route('entities.show', $log->entity) }}"
                                   class="text-decoration-none text-muted"
                                   style="max-width:220px;overflow:hidden;text-overflow:ellipsis;display:block;white-space:nowrap;">
                                    {{ $log->entity->entity_id }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        {{-- Changes summary + expand toggle --}}
                        <td>
                            @if(!empty($changedKeys))
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                                        @click="open = !open">
                                    <span class="small text-muted">
                                        {{ count($changedKeys) }} field(s)
                                    </span>
                                    <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                </button>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>

                        {{-- IP --}}
                        <td class="pe-3 small text-muted">{{ $log->ip_address }}</td>
                    </tr>

                    {{-- Expandable diff row (shares x-data scope from parent <tbody>) --}}
                    @if(!empty($changedKeys))
                    <tr x-show="open" x-cloak class="table-light">
                        <td colspan="6" class="px-3 py-2">
                            <div class="small">
                                <table class="table table-bordered table-sm mb-0">
                                    <thead>
                                        <tr class="table-secondary">
                                            <th style="width:20%">Field</th>
                                            <th style="width:40%">Old Value</th>
                                            <th style="width:40%">New Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($changedKeys as $key)
                                        <tr>
                                            <td class="font-monospace text-muted">{{ $key }}</td>
                                            <td class="text-danger font-monospace">
                                                {{ is_array($log->old_values[$key] ?? null)
                                                    ? json_encode($log->old_values[$key])
                                                    : ($log->old_values[$key] ?? '—') }}
                                            </td>
                                            <td class="text-success font-monospace">
                                                {{ is_array($log->new_values[$key] ?? null)
                                                    ? json_encode($log->new_values[$key])
                                                    : ($log->new_values[$key] ?? '—') }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    @endif
                </tbody>
                @empty
                <tbody>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No audit log entries match the current filters.
                        </td>
                    </tr>
                </tbody>
                @endforelse
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-footer">{{ $logs->links() }}</div>
        @endif
    </div>

</div>

@endsection
