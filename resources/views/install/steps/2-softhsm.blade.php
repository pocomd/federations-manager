@extends('install.layout')

@section('title', 'Step 2 — Signing Backend')
@section('favicon', "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔐</text></svg>")

@php
    $currentStep    = 2;
    $completedSteps = [1];
@endphp

@section('content')

<h4 class="mb-1">Signing Backend</h4>
<p class="text-muted small mb-4">
    Configure how federation signing credentials are stored. The <strong>file driver</strong> is always
    available and stores keys as PEM files on disk. The <strong>SoftHSM2 driver</strong> stores keys
    inside a PKCS#11 software token, reducing exposure from file permission mistakes or backup leaks.
</p>

{{-- Security notice --}}
<div class="alert alert-warning small d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-shield-exclamation mt-1 flex-shrink-0"></i>
    <div>
        <strong>SoftHSM2 is a software token.</strong>
        Anyone with root access and the <code>JAGGER_HSM_PIN</code> can sign metadata directly via
        xmlsectool, bypassing this application. This is the fundamental difference from a hardware HSM.
        The audit log is the only detection mechanism for such bypass.
        SoftHSM2 protects against: unprivileged users, web exploits, accidental backup leaks.
    </div>
</div>

{{-- File driver (always available) --}}
<div class="card border-success mb-3">
    <div class="card-body py-3 d-flex align-items-center gap-3">
        <i class="bi bi-check-circle-fill text-success fs-5 flex-shrink-0"></i>
        <div>
            <div class="fw-semibold small">Local file driver</div>
            <div class="text-muted small">Always active — stores PEM keys under <code>storage/app/signing-keys/</code></div>
        </div>
        <span class="badge bg-success ms-auto">Active</span>
    </div>
</div>

{{-- SoftHSM2 detection --}}
<div class="card mb-4 @if($softhsmAvailable) border-success @else border-secondary @endif">
    <div class="card-body py-3">
        <div class="d-flex align-items-center gap-3 mb-2">
            <i class="bi bi-hdd-stack @if($softhsmAvailable) text-success @else text-secondary @endif fs-5 flex-shrink-0"></i>
            <div>
                <div class="fw-semibold small">SoftHSM2 driver</div>
                <div class="text-muted small">Requires <code>softhsm2</code> and <code>opensc</code> packages</div>
            </div>
            <span class="badge @if($softhsmAvailable) bg-success @else bg-secondary @endif ms-auto">
                @if($softhsmAvailable) Available @else Not installed @endif
            </span>
        </div>

        @if(! $softhsmAvailable)
        <div class="mt-2 ps-5">
            <p class="small text-muted mb-2">Install the required packages:</p>
            <pre class="bg-dark text-light rounded px-3 py-2 small mb-2">sudo apt install softhsm2 opensc</pre>
            <p class="small text-muted mb-0">After installing, reload this page to detect the packages.</p>
        </div>
        @else
        <div class="mt-2 ps-5">
            <div class="row g-2">
                <div class="col-sm-6">
                    <div class="small text-muted">softhsm2-util</div>
                    <code class="small">{{ $softhsmUtil }}</code>
                </div>
                <div class="col-sm-6">
                    <div class="small text-muted">pkcs11-tool</div>
                    <code class="small">{{ $pkcs11Tool }}</code>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('install.process', 2) }}">
    @csrf
    @if($softhsmAvailable)
        <input type="hidden" name="enable_softhsm" value="1">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
                Enable SoftHSM2 &amp; Continue
            </button>
            <button type="submit" name="enable_softhsm" value="0" class="btn btn-outline-secondary btn-sm">
                Skip — use file driver only
            </button>
        </div>
    @else
        <input type="hidden" name="enable_softhsm" value="0">
        <button type="submit" class="btn btn-primary btn-sm">
            Continue with file driver only
        </button>
    @endif
</form>

@endsection
