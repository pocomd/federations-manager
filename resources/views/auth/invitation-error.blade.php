<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation Error — {{ config('app.name') }}</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .error-card { max-width: 480px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

<div class="error-card w-100 px-3">

    <div class="text-center mb-4">
        <h1 class="h4 fw-bold">{{ config('app.name') }}</h1>
        <p class="text-muted small">Federation Registry</p>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4 text-center">

            <div class="mb-3">
                <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 2.5rem;"></i>
            </div>

            <h2 class="h5 fw-semibold mb-3">Invitation Link Unavailable</h2>

            <p class="text-muted small mb-3">{{ $reason }}</p>

            @if($invitation && $invitation->invitedBy)
            <p class="small mb-4">
                Please contact <strong>{{ $invitation->invitedBy->name }}</strong>
                (<a href="mailto:{{ $invitation->invitedBy->email }}">{{ $invitation->invitedBy->email }}</a>)
                to request a new invitation link.
            </p>
            @elseif($invitation && $invitation->federation)
            <p class="small mb-4">
                Please contact your Federation Manager at
                <strong>{{ $invitation->federation->name }}</strong>
                to request a new invitation link.
            </p>
            @else
            <p class="small mb-4">
                Please contact the person who invited you to request a new link.
            </p>
            @endif

            @if($invitation && $invitation->accepted_at)
            <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm">
                Back to Login
            </a>
            @endif

        </div>
    </div>

</div>

</body>
</html>
