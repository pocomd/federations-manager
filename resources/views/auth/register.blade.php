<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accept Invitation — {{ config('app.name') }}</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <style>
        body { background-color: #f8f9fa; }
        .register-card { max-width: 480px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

<div class="register-card w-100 px-3">

    <div class="text-center mb-4">
        <h1 class="h4 fw-bold">{{ config('app.name') }}</h1>
        <p class="text-muted small">Federation Registry</p>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">

            <p class="fw-semibold text-center mb-1">You've been invited to join</p>
            <p class="text-center text-primary fw-bold mb-3">{{ $invitation->federation?->name ?? 'the federation' }}</p>

            @if ($invitation->entity)
                <div class="alert alert-info py-2 small">
                    You are being added as a co-manager of:
                    <strong>{{ $invitation->entity->getDisplayName() ?? $invitation->entity->entity_id }}</strong>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('register.invitation.register', $invitation->token) }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email address</label>
                    <input type="email"
                           class="form-control"
                           value="{{ $invitation->email }}"
                           readonly>
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label small fw-semibold">Your name</label>
                    <input id="name"
                           type="text"
                           name="name"
                           value="{{ old('name') }}"
                           class="form-control @error('name') is-invalid @enderror"
                           required
                           autofocus>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label small fw-semibold">Password</label>
                    <input id="password"
                           type="password"
                           name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="form-label small fw-semibold">Confirm password</label>
                    <input id="password_confirmation"
                           type="password"
                           name="password_confirmation"
                           class="form-control"
                           required>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Create account &amp; accept invitation
                </button>
            </form>

        </div>
    </div>

    @if ($invitation->expires_at)
        <p class="text-center text-muted small mt-3">
            This invitation expires on {{ $invitation->expires_at->timezone(config('app.timezone'))->format('d M Y H:i') }}.
        </p>
    @endif

</div>

</body>
</html>
