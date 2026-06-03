{{--
    resources/views/users/show.blade.php
    User detail — profile info, role badge, recent audit log entries.
--}}
@extends('layouts.app')

@section('title', $user->name . ' — Federation Registry')

@section('content')

<div class="container-fluid py-4 px-4">

    {{-- Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
        </ol>
    </nav>

    <div class="row g-4">

        {{-- Profile card ────────────────────────────────────────────────── --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
                    <span class="card-header-label"><i class="bi bi-person me-1"></i> Profile</span>
                    @can('user.edit')
                        <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </a>
                    @endcan
                </div>
                <div class="card-body">
                    @php $userRole = $user->roles->first()?->name ?? 'Guest'; @endphp
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Name</dt>
                        <dd class="col-7 fw-semibold">{{ $user->name }}</dd>

                        <dt class="col-5 text-muted">Email</dt>
                        <dd class="col-7">{{ $user->email }}</dd>

                        <dt class="col-5 text-muted">Role</dt>
                        <dd class="col-7">
                            <span class="badge {{ $userRole === 'Admin' ? 'bg-danger' : ($userRole === 'Operator' ? 'bg-primary' : 'bg-secondary') }}">
                                {{ $userRole }}
                            </span>
                        </dd>

                        <dt class="col-5 text-muted">Status</dt>
                        <dd class="col-7">
                            @if($user->status === 'suspended')
                                <span class="d-inline-flex align-items-center gap-1 small">
                                    <span class="status-dot status-dot-danger"></span><span>Suspended</span>
                                </span>
                            @else
                                <span class="d-inline-flex align-items-center gap-1 small">
                                    <span class="status-dot status-dot-success"></span><span>Active</span>
                                </span>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted">Last Login</dt>
                        <dd class="col-7">{{ $user->last_login_at?->format('d M Y H:i') ?? '—' }}</dd>

                        <dt class="col-5 text-muted">Joined</dt>
                        <dd class="col-7">{{ $user->created_at->format('d M Y') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Recent activity ──────────────────────────────────────────────── --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
                    <span class="card-header-label"><i class="bi bi-clock-history me-1"></i> Recent Activity</span>
                    <a href="{{ route('audit.index', ['user_id' => $user->id]) }}"
                       class="btn btn-outline-secondary btn-sm">
                        Full log
                    </a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">When</th>
                                <th>Action</th>
                                <th>Entity</th>
                                <th class="pe-3">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogs as $log)
                            <tr>
                                <td class="ps-3 small text-muted text-nowrap">
                                    {{ $log->created_at->format('d M Y H:i') }}
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ __('app.action_' . $log->action) }}</span>
                                </td>
                                <td class="small font-monospace text-muted">
                                    @if($log->entity)
                                        <a href="{{ route('entities.show', $log->entity) }}"
                                           class="text-decoration-none text-muted"
                                           style="max-width:260px;overflow:hidden;text-overflow:ellipsis;display:block;white-space:nowrap;">
                                            {{ $log->entity->entity_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="pe-3 small text-muted">{{ $log->ip_address }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No activity recorded.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

@endsection
