<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ \App\Models\SystemPreference::get('header_title_prefix') ? \App\Models\SystemPreference::get('header_title_prefix') . ' ' : '' }}@yield('title', \App\Models\SystemPreference::get('app_name', config('app.name', 'Federation Manager')))</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* ── Design tokens ──────────────────────────────────────────── */
        :root {
            --sidebar-width : 240px;
            --orange        : #e87722;
            --orange-light  : rgba(232, 119, 34, .08);
            --border-color  : #dee2e6;
            --surface-alt   : #f5f7fa;
        }

        /* ── Sidebar layout ─────────────────────────────────────────── */
        .sidebar {
            position   : fixed;
            top        : 0;
            left       : 0;
            width      : var(--sidebar-width);
            height     : 100vh;
            overflow-y : auto;
            z-index    : 1040;
            transition : transform .25s ease;
            background : #fff;
            border-right: 1px solid var(--border-color);
        }

        .sidebar-backdrop {
            display    : none;
            position   : fixed;
            inset      : 0;
            background : rgba(0,0,0,.35);
            z-index    : 1039;
        }

        @media (min-width: 992px) {
            .sidebar          { transform: translateX(0) !important; }
            .sidebar-backdrop { display: none !important; }
            .main-content     { margin-left: var(--sidebar-width); }
        }

        @media (max-width: 991.98px) {
            .sidebar.mobile-closed   { transform: translateX(-100%); }
            .sidebar.mobile-open     { transform: translateX(0); }
            .sidebar-backdrop.active { display: block; }
        }

        /* ── Sidebar nav ────────────────────────────────────────────── */
        .sidebar .sidebar-brand {
            display        : flex;
            align-items    : center;
            gap            : .625rem;
            padding        : .875rem 1rem;
            border-bottom  : 1px solid var(--border-color);
            text-decoration: none;
            color          : #212529;
            font-weight    : 600;
            font-size      : .9375rem;
        }
        .sidebar .sidebar-brand .brand-icon {
            color    : var(--orange);
            font-size: 1.25rem;
        }

        .sidebar .nav-link {
            display        : flex;
            align-items    : center;
            gap            : .5rem;
            color          : #495057;
            border-radius  : 0;
            padding        : .45rem .75rem .45rem 1rem;
            font-size      : .875rem;
            border-left    : 3px solid transparent;
            transition     : background .12s, color .12s, border-color .12s;
        }
        .sidebar .nav-link:hover {
            color      : #212529;
            background : var(--surface-alt);
        }
        .sidebar .nav-link.active {
            color        : var(--orange);
            background   : var(--orange-light);
            border-left  : 3px solid var(--orange);
            font-weight  : 500;
        }
        .sidebar .nav-link .bi {
            font-size : .9rem;
            width     : 1.1rem;
            flex-shrink: 0;
        }

        .sidebar .nav-section-label {
            font-size     : .6875rem;
            font-weight   : 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color         : #9aa0ac;
            padding       : .5rem 1rem .25rem;
            margin-top    : .25rem;
        }

        .sidebar .sub-link {
            display        : flex;
            align-items    : center;
            gap            : .5rem;
            color          : #6c757d;
            border-radius  : 0;
            padding        : .35rem .75rem .35rem 2.25rem;
            font-size      : .8125rem;
            text-decoration: none;
            border-left    : 3px solid transparent;
            transition     : background .12s, color .12s;
        }
        .sidebar .sub-link:hover {
            color      : #212529;
            background : var(--surface-alt);
        }
        .sidebar .sub-link.active {
            color      : var(--orange);
            background : var(--orange-light);
            border-left: 3px solid var(--orange);
        }

        .sidebar .sidebar-divider {
            border-top : 1px solid var(--border-color);
            margin     : .5rem 0;
        }

        /* ── Card design ────────────────────────────────────────────── */
        .card { box-shadow: none !important; }

        .card-header-label {
            font-size     : .6875rem;
            font-weight   : 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color         : #6c757d;
        }

        /* ── Status dots ────────────────────────────────────────────── */
        .status-dot {
            display      : inline-block;
            width        : 9px;
            height       : 9px;
            border-radius: 50%;
            flex-shrink  : 0;
        }
        .status-dot-success  { background: #28a745; }
        .status-dot-danger   { background: #dc3545; }
        .status-dot-warning  { background: #4a90d9; }
        .status-dot-advisory { background: #ffc107; }
        .status-dot-muted    { background: #adb5bd; }

        /* ── Table enhancements ─────────────────────────────────────── */
        .table thead th {
            font-size     : .6875rem;
            font-weight   : 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color         : #6c757d;
        }

        /* ── Topbar ─────────────────────────────────────────────────── */
        .topbar {
            background  : #fff;
            border-bottom: 1px solid var(--border-color);
            position    : sticky;
            top         : 0;
            z-index     : 1030;
        }

        /* ── Misc ───────────────────────────────────────────────────── */
        [wire\:loading] { opacity: .6; }
        [x-cloak]       { display: none !important; }
        .font-monospace { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
        .bg-surface-alt { background: var(--surface-alt); }
        .border-orange  { border-color: var(--orange) !important; }
        .text-orange    { color: var(--orange) !important; }

        /* ── Page background ────────────────────────────────────────── */
        body { background: var(--surface-alt); }
    </style>

    @stack('styles')
</head>
<body x-data="{ sidebarOpen: false }"
      @keydown.escape.window="sidebarOpen = false">

    {{-- ── Sidebar ─────────────────────────────────────────────────────────── --}}
    <aside class="sidebar"
           :class="sidebarOpen ? 'mobile-open' : 'mobile-closed'"
           id="sidebar"
           aria-label="Main navigation">
        @include('layouts.sidenav')
    </aside>

    {{-- Mobile backdrop --}}
    <div class="sidebar-backdrop"
         :class="{ 'active': sidebarOpen }"
         @click="sidebarOpen = false"
         aria-hidden="true"></div>

    {{-- ── Main area ───────────────────────────────────────────────────────── --}}
    <div class="main-content d-flex flex-column min-vh-100">

        @include('layouts.topbar')

        {{-- Page content --}}
        <main class="flex-grow-1 pb-4">
            <div class="container-fluid px-4">
                @yield('content')
            </div>
        </main>

        @include('layouts.footer')
    </div>

    @livewireScripts
    @stack('scripts')

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.Lang = {
            confirm: {
                cancel:  "{{ __('app.action_cancel') }}",
                confirm: "{{ __('app.action_confirm') }}",
            },
            entity: {
                delete_title:  "{{ __('app.confirm_entity_delete_title') }}",
                delete_text:   "{{ __('app.confirm_entity_delete_text') }}",
                restore_title: "{{ __('app.confirm_entity_restore_title') }}",
                force_title:   "{{ __('app.confirm_entity_force_title') }}",
                force_text:    "{{ __('app.confirm_entity_force_text') }}",
                remove_title:  "{{ __('app.confirm_entity_remove_title') }}",
            },
            federation: {
                delete_title:        "{{ __('app.confirm_federation_delete_title') }}",
                delete_text:         "{{ __('app.confirm_federation_delete_text') }}",
                reject_title:        "{{ __('app.confirm_federation_reject_title') }}",
                reject_input_label:  "{{ __('app.confirm_federation_reject_input_label') }}",
                reject_placeholder:  "{{ __('app.confirm_federation_reject_placeholder') }}",
                reject_required:     "{{ __('app.confirm_federation_reject_required') }}",
                metadata_title:      "{{ __('app.confirm_federation_metadata_title') }}",
                metadata_text:       "{{ __('app.confirm_federation_metadata_text') }}",
                remove_title:        "{{ __('app.confirm_federation_remove_title') }}",
                force_title:         "{{ __('app.confirm_federation_force_title') }}",
                force_text:          "{{ __('app.confirm_federation_force_text') }}",
                validator_del_title: "{{ __('app.confirm_federation_validator_del_title') }}",
                policy_del_title:    "{{ __('app.confirm_federation_policy_del_title') }}",
            },
            user: {
                suspend_title:   "{{ __('app.confirm_user_suspend_title') }}",
                reinstate_title: "{{ __('app.confirm_user_reinstate_title') }}",
                delete_title:    "{{ __('app.confirm_user_delete_title') }}",
            },
            attribute: {
                delete_title: "{{ __('app.confirm_attribute_delete_title') }}",
                delete_text:  "{{ __('app.confirm_attribute_delete_text') }}",
            },
            mail: {
                send_title:   "{{ __('app.confirm_mail_send_title') }}",
                delete_title: "{{ __('app.confirm_mail_delete_title') }}",
            },
            cert: {
                notify_title: "{{ __('app.confirm_cert_notify_title') }}",
                notify_text:  "{{ __('app.confirm_cert_notify_text') }}",
            },
        };

        const SwalDefault = Swal.mixin({
            confirmButtonColor: '#0d6efd',
            cancelButtonColor:  '#6c757d',
            cancelButtonText:   window.Lang.confirm.cancel,
            reverseButtons:     true,
            focusCancel:        true,
        });
    </script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('swal-confirm', ({ title, text, icon, component, method, params }) => {
                SwalDefault.fire({
                    title: title ?? Lang.confirm.confirm,
                    text:  text  ?? '',
                    icon:  icon  ?? 'warning',
                    showCancelButton: true,
                    confirmButtonText: Lang.confirm.confirm,
                }).then(result => {
                    if (result.isConfirmed) {
                        const el = document.querySelector('[wire\\:id="' + component + '"]');
                        if (el) {
                            const args = Array.isArray(params) ? params : [];
                            Livewire.find(el.getAttribute('wire:id')).call(method, ...args);
                        }
                    }
                });
            });
        });
    </script>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 4000,
            extendedTimeOut: 2000,
        };

        if (!window._notifyListenerRegistered) {
            window._notifyListenerRegistered = true;
            document.addEventListener('livewire:initialized', () => {
                Livewire.on('notify', ({ type, message }) => {
                    toastr[type](message);
                });

                Livewire.hook('request', ({ fail }) => {
                    fail(({ status, preventDefault }) => {
                        if (status === 403) {
                            preventDefault();
                            toastr.warning('You do not have permission to perform this action.');
                        }
                    });
                });
            });
        }

        @if(session('success'))
            toastr.success("{{ session('success') }}");
        @endif
        @if(session('error'))
            toastr.error("{{ session('error') }}");
        @endif
        @if(session('warning'))
            toastr.warning("{{ session('warning') }}");
        @endif
        @if(session('info'))
            toastr.info("{{ session('info') }}");
        @endif
    </script>

    @if(\App\Models\SystemPreference::get('cookie_consent_enabled', false))
    <div id="cookie-consent"
         class="position-fixed bottom-0 w-100 bg-dark text-white p-3"
         style="z-index:9999"
         x-data="{ show: !localStorage.getItem('cookie_accepted') }"
         x-show="show">
        <div class="container d-flex justify-content-between align-items-center">
            <span class="small">{{ \App\Models\SystemPreference::get('cookie_consent_text') }}</span>
            <button class="btn btn-sm btn-outline-light ms-3"
                    @click="localStorage.setItem('cookie_accepted','1'); show=false">
                Accept
            </button>
        </div>
    </div>
    @endif
    <script>
        document.addEventListener('show.bs.modal', e => e.target.removeAttribute('aria-hidden'));
        document.addEventListener('hide.bs.modal', () => document.activeElement?.blur());
    </script>
    <script>
    (function () {
        var activeLink = document.querySelector('.sidebar .nav-link.active');
        if (!activeLink) return;
        var iconEl = activeLink.querySelector('i[class]');
        if (!iconEl) return;
        var match = Array.from(iconEl.classList).find(function (c) { return c.startsWith('bi-') && c !== 'bi'; });
        if (!match) return;
        var name = match.slice(3); // strip "bi-"
        fetch('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/' + name + '.svg')
            .then(function (r) { return r.text(); })
            .then(function (svg) {
                var colored = svg.replace(/currentColor/g, '#e87722');
                var link = document.querySelector('link[rel~="icon"]') || document.createElement('link');
                link.rel  = 'icon';
                link.type = 'image/svg+xml';
                link.href = 'data:image/svg+xml,' + encodeURIComponent(colored);
                document.head.appendChild(link);
            })
            .catch(function () {});
    })();
    </script>
    <script>
    window.copyToClipboard = function (text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        return Promise.resolve();
    };
    </script>
</body>
</html>
