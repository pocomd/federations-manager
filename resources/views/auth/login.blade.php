<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — {{ \App\Models\SystemPreference::get('app_name', config('app.name', 'Federation Manager')) }}</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .login-card { max-width: 440px; }
        .divider { display: flex; align-items: center; gap: 1rem; color: #6c757d; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #dee2e6; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

<div class="login-card w-100 px-3">

    {{-- Header --}}
    <div class="text-center mb-4">
        <h1 class="h4 fw-bold">{{ \App\Models\SystemPreference::get('app_name', config('app.name', 'Federation Manager')) }}</h1>
        <p class="text-muted small">Federation Registry</p>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">

            {{-- Option 1: SAML institutional login --}}
            @if(config('simplesamlphp.enabled'))
            <p class="fw-semibold text-center mb-3">{{ __('app.auth_login') }}</p>

            <a href="{{ route('saml.login') }}"
               class="btn btn-sm btn-primary d-block w-100 py-2">
                {{ __('app.auth_institutional') }}
            </a>

            {{-- Divider --}}
            <div class="divider my-4">{{ __('app.auth_or_divider') }}</div>
            @endif

            {{-- Option 2: Local email + password --}}
            <p class="fw-semibold text-center mb-3">{{ __('app.auth_login') }}</p>

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label small fw-semibold">{{ __('app.auth_email') }}</label>
                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror"
                           required
                           autocomplete="email"
                           autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label small fw-semibold">{{ __('app.auth_password') }}</label>
                    <input id="password"
                           type="password"
                           name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           required
                           autocomplete="current-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 form-check">
                    <input id="remember" type="checkbox" name="remember" class="form-check-input">
                    <label for="remember" class="form-check-label small">Remember me</label>
                </div>

                <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                    {{ __('app.auth_login') }}
                </button>
            </form>

        </div>
    </div>

    @php $supportEmail = \App\Models\SystemPreference::get('support_email') @endphp
    @if($supportEmail)
    <p class="text-center text-muted small mt-3">
        Need help or access? Contact <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
    </p>
    @endif

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    toastr.options = { positionClass: 'toast-top-right', timeOut: 6000, closeButton: true };
    @if(session('warning'))
        toastr.warning("{{ session('warning') }}");
    @endif
    @if(session('info'))
        toastr.info("{{ session('info') }}");
    @endif
</script>
</body>
</html>
