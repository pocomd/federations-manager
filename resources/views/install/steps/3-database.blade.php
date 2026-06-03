@extends('install.layout')

@section('title', 'Step 3 — Database')
@section('favicon', "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🗄️</text></svg>")

@php
    $currentStep    = 3;
    $completedSteps = [1, 2];
@endphp

@section('content')

<h4 class="fw-bold mb-1">Application & Database</h4>
<p class="text-muted small mb-4">
    Set the public URL your registry will be served from, then confirm or update your database connection.
</p>

@if($errors->has('db_connection'))
<div class="alert alert-danger">
    <i class="bi bi-x-circle me-1"></i> {{ $errors->first('db_connection') }}
</div>
@endif

@if($errors->has('migrate'))
<div class="alert alert-danger">
    <strong>Migration failed:</strong>
    <pre class="mb-0 mt-2 small" style="white-space: pre-wrap;">{{ $errors->first('migrate') }}</pre>
</div>
@endif

@if($errors->has('seed'))
<div class="alert alert-danger">
    <strong>Seeder failed:</strong>
    <pre class="mb-0 mt-2 small" style="white-space: pre-wrap;">{{ $errors->first('seed') }}</pre>
</div>
@endif

@if($preConfigured)

{{-- Pre-configured: summary card with optional update --}}
<div x-data="{ updating: false }">

    {{-- Keep form: app URL + next --}}
    <div x-show="!updating">
        <form method="POST" action="{{ route('install.process', 3) }}">
            @csrf
            <input type="hidden" name="action" value="keep">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold small">Application URL</label>
                    <input type="url" class="form-control form-control-sm @error('app_url') is-invalid @enderror"
                           name="app_url"
                           value="{{ old('app_url', $existing['app_url'] ?? rtrim(config('app.url'), '/')) }}"
                           placeholder="https://registry.example.com" required>
                    @error('app_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">The public HTTPS URL of this registry.</div>
                </div>
            </div>

            <div class="mt-3 p-3 rounded border d-flex align-items-center justify-content-between">
                <div>
                    <div class="small fw-semibold">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>
                        Database configured during pre-install
                    </div>
                    <div class="small text-muted mt-1">
                        <code>{{ $existing['database'] ?? '' }}</code>
                        on <code>{{ $existing['host'] ?? '' }}</code>
                        as <code>{{ $existing['username'] ?? '' }}</code>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm ms-3"
                        @click="updating = true">
                    <i class="bi bi-pencil me-1"></i>Update
                </button>
            </div>

            @if(session('has_existing_tables'))
            <div class="alert alert-warning mt-3 mb-0">
                <p class="mb-2 fw-semibold">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Existing database detected
                </p>
                <p class="small mb-3">The database already contains tables. Choose how to proceed:</p>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" name="sub_action" value="fresh" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>
                        Fresh Install
                        <span class="badge bg-white text-danger ms-1" style="font-size:.65rem">drops all data</span>
                    </button>
                    <button type="submit" name="sub_action" value="sync" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-up-circle me-1"></i>
                        Migrate over existing
                        <span class="badge bg-secondary ms-1" style="font-size:.65rem">keeps data</span>
                    </button>
                </div>
            </div>
            @endif

            <hr class="my-4">

            <div class="d-flex justify-content-between">
                <a href="{{ route('install.step', 1) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
                @if(!session('has_existing_tables'))
                <button type="submit" class="btn btn-primary px-4">
                    Next <i class="bi bi-arrow-right ms-1"></i>
                </button>
                @endif
            </div>
        </form>
    </div>

    {{-- Update form: full credentials --}}
    <div x-show="updating" x-cloak>
        <div x-data="{ action: '' }">
        <form method="POST" action="{{ route('install.process', 3) }}" id="db-form">
            @csrf
            <input type="hidden" name="action" :value="action">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold small">Application URL</label>
                    <input type="url" class="form-control form-control-sm @error('app_url') is-invalid @enderror"
                           name="app_url"
                           value="{{ old('app_url', $existing['app_url'] ?? rtrim(config('app.url'), '/')) }}"
                           placeholder="https://registry.example.com" required>
                    @error('app_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12"><hr class="my-1"></div>

                @include('install.steps.partials.db-fields', ['existing' => $existing])
            </div>

            @if(session('has_existing_tables'))
            @include('install.steps.partials.db-existing-tables')
            @endif

            <hr class="my-4">

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        @click="updating = false">
                    <i class="bi bi-arrow-left me-1"></i> Cancel
                </button>
                @if(!session('has_existing_tables'))
                <button type="submit" class="btn btn-primary px-4">
                    Next <i class="bi bi-arrow-right ms-1"></i>
                </button>
                @endif
            </div>
        </form>
        </div>
    </div>

</div>

@else

{{-- Not pre-configured: show full form directly --}}
<div x-data="{ action: '' }">
<form method="POST" action="{{ route('install.process', 3) }}" id="db-form">
    @csrf
    <input type="hidden" name="action" :value="action">

    <div class="row g-3">
        <div class="col-12">
            <label class="form-label fw-semibold small">Application URL</label>
            <input type="url" class="form-control form-control-sm @error('app_url') is-invalid @enderror"
                   name="app_url"
                   value="{{ old('app_url', $existing['app_url'] ?? rtrim(config('app.url'), '/')) }}"
                   placeholder="https://registry.example.com" required>
            @error('app_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">The public HTTPS URL of this registry. Used in emails, SAML metadata, and absolute links.</div>
        </div>

        <div class="col-12"><hr class="my-1"></div>

        @include('install.steps.partials.db-fields', ['existing' => $existing])
    </div>

    @if(session('has_existing_tables'))
    @include('install.steps.partials.db-existing-tables')
    @endif

    <hr class="my-4">

    <div class="d-flex justify-content-between">
        <a href="{{ route('install.step', 1) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        @if(!session('has_existing_tables'))
        <button type="submit" class="btn btn-primary px-4">
            Next <i class="bi bi-arrow-right ms-1"></i>
        </button>
        @endif
    </div>
</form>
</div>

@endif

@endsection
