{{-- Top bar — flat white, 1px bottom border, no shadow (eduGAIN portal style) --}}
<header class="topbar px-3 py-2" style="height: 3.5rem;">
    <div class="d-flex align-items-center w-100 gap-3">

        {{-- Mobile sidebar toggle --}}
        <button class="btn btn-sm d-lg-none flex-shrink-0"
                @click="sidebarOpen = !sidebarOpen"
                :aria-expanded="sidebarOpen"
                aria-label="Toggle navigation">
            <i class="bi bi-list fs-5"></i>
        </button>

        {{-- Page title + guide link --}}
        @php
            $guideMap = [
                'dashboard'                => 'dashboard',
                'entities.*'               => 'entities',
                'federations.*'            => 'federations',
                'certificates.*'           => 'certificates',
                'metadata.*'               => 'metadata',
                'invitations.*'            => 'invitations',
                'invitation-requests.*'    => 'invitations',
                'statistics.*'             => 'statistics',
                'mail.templates.*'         => 'mail-templates',
                'attributes.*'             => 'attributes',
                'rules.*'                  => 'rules',
                'webhooks.*'               => 'webhooks',
                'import.*'                 => 'import',
                'scheduler.*'              => 'scheduler',
                'preferences.*'            => 'preferences',
                'users.*'                  => 'users',
                'audit.*'                  => 'audit',
                'notifications.*'          => 'notifications',
                'profile.notifications.*'  => 'notifications',
            ];
            $guideKey = null;
            foreach ($guideMap as $pattern => $key) {
                if (request()->routeIs($pattern)) { $guideKey = $key; break; }
            }
        @endphp
        <div class="d-flex align-items-center gap-2 me-auto overflow-hidden">
            <h1 class="h6 fw-semibold mb-0 text-dark text-truncate" style="font-size:.9rem;">
                @yield('title', 'Federation Manager')
            </h1>
            @if ($guideKey)
                <a href="{{ route('guide.show', $guideKey) }}"
                   target="_blank"
                   title="Open user guide for this page"
                   class="text-muted flex-shrink-0 lh-1"
                   style="font-size:.9rem;">
                    <i class="bi bi-info-circle"></i>
                </a>
            @endif
        </div>

        {{-- Right-side actions --}}
        <div class="d-flex align-items-center gap-2 flex-shrink-0">

            {{-- Language switcher (hidden when I18N_ENABLED=false) --}}
            @if(config('federation.i18n_enabled'))
            @php
                $supportedLangs = [
                    'en' => ['label' => 'English',  'flag' => '🇬🇧'],
                    'ro' => ['label' => 'Română',   'flag' => '🇲🇩'],
                    'de' => ['label' => 'Deutsch',  'flag' => '🇩🇪'],
                    'fr' => ['label' => 'Français', 'flag' => '🇫🇷'],
                    'ru' => ['label' => 'Русский',  'flag' => '🇷🇺'],
                ];
                $enabledCodes = array_filter(
                    array_map('trim', explode(',', \App\Models\SystemPreference::get('supported_languages', 'en,ro'))),
                    fn ($c) => $c !== ''
                );
                $langs = array_filter(
                    $supportedLangs,
                    fn ($code) => in_array($code, $enabledCodes),
                    ARRAY_FILTER_USE_KEY
                );
                $currentLocale = app()->getLocale();
                $currentLang   = $langs[$currentLocale] ?? ['label' => strtoupper($currentLocale), 'flag' => '🌐'];
            @endphp
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    {{ $currentLang['flag'] }}
                    <span class="ms-1 d-none d-md-inline small">{{ strtoupper($currentLocale) }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-none border">
                    @foreach($langs as $code => $lang)
                    <li>
                        <a class="dropdown-item small {{ $currentLocale === $code ? 'active' : '' }}"
                           href="{{ route('language.switch', $code) }}">
                            {{ $lang['flag'] }}
                            <span class="ms-2">{{ $lang['label'] }}</span>
                            @if($currentLocale === $code)
                                <i class="bi bi-check ms-1"></i>
                            @endif
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            @auth

            {{-- Critical certificate alert --}}
            @php $criticalCertCount = \App\Models\EntityCertificate::critical()->count(); @endphp
            @if ($criticalCertCount > 0)
                <a href="{{ route('certificates.monitor') }}"
                   class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1"
                   title="{{ $criticalCertCount }} certificate(s) expiring within 14 days">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span class="badge bg-danger rounded-1 lh-1">{{ $criticalCertCount }}</span>
                    <span class="d-none d-lg-inline small">Expiring Soon</span>
                </a>
            @endif

            {{-- Notification bell --}}
            @include('layouts.partials.notification-bell')

            {{-- User dropdown --}}
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2"
                        type="button" id="userMenuBtn"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i>
                    <span class="d-none d-md-inline text-truncate small" style="max-width:120px;">
                        {{ auth()->user()->name }}
                    </span>
                    @php $roleName = auth()->user()->getRoleNames()->first(); @endphp
                    @if ($roleName)
                        <span class="px-2 text-white rounded-1 d-none d-md-inline
                            {{ $roleName === 'Admin' ? 'bg-danger' : ($roleName === 'Operator' ? 'bg-primary' : 'bg-secondary') }}"
                              style="font-size:.65rem;">
                            {{ $roleName }}
                        </span>
                    @endif
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-none border" aria-labelledby="userMenuBtn">
                    <li>
                        <span class="dropdown-item-text small text-muted d-flex align-items-center gap-2">
                            <i class="bi bi-envelope"></i>
                            {{ auth()->user()->email }}
                        </span>
                    </li>
                    <li><hr class="dropdown-divider m-0"></li>
                    @can('user.view')
                    <li>
                        <a class="dropdown-item small d-flex align-items-center gap-2"
                           href="{{ route('users.show', auth()->user()) }}">
                            <i class="bi bi-person"></i> My Profile
                        </a>
                    </li>
                    @endcan
                    <li>
                        <a class="dropdown-item small d-flex align-items-center gap-2"
                           href="{{ route('audit.index') }}?user_id={{ auth()->id() }}">
                            <i class="bi bi-clock-history"></i> My Activity
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item small d-flex align-items-center gap-2"
                           href="{{ route('profile.notifications.index') }}">
                            <i class="bi bi-bell"></i> Notification Settings
                        </a>
                    </li>
                    <li><hr class="dropdown-divider m-0"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item small d-flex align-items-center gap-2 text-danger"
                                    type="submit">
                                <i class="bi bi-box-arrow-right"></i>
                                {{ __('app.action_sign_out') }}
                            </button>
                        </form>
                    </li>
                </ul>
            </div>

            @endauth
        </div>
    </div>
</header>
