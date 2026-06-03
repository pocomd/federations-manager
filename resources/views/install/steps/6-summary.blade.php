@extends('install.layout')

@section('title', 'Step 6 — Summary')
@section('favicon', "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📋</text></svg>")

@php
    $currentStep    = 6;
    $completedSteps = [1, 2, 3, 4, 5];
@endphp

@section('content')

<h4 class="fw-bold mb-1">Ready to Install</h4>
<p class="text-muted small mb-4">
    Review the configuration below and click <strong>Complete Installation</strong> to finalize.
    The installer will set production mode, build caches, and lock itself permanently.
</p>

<div class="row g-3 mb-4">

    {{-- Application URL --}}
    <div class="col-12">
        <div class="d-flex align-items-start gap-3 p-3 rounded border bg-light">
            <i class="bi bi-globe text-success fs-4 mt-1"></i>
            <div>
                <div class="fw-semibold small">Application URL</div>
                <div class="text-muted small">
                    {{ $appUrl ?? '—' }}
                    <span class="text-success ms-2"><i class="bi bi-check-circle-fill"></i> Configured</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Database --}}
    <div class="col-12">
        <div class="d-flex align-items-start gap-3 p-3 rounded border bg-light">
            <i class="bi bi-database text-success fs-4 mt-1"></i>
            <div>
                <div class="fw-semibold small">Database</div>
                <div class="text-muted small">
                    {{ $db['host'] ?? '—' }} / {{ $db['database'] ?? '—' }}
                    <span class="text-success ms-2"><i class="bi bi-check-circle-fill"></i> Configured</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Mail --}}
    <div class="col-12">
        <div class="d-flex align-items-start gap-3 p-3 rounded border bg-light">
            <i class="bi bi-envelope {{ $mailSkipped ? 'text-warning' : 'text-success' }} fs-4 mt-1"></i>
            <div>
                <div class="fw-semibold small">Mail</div>
                @if($mailSkipped)
                <div class="text-muted small">
                    <span class="text-warning"><i class="bi bi-dash-circle-fill me-1"></i>Skipped</span>
                    — configure SMTP in <code>.env</code> or Admin → Preferences after installation.
                </div>
                @else
                <div class="text-muted small">
                    {{ $mail['from_address'] ?? '—' }} via {{ $mail['host'] ?? '—' }}:{{ $mail['port'] ?? '' }}
                    <span class="text-success ms-2"><i class="bi bi-check-circle-fill"></i> Configured</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Admin account --}}
    <div class="col-12">
        <div class="d-flex align-items-start gap-3 p-3 rounded border bg-light">
            <i class="bi bi-person-fill-gear text-success fs-4 mt-1"></i>
            <div>
                <div class="fw-semibold small">Admin Account</div>
                <div class="text-muted small">
                    {{ $admin['name'] ?? '—' }} &lt;{{ $admin['email'] ?? '—' }}&gt;
                    <span class="text-success ms-2"><i class="bi bi-check-circle-fill"></i> Created</span>
                </div>
            </div>
        </div>
    </div>

</div>


<div class="alert alert-secondary small">
    <i class="bi bi-info-circle me-1"></i>
    Clicking <strong>Complete Installation</strong> will:
    set <code>APP_ENV=production</code>,
    set <code>APP_DEBUG=false</code>,
    build config/route/view caches, and write the
    <code>storage/app/.installed</code> flag — permanently locking the installer.
</div>

<form method="POST" action="{{ route('install.process', 6) }}">
    @csrf
    <div class="d-flex justify-content-between mt-3">
        <a href="{{ route('install.step', 4) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <button type="submit" class="btn btn-success px-4 fw-semibold">
            <i class="bi bi-check-lg me-1"></i> Complete Installation
        </button>
    </div>
</form>

@endsection
