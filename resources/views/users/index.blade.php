{{--
    resources/views/users/index.blade.php
    User management — list all federation operator accounts.
--}}
@extends('layouts.app')

@section('title', 'Users — Federation Registry')

@section('content')

<div class="container-fluid py-4 px-4">

    {{-- Header ─────────────────────────────────────────────────────────── --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">Users</h1>
            <p class="text-muted small mb-0">Federation operator accounts</p>
        </div>
    </div>

    {{-- Filters ─────────────────────────────────────────────────────────── --}}
    <div class="bg-light border rounded p-3 mb-3">
        <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-control form-control-sm" placeholder="Search name or email…">
                </div>
                <div class="col-auto">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <option value="active"    {{ request('status') === 'active'    ? 'selected' : '' }}>Active</option>
                        <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All roles</option>
                        <option value="Admin"              {{ request('role') === 'Admin'              ? 'selected' : '' }}>Admin</option>
                        <option value="Federation Manager" {{ request('role') === 'Federation Manager' ? 'selected' : '' }}>Federation Manager</option>
                        <option value="Entity Manager"     {{ request('role') === 'Entity Manager'     ? 'selected' : '' }}>Entity Manager</option>
                        <option value="Guest"              {{ request('role') === 'Guest'              ? 'selected' : '' }}>Guest</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['search', 'status', 'role']))
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm ms-1">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    @endif
                </div>
                <div class="col"></div>
                <div class="col-auto">
                    <span class="text-muted small">{{ $users->total() }} user(s)</span>
                </div>
            </form>
    </div>

    {{-- Table ───────────────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Name</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Email</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Role</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Status</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Last Login</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Joined</th>
                        <th class="text-center pe-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    @php $userRole = $user->roles->first()?->name ?? 'Guest'; @endphp
                    <tr class="{{ $user->status === 'suspended' ? 'table-secondary' : '' }}">
                        <td class="ps-3">
                            <a href="{{ route('users.show', $user) }}" class="text-decoration-none fw-semibold">
                                {{ $user->name }}
                            </a>
                        </td>
                        <td class="small text-muted">{{ $user->email }}</td>
                        <td>
                            @if($userRole === 'Admin')
                                <span class="badge bg-danger">Admin</span>
                            @elseif($userRole === 'Federation Manager')
                                <span class="badge bg-primary">Federation Manager</span>
                            @elseif($userRole === 'Entity Manager')
                                <span class="badge bg-info text-dark">Entity Manager</span>
                            @else
                                <span class="badge bg-secondary">Guest</span>
                            @endif
                        </td>
                        <td>
                            @if($user->status === 'suspended')
                                <span class="d-inline-flex align-items-center gap-1 small">
                                    <span class="status-dot status-dot-danger"></span><span>Suspended</span>
                                </span>
                            @else
                                <span class="d-inline-flex align-items-center gap-1 small">
                                    <span class="status-dot status-dot-success"></span><span>Active</span>
                                </span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            {{ $user->last_login_at?->format('d M Y H:i') ?? '—' }}
                        </td>
                        <td class="small text-muted">
                            {{ $user->created_at->format('d M Y') }}
                        </td>
                        <td class="text-center pe-3">
                            <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">
                                <div>
                                    {{-- View --}}
                                    @can('user.view')
                                    <a href="{{ route('users.show', $user) }}"
                                       class="btn btn-sm btn-outline-secondary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @endcan
                                    {{-- Edit --}}
                                    @can('user.edit')
                                    <a href="{{ route('users.edit', $user) }}"
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @endcan
                                    {{-- Suspend / Reinstate (not self) --}}
                                    @can('user.edit')
                                    @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.suspend', $user) }}"
                                          style="display:contents"
                                          data-action="{{ $user->status === 'suspended' ? 'reinstate' : 'suspend' }}"
                                          onsubmit="event.preventDefault(); var a=this.dataset.action; SwalDefault.fire({title: a==='reinstate' ? Lang.user.reinstate_title : Lang.user.suspend_title, icon: a==='reinstate' ? 'question' : 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm}).then(r => { if (r.isConfirmed) this.submit() })">
                                        @csrf @method('PATCH')
                                        @if($user->status === 'suspended')
                                            <button class="btn btn-sm btn-outline-success" title="Reinstate">
                                                <i class="bi bi-person-check"></i>
                                            </button>
                                        @else
                                            <button class="btn btn-sm btn-outline-warning" title="Suspend">
                                                <i class="bi bi-person-dash"></i>
                                            </button>
                                        @endif
                                    </form>
                                    @endif
                                    @endcan

                                </div>

                                {{-- Role change (Admin only, inline) --}}
                                @if(auth()->user()?->hasRole('Admin'))
                                <form method="POST" action="{{ route('users.changeRole', $user) }}"
                                      class="d-flex gap-1 align-items-center">
                                    @csrf @method('PATCH')
                                    <select name="role" class="form-select form-select-sm"
                                            style="width:auto;" title="Change role">
                                        <option value="Admin"              {{ $userRole === 'Admin'              ? 'selected' : '' }}>Admin</option>
                                        <option value="Federation Manager" {{ $userRole === 'Federation Manager' ? 'selected' : '' }}>Federation Manager</option>
                                        <option value="Entity Manager"     {{ $userRole === 'Entity Manager'     ? 'selected' : '' }}>Entity Manager</option>
                                        <option value="Guest"              {{ $userRole === 'Guest'              ? 'selected' : '' }}>Guest</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-info" title="Apply role">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                @endif

                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="card-footer">{{ $users->links() }}</div>
        @endif
    </div>

</div>

@endsection
