{{--
    resources/views/users/edit.blade.php
    Edit user — name, email. Role change via separate form (Admin only).
--}}
@extends('layouts.app')

@section('title', 'Edit User — ' . $user->name)

@section('content')

<div class="container-fluid py-4 px-4">

    {{-- Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
            <li class="breadcrumb-item"><a href="{{ route('users.show', $user) }}">{{ $user->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>

    <div class="row g-4">

        {{-- ── User info form ──────────────────────────────────────────── --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-person me-1"></i> Edit Profile
                </div>
                <div class="card-body">

                    @if($errors->any())
                        <div class="alert alert-danger py-2">
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('users.update', $user) }}">
                        @csrf @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Name</label>
                            <input type="text" id="name" name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $user->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email</label>
                            <input type="email" id="email" name="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $user->email) }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-floppy me-1"></i> Save Changes
                            </button>
                            <a href="{{ route('users.show', $user) }}" class="btn btn-outline-secondary btn-sm">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ── Role & status (Admin only) ──────────────────────────────── --}}
        @if(auth()->user()?->hasRole('Admin'))
        <div class="col-lg-6">

            {{-- Role card --}}
            <div class="card mb-3">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-shield-lock me-1"></i> Role
                </div>
                <div class="card-body">
                    @php $currentRole = $user->roles->first()?->name ?? 'Guest'; @endphp
                    <p class="small text-muted mb-3">
                        Current role:
                        <span class="badge {{ $currentRole === 'Admin' ? 'bg-danger' : ($currentRole === 'Federation Manager' ? 'bg-primary' : ($currentRole === 'Entity Manager' ? 'bg-info text-dark' : 'bg-secondary')) }}">
                            {{ $currentRole }}
                        </span>
                    </p>
                    <form method="POST" action="{{ route('users.changeRole', $user) }}">
                        @csrf @method('PATCH')
                        <div class="d-flex gap-2 align-items-center">
                            <select name="role" class="form-select">
                                @foreach($allowedRoles as $role)
                                    <option value="{{ $role }}" {{ $currentRole === $role ? 'selected' : '' }}>
                                        {{ $role }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-info btn-sm text-nowrap">
                                <i class="bi bi-check-lg me-1"></i> Apply
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Suspend / Reinstate card --}}
            @if($user->id !== auth()->id())
            <div class="card border-{{ $user->status === 'suspended' ? 'warning' : 'danger' }}">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold text-{{ $user->status === 'suspended' ? 'warning' : 'danger' }}">
                    <i class="bi bi-person-dash me-1"></i>
                    {{ $user->status === 'suspended' ? 'Account Suspended' : 'Suspend Account' }}
                </div>
                <div class="card-body">
                    @if($user->status === 'suspended')
                        <p class="small text-muted mb-3">
                            This account is currently suspended and cannot log in.
                        </p>
                        <form method="POST" action="{{ route('users.suspend', $user) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="bi bi-person-check me-1"></i> Reinstate Account
                            </button>
                        </form>
                    @else
                        <p class="small text-muted mb-3">
                            Suspending this account will prevent the user from logging in.
                        </p>
                        <form method="POST" action="{{ route('users.suspend', $user) }}"
                              onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.user.suspend_title, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm}).then(r => { if (r.isConfirmed) this.submit() })">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="bi bi-person-dash me-1"></i> Suspend Account
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            @endif

        </div>
        @endif

    </div>

</div>

@endsection
