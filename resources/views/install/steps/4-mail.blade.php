@extends('install.layout')

@section('title', 'Step 4 — Mail')
@section('favicon', "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📧</text></svg>")

@php
    $currentStep    = 4;
    $completedSteps = [1, 2, 3];
@endphp

@section('content')

<h4 class="fw-bold mb-1">Mail Configuration</h4>
<p class="text-muted small mb-4">
    Configure your SMTP server for certificate expiry alerts, entity approval workflows
    and admin notifications. You can skip this step and configure it later.
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
    testing: false,
    testResult: null,
    async runTest() {
        this.testing = true;
        this.testResult = null;
        const form = document.getElementById('mail-form');
        const fd = new FormData(form);
        fd.set('_action', 'test');
        try {
            const r = await fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json' }
            });
            this.testResult = await r.json();
        } catch (e) {
            this.testResult = { success: false, message: 'Request failed: ' + e.message };
        } finally {
            this.testing = false;
        }
    }
}">

<form method="POST" action="{{ route('install.process', 4) }}" id="mail-form">
    @csrf

    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label fw-semibold small">SMTP Host</label>
            <input type="text" class="form-control form-control-sm @error('mail_host') is-invalid @enderror"
                   name="mail_host" value="{{ old('mail_host', $existing['host'] ?? '') }}"
                   placeholder="smtp.example.com">
            @error('mail_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold small">Port</label>
            <input type="number" class="form-control form-control-sm @error('mail_port') is-invalid @enderror"
                   name="mail_port" value="{{ old('mail_port', '587') }}" min="1" max="65535">
            @error('mail_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold small">
                Username <span class="text-muted fw-normal">(optional)</span>
            </label>
            <input type="text" class="form-control form-control-sm"
                   name="mail_username" value="{{ old('mail_username', '') }}"
                   autocomplete="username">
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold small">
                Password <span class="text-muted fw-normal">(optional)</span>
            </label>
            <input type="password" class="form-control form-control-sm"
                   name="mail_password" autocomplete="current-password">
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold small">Encryption</label>
            <select class="form-select form-select-sm @error('mail_encryption') is-invalid @enderror"
                    name="mail_encryption">
                <option value="tls"  {{ old('mail_encryption', 'tls') === 'tls'  ? 'selected' : '' }}>TLS (STARTTLS — port 587)</option>
                <option value="ssl"  {{ old('mail_encryption') === 'ssl'  ? 'selected' : '' }}>SSL (port 465)</option>
                <option value="none" {{ old('mail_encryption') === 'none' ? 'selected' : '' }}>None</option>
            </select>
            @error('mail_encryption')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold small">From Address</label>
            <input type="email" class="form-control form-control-sm @error('mail_from_address') is-invalid @enderror"
                   name="mail_from_address"
                   value="{{ old('mail_from_address', $existing['from_address'] ?? '') }}"
                   placeholder="registry@example.com">
            @error('mail_from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold small">From Name</label>
            <input type="text" class="form-control form-control-sm @error('mail_from_name') is-invalid @enderror"
                   name="mail_from_name"
                   value="{{ old('mail_from_name', $existing['from_name'] ?? 'Federation Registry') }}">
            @error('mail_from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        {{-- Test email --}}
        <div class="col-12">
            <div class="p-3 bg-light rounded border">
                <label class="form-label fw-semibold small mb-2">Send a test email</label>
                <div class="d-flex gap-2 align-items-start flex-wrap">
                    <input type="email" class="form-control form-control-sm" style="max-width:280px"
                           name="test_email" placeholder="recipient@example.com">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            @click="runTest()" :disabled="testing">
                        <span x-show="testing" class="spinner-border spinner-border-sm me-1"></span>
                        <span x-text="testing ? 'Sending…' : 'Send Test'"></span>
                    </button>
                </div>
                <div class="mt-2" x-show="testResult !== null" x-cloak>
                    <span :class="testResult?.success ? 'text-success' : 'text-danger'"
                          class="small">
                        <i :class="testResult?.success ? 'bi-check-circle' : 'bi-x-circle'"
                           class="bi me-1"></i>
                        <span x-text="testResult?.message"></span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-4">

    <div class="d-flex justify-content-between align-items-center">
        <a href="{{ route('install.step', 2) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <div class="d-flex gap-2">
            <button type="submit" name="skip" value="1" class="btn btn-outline-secondary btn-sm">
                Skip this step
            </button>
            <button type="submit" class="btn btn-primary px-4">
                Next <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</form>
</div>

@endsection
