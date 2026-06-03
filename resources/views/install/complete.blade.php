@extends('install.layout')

@section('title', 'Installation Complete')
@section('favicon', "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✅</text></svg>")

@section('content')

<div class="text-center mb-4">
    <div class="mb-3">
        <i class="bi bi-shield-fill-check" style="font-size:3rem;color:#e87722"></i>
    </div>
    <h3 class="fw-bold">Your federation registry is operational.</h3>
    <p class="text-muted">
        Installation completed successfully. Log in with your admin account to continue setup.
    </p>
</div>

<hr class="my-4">

<p class="fw-semibold small mb-3">Before your federation goes live, consider these next steps:</p>

<div class="row g-3 mb-4">

    {{-- Federation preferences --}}
    <div class="col-12">
        <div class="d-flex align-items-start gap-3 p-3 rounded border">
            <i class="bi bi-building fs-4 mt-1" style="color:#e87722"></i>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold small">Configure System Preferences</span>
                    <span class="badge" style="background:#e87722;font-size:.65rem">Recommended</span>
                </div>
                <p class="text-muted small mb-0">
                    Set your federation name, registration authority URI, mail settings,
                    and notification preferences. Required before publishing metadata.
                </p>
            </div>
        </div>
    </div>

    {{-- Signing certificate --}}
    <div class="col-12">
        <div class="d-flex align-items-start gap-3 p-3 rounded border">
            <i class="bi bi-key fs-4 mt-1" style="color:#e87722"></i>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold small">Upload Signing Certificate</span>
                    <span class="badge" style="background:#e87722;font-size:.65rem">Recommended</span>
                </div>
                <p class="text-muted small mb-0">
                    Configure <code>FEDERATION_SIGNING_KEY</code>, <code>FEDERATION_SIGNING_CERT</code>
                    and <code>XMLSECTOOL_PATH</code> in <code>.env</code>.
                    Required to publish signed metadata aggregates.
                    See the <a href="{{ route('guide.show', 'signing') }}">Metadata Signing guide</a> for step-by-step instructions.
                </p>
            </div>
        </div>
    </div>

    {{-- Mail — only if skipped --}}
    @if($mailSkipped)
    <div class="col-12">
        <div class="d-flex align-items-start gap-3 p-3 rounded border">
            <i class="bi bi-envelope fs-4 mt-1 text-secondary"></i>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold small">Configure Mail</span>
                    <span class="badge bg-secondary" style="font-size:.65rem">Optional</span>
                </div>
                <p class="text-muted small mb-0">
                    Set up an SMTP server to enable certificate expiry alerts, entity approval
                    workflows, and admin notifications. Configure in Admin → Preferences after
                    logging in.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Queue worker — critical --}}
    <div class="col-12">
        <div class="d-flex align-items-start gap-3 p-3 rounded border border-warning bg-warning bg-opacity-10">
            <i class="bi bi-exclamation-triangle-fill fs-4 mt-1 text-warning"></i>
            <div class="flex-grow-1" style="overflow-x: auto;">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold small">Queue Worker — Required</span>
                    <span class="badge bg-danger" style="font-size:.65rem">Do this now</span>
                </div>
                <p class="text-muted small mb-2">
                    The application relies on a persistent background worker process to execute all
                    time-sensitive tasks. Without it, <strong>metadata is never regenerated, certificate
                    warnings are never sent, webhooks are never delivered, and the health check reports
                    "No heartbeat — cron or queue worker may not be running"</strong>.
                </p>
                <p class="text-muted small mb-1">
                    A ready-made systemd unit file is included. Copy it, enable it, and start it:
                </p>
                <pre class="small bg-dark text-light p-2 rounded mb-1">sudo cp {{ base_path('deploy/federations-management.service') }} /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now federations-management
sudo systemctl status federations-management</pre>
                <p class="text-muted small mb-0">
                    See the <a href="{{ route('guide.show', 'scheduler') }}">Scheduler guide</a> for the crontab entry, Supervisor alternative, and post-deploy restart instructions.
                </p>
            </div>
        </div>
    </div>

</div>

<div class="d-flex justify-content-center gap-3">
    <a href="{{ route('login') }}" class="btn btn-primary px-4">
        <i class="bi bi-box-arrow-in-right me-1"></i> Go to Login
    </a>
    <a href="{{ url('/') }}" class="btn btn-outline-secondary">
        Open Application
    </a>
</div>

@endsection
