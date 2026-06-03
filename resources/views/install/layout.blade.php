<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Installation') — Federation Registry</title>
    <link rel="icon" href="@yield('favicon', asset('favicon.ico'))">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root { --orange: #e87722; }

        body { background: #f5f7fa; min-height: 100vh; }

        .install-header {
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            padding: .875rem 1.5rem;
        }

        .brand-icon { color: var(--orange); font-size: 1.4rem; }
        .brand-name { font-weight: 700; font-size: 1.05rem; color: #212529; }

        .install-card {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: .5rem;
            padding: 2rem;
        }

        /* Progress steps */
        .steps-bar { display: flex; gap: .25rem; margin-bottom: 2rem; }

        .step-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .375rem;
        }

        .step-circle {
            width: 2rem; height: 2rem;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem; font-weight: 600;
            border: 2px solid #dee2e6;
            background: #fff;
            color: #6c757d;
        }

        .step-item.done .step-circle   { background: #198754; border-color: #198754; color: #fff; }
        .step-item.active .step-circle { background: var(--orange); border-color: var(--orange); color: #fff; }

        .step-label { font-size: .7rem; color: #6c757d; text-align: center; white-space: nowrap; }
        .step-item.active .step-label  { color: var(--orange); font-weight: 600; }
        .step-item.done .step-label    { color: #198754; }

        .step-connector {
            flex: 1; height: 2px; background: #dee2e6;
            margin-top: 1rem; align-self: flex-start;
        }
        .step-connector.done { background: #198754; }

        .btn-primary {
            background: var(--orange); border-color: var(--orange);
        }
        .btn-primary:hover { background: #d06a1a; border-color: #d06a1a; }
        .btn-check-label { display: inline-flex; align-items: center; gap: .375rem; }
    </style>

    @stack('styles')
</head>
<body>

<header class="install-header d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-shield-fill-check brand-icon"></i>
    <span class="brand-name">Federation Registry</span>
    <span class="text-muted ms-2 small">Installation</span>
</header>

<div class="container" style="max-width: 760px;">

    {{-- Step progress bar --}}
    @isset($currentStep)
    @php
        $stepLabels = ['Requirements', 'Signing Backend', 'Database', 'Mail', 'Admin', 'Summary'];
        $completedSteps = $completedSteps ?? [];
    @endphp
    <div class="steps-bar align-items-center mb-4">
        @foreach($stepLabels as $i => $label)
            @php
                $n      = $i + 1;
                $done   = in_array($n, $completedSteps);
                $active = $n === $currentStep;
            @endphp

            <div class="step-item {{ $done ? 'done' : ($active ? 'active' : '') }}">
                <div class="step-circle">
                    @if($done)
                        <i class="bi bi-check-lg"></i>
                    @else
                        {{ $n }}
                    @endif
                </div>
                <span class="step-label">{{ $label }}</span>
            </div>

            @if($i < count($stepLabels) - 1)
                <div class="step-connector {{ $done ? 'done' : '' }}"></div>
            @endif
        @endforeach
    </div>
    @endisset

    <div class="install-card">
        @yield('content')
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js" defer></script>

@stack('scripts')
</body>
</html>
