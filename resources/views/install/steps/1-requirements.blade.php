@extends('install.layout')

@section('title', 'Step 1 — Requirements')
@section('favicon', "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔍</text></svg>")

@php
    $currentStep    = 1;
    $completedSteps = [];
@endphp

@section('content')

<h4 class="fw-bold mb-1">System Requirements</h4>
<p class="text-muted small mb-4">Checking your server meets all requirements before installation begins.</p>

@if($errors->any())
<div class="alert alert-danger mb-3">
    @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
    @endforeach
</div>
@endif

<table class="table table-sm table-borderless mb-0">
    <thead class="table-light">
        <tr>
            <th>Requirement</th>
            <th class="text-center" style="width:80px">Status</th>
            <th>Details</th>
        </tr>
    </thead>
    <tbody>
        @foreach($checks as $check)
        <tr>
            <td class="align-middle">
                <code class="text-body">{{ $check['label'] }}</code>
                @if(!$check['required'])
                    <span class="badge bg-secondary ms-1" style="font-size:.65rem">optional</span>
                @endif
            </td>
            <td class="align-middle text-center">
                @if($check['pass'])
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                @elseif(!$check['required'])
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                @else
                    <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                @endif
            </td>
            <td class="align-middle small text-muted">{{ $check['detail'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@if(!$canProceed)
<div class="alert alert-danger mt-3 mb-0 small">
    <i class="bi bi-x-circle me-1"></i>
    One or more required checks failed. Resolve the issues above and
    <a href="{{ route('install.step', 1) }}">refresh this page</a>.
</div>
@else
<div class="alert alert-success mt-3 mb-0 small">
    <i class="bi bi-check-circle me-1"></i>
    All required checks passed.
    @php $warned = collect($checks)->where('required', false)->where('pass', false)->count(); @endphp
    @if($warned)
        {{ $warned }} optional {{ Str::plural('check', $warned) }} {{ $warned === 1 ? 'is' : 'are' }} not met — you can install without {{ $warned === 1 ? 'it' : 'them' }}.
    @endif
</div>
@endif

{{-- xmlsectool not found: inline install instructions --}}
@php $xmlsec = collect($checks)->firstWhere('label', 'xmlsectool'); @endphp
@if($xmlsec && !$xmlsec['pass'])
<div class="mt-3 p-3 bg-light rounded border">
    <p class="mb-2 small fw-semibold">
        <i class="bi bi-info-circle me-1 text-warning"></i>
        Installing xmlsectool (optional — required for signed metadata)
    </p>
    @if(!empty($xmlsec['error']))
    <p class="mb-2 small text-muted">Error when running xmlsectool:</p>
    <pre class="small bg-dark text-danger p-2 rounded mb-2">{{ $xmlsec['error'] }}</pre>
    @endif
    <p class="mb-2 small text-muted">xmlsectool requires Java. Install Java 17 first:</p>
    <pre class="small bg-dark text-light p-2 rounded mb-2"># Ubuntu / Debian
sudo apt-get install -y openjdk-17-jre-headless

# CentOS / RHEL
sudo dnf install -y java-17-openjdk-headless</pre>
    <p class="mb-2 small text-muted">Then install xmlsectool:</p>
    <pre class="small bg-dark text-light p-2 rounded mb-2">cd /tmp
curl -LO https://shibboleth.net/downloads/tools/xmlsectool/3.0.0/xmlsectool-3.0.0-bin.zip
unzip xmlsectool-3.0.0-bin.zip
sudo mv xmlsectool-3.0.0 /opt/xmlsectool
sudo ln -s /opt/xmlsectool/xmlsectool.sh /usr/local/bin/xmlsectool
sudo chmod +x /usr/local/bin/xmlsectool</pre>
    <p class="mb-0 small text-muted">
        After installation, <a href="{{ route('install.step', 1) }}">refresh this page</a> to confirm it is detected.
        You can also skip this and configure signing later from the Setup Guide after logging in.
    </p>
</div>
@endif

{{-- missing PHP extensions: generic install instructions --}}
@php $missingExts = collect($checks)->filter(fn($c) => str_starts_with($c['label'], 'ext-') && !$c['pass']); @endphp
@if($missingExts->isNotEmpty())
<div class="mt-3 p-3 bg-light rounded border">
    <p class="mb-2 small fw-semibold">
        <i class="bi bi-info-circle me-1 text-danger"></i>
        Missing PHP extension{{ $missingExts->count() > 1 ? 's' : '' }}
    </p>
    <p class="mb-2 small text-muted">Install the missing extension{{ $missingExts->count() > 1 ? 's' : '' }}:</p>
    <pre class="small bg-dark text-light p-2 rounded mb-2"># Ubuntu / Debian
sudo apt-get install -y {{ $missingExts->map(fn($c) => 'php-'.substr($c['label'], 4))->join(' ') }}

# CentOS / RHEL
sudo dnf install -y {{ $missingExts->map(fn($c) => 'php-'.substr($c['label'], 4))->join(' ') }}</pre>
    <p class="mb-2 small text-muted">Then restart PHP-FPM:</p>
    <pre class="small bg-dark text-light p-2 rounded mb-2"># Ubuntu / Debian
sudo systemctl restart php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm

# CentOS / RHEL
sudo systemctl restart php-fpm</pre>
    <p class="mb-0 small text-muted">
        After installation, <a href="{{ route('install.step', 1) }}">refresh this page</a> to confirm all extensions are detected.
    </p>
</div>
@endif

{{-- .env not writable: manual instructions --}}
@php $envCheck = collect($checks)->firstWhere('label', '.env'); @endphp
@if($envCheck && !$envCheck['pass'])
<div class="mt-3 p-3 bg-light rounded border">
    <p class="mb-2 small fw-semibold">Manual .env setup</p>
    <p class="mb-2 small text-muted">
        Copy <code>.env.example</code> to <code>.env</code> and make it writable:
    </p>
    <pre class="mb-0 small bg-dark text-light p-2 rounded">cp .env.example .env
chmod 664 .env
chown www-data:www-data .env</pre>
</div>
@endif

<hr class="my-4">

<div class="d-flex justify-content-end">
    <form method="POST" action="{{ route('install.process', 1) }}">
        @csrf
        <button type="submit" class="btn btn-primary px-4" {{ !$canProceed ? 'disabled' : '' }}>
            Next <i class="bi bi-arrow-right ms-1"></i>
        </button>
    </form>
</div>

@endsection
