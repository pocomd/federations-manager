@extends('install.layout')

@section('title', 'Step 5 — Admin Account')
@section('favicon', "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>👤</text></svg>")

@php
    $currentStep    = 5;
    $completedSteps = [1, 2, 3, 4];
@endphp

@section('content')

<h4 class="fw-bold mb-1">Admin Account</h4>
<p class="text-muted small mb-4">
    Create the first administrator account. This account will have full access to all
    settings and can invite additional users.
</p>

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $e)
            <li>{{ $e }}</li>
        @endforeach
    </ul>
</div>
@endif

<div x-data="{
    password: '',
    get strength() {
        const p = this.password;
        if (p.length === 0) return 0;
        let s = 0;
        if (p.length >= 12) s++;
        if (p.length >= 16) s++;
        if (/[A-Z]/.test(p)) s++;
        if (/[0-9]/.test(p)) s++;
        if (/[^A-Za-z0-9]/.test(p)) s++;
        return s;
    },
    get strengthLabel() {
        return ['', 'Weak', 'Fair', 'Good', 'Strong', 'Very strong'][this.strength] ?? '';
    },
    get strengthColor() {
        return ['', 'danger', 'warning', 'info', 'primary', 'success'][this.strength] ?? 'secondary';
    }
}">

<form method="POST" action="{{ route('install.process', 5) }}">
    @csrf

    <div class="row g-3">
        <div class="col-12">
            <label class="form-label fw-semibold small">Full name</label>
            <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror"
                   name="name" value="{{ old('name') }}" autocomplete="name" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold small">Email address</label>
            <input type="email" class="form-control form-control-sm @error('email') is-invalid @enderror"
                   name="email" value="{{ old('email') }}" autocomplete="email" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold small">Password</label>
            <input type="password" class="form-control form-control-sm @error('password') is-invalid @enderror"
                   name="password" x-model="password" autocomplete="new-password"
                   minlength="12" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror

            <div class="mt-2" x-show="password.length > 0" x-cloak>
                <div class="progress" style="height:4px">
                    <div class="progress-bar"
                         :class="'bg-' + strengthColor"
                         :style="'width:' + (strength / 5 * 100) + '%'"
                         role="progressbar"></div>
                </div>
                <div class="small mt-1" :class="'text-' + strengthColor" x-text="strengthLabel"></div>
            </div>
            <div class="form-text">Minimum 12 characters.</div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold small">Confirm password</label>
            <input type="password" class="form-control form-control-sm @error('password_confirmation') is-invalid @enderror"
                   name="password_confirmation" autocomplete="new-password" required>
            @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <hr class="my-4">

    <div class="d-flex justify-content-between">
        <a href="{{ route('install.step', 3) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <button type="submit" class="btn btn-primary px-4">
            Next <i class="bi bi-arrow-right ms-1"></i>
        </button>
    </div>
</form>
</div>

@endsection
