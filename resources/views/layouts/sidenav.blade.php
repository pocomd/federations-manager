{{-- Sidebar navigation — white/light, orange active accent (eduGAIN portal style) --}}

{{-- Brand ──────────────────────────────────────────────────────────────── --}}
<a href="{{ route('dashboard') }}" class="sidebar-brand" style="height: 3.5rem;">
    <i class="bi bi-shield-fill-check brand-icon"></i>
    <span>{{ \App\Models\SystemPreference::get('app_name', config('app.name', 'Federation Manager')) }}</span>
</a>

{{-- Close button — mobile only --}}
<div class="d-flex justify-content-end px-2 py-1 d-lg-none border-bottom">
    <button class="btn btn-sm btn-link text-muted p-1"
            @click="sidebarOpen = false"
            aria-label="Close sidebar">
        <i class="bi bi-x-lg"></i>
    </button>
</div>

{{-- Navigation ──────────────────────────────────────────────────────────── --}}
<nav class="py-2" aria-label="Sidebar navigation">
    <ul class="nav flex-column list-unstyled mb-0">

        {{-- ── Dashboard ──────────────────────────────────────────────── --}}
        <li>
            <a href="{{ route('dashboard') }}"
               class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-house"></i>
                <span>{{ __('app.nav_dashboard') }}</span>
            </a>
        </li>

        {{-- ── Entities (collapsible) ─────────────────────────────────── --}}
        <li x-data="{ open: {{ request()->routeIs('entities.*') ? 'true' : 'false' }} }">
            <div class="d-flex align-items-center">
                <a href="{{ route('entities.index') }}"
                   class="nav-link flex-grow-1 {{ request()->routeIs('entities.*') ? 'active' : '' }}">
                    <i class="bi bi-diagram-3"></i>
                    <span>{{ __('app.nav_entities') }}</span>
                </a>
                <button class="btn btn-link btn-sm p-1 pe-3 text-muted border-0"
                        @click="open = !open"
                        :aria-expanded="open">
                    <i class="bi fs-6" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </button>
            </div>
            <ul class="nav flex-column list-unstyled" x-show="open"
                @unless(request()->routeIs('entities.*')) style="display:none;" @endunless>
                <li>
                    <a href="{{ route('entities.index') }}?type=idp"
                       class="sub-link {{ request()->routeIs('entities.*') && request('type') === 'idp' ? 'active' : '' }}">
                        <i class="bi bi-server"></i> Identity Providers
                    </a>
                </li>
                <li>
                    <a href="{{ route('entities.index') }}?type=sp"
                       class="sub-link {{ request()->routeIs('entities.*') && request('type') === 'sp' ? 'active' : '' }}">
                        <i class="bi bi-app"></i> Service Providers
                    </a>
                </li>
            </ul>
        </li>

        {{-- ── Federations ─────────────────────────────────────────────── --}}
        <li>
            <a href="{{ route('federations.index') }}"
               class="nav-link {{ request()->routeIs('federations.*') ? 'active' : '' }}">
                <i class="bi bi-share"></i>
                <span>{{ __('app.nav_federations') }}</span>
            </a>
        </li>

        {{-- ── Certificates ─────────────────────────────────────────────── --}}
        <li>
            <a href="{{ route('certificates.monitor') }}"
               class="nav-link {{ request()->routeIs('certificates.*') ? 'active' : '' }}">
                <i class="bi bi-key"></i>
                <span>{{ __('app.nav_certificates') }}</span>
            </a>
        </li>

        {{-- ── Metadata ─────────────────────────────────────────────────── --}}
        @can('metadata.generate')
        <li>
            <a href="{{ route('metadata.index') }}"
               class="nav-link {{ request()->routeIs('metadata.*') ? 'active' : '' }}">
                <i class="bi bi-file-code"></i>
                <span>{{ __('app.nav_metadata') }}</span>
            </a>
        </li>
        @endcan

        {{-- ── Invitations ─────────────────────────────────────────── --}}
        @can('invitation.manage')
        <li>
            <a href="{{ route('invitations.index') }}"
               class="nav-link {{ request()->routeIs('invitations.*') ? 'active' : '' }}">
                <i class="bi bi-envelope-check"></i>
                <span>Invitations</span>
            </a>
        </li>
        <li>
            <a href="{{ route('invitation-requests.index') }}"
               class="nav-link {{ request()->routeIs('invitation-requests.*') ? 'active' : '' }}">
                <i class="bi bi-person-plus"></i>
                <span>Invitation Requests</span>
            </a>
        </li>
        @elsecan('invitation.view')
        <li>
            <a href="{{ route('invitations.index') }}"
               class="nav-link {{ request()->routeIs('invitations.index') ? 'active' : '' }}">
                <i class="bi bi-envelope-check"></i>
                <span>My Invitations</span>
            </a>
        </li>
        <li>
            <a href="{{ route('invitation-requests.index') }}"
               class="nav-link {{ request()->routeIs('invitation-requests.*') ? 'active' : '' }}">
                <i class="bi bi-person-plus"></i>
                <span>My Requests</span>
            </a>
        </li>
        @endcan

        {{-- ── Statistics ──────────────────────────────────────────── --}}
        @can('compliance.view')
        <li>
            <a href="{{ route('statistics.index') }}"
               class="nav-link {{ request()->routeIs('statistics.*') ? 'active' : '' }}">
                <i class="bi bi-bar-chart-line"></i>
                <span>Statistics</span>
            </a>
        </li>
        @endcan

        <li><div class="sidebar-divider"></div></li>
        <li><div class="nav-section-label">Admin</div></li>

        {{-- ── Mail Templates ──────────────────────────────────────────── --}}
        @can('federation.create')
        <li>
            <a href="{{ route('mail.templates.index') }}"
               class="nav-link {{ request()->routeIs('mail.templates.*') ? 'active' : '' }}">
                <i class="bi bi-envelope-paper"></i>
                <span>{{ __('app.nav_mail_templates') }}</span>
            </a>
        </li>
        @endcan

        {{-- ── Attributes ───────────────────────────────────────────────── --}}
        @can('entity.view')
        <li>
            <a href="{{ route('attributes.index') }}"
               class="nav-link {{ request()->routeIs('attributes.*') ? 'active' : '' }}">
                <i class="bi bi-tags"></i>
                <span>{{ __('app.nav_attributes') }}</span>
            </a>
        </li>
        @endcan

        {{-- ── Compliance Rules ────────────────────────────────────────── --}}
        @can('federation.create')
        <li>
            <a href="{{ route('rules.index') }}"
               class="nav-link {{ request()->routeIs('rules.*') ? 'active' : '' }}">
                <i class="bi bi-shield-check"></i>
                <span>{{ __('app.nav_rules') }}</span>
            </a>
        </li>
        @endcan

        {{-- ── Webhooks ─────────────────────────────────────────────────── --}}
        @can('federation.create')
        <li>
            <a href="{{ route('webhooks.index') }}"
               class="nav-link {{ request()->routeIs('webhooks.*') ? 'active' : '' }}">
                <i class="bi bi-broadcast"></i>
                <span>Webhooks</span>
            </a>
        </li>
        @endcan

        {{-- ── Import from Jagger ──────────────────────────────────────── --}}
        @if(config('federation.import_enabled'))
        @can('federation.create')
        <li>
            <a href="{{ route('import.jagger') }}"
               class="nav-link {{ request()->routeIs('import.*') ? 'active' : '' }}">
                <i class="bi bi-cloud-download"></i>
                <span>Import from Jagger</span>
            </a>
        </li>
        @endcan
        @endif

        {{-- ── Scheduler ───────────────────────────────────────────────── --}}
        @can('federation.create')
        <li>
            <a href="{{ route('scheduler.index') }}"
               class="nav-link {{ request()->routeIs('scheduler.*') ? 'active' : '' }}">
                <i class="bi bi-gear"></i>
                <span>{{ __('app.nav_scheduler') }}</span>
            </a>
        </li>
        @endcan

        {{-- ── Preferences ─────────────────────────────────────────────── --}}
        @can('federation.create')
        <li>
            <a href="{{ route('preferences.index') }}"
               class="nav-link {{ request()->routeIs('preferences.*') ? 'active' : '' }}">
                <i class="bi bi-sliders"></i>
                <span>{{ __('app.nav_preferences') }}</span>
            </a>
        </li>
        @endcan

        {{-- ── System Health ────────────────────────────────────────────── --}}
        @if(config('app.health_ui_enabled'))
        @can('federation.create')
        <li>
            <a href="{{ route('health.ui') }}"
               class="nav-link {{ request()->routeIs('health.ui') ? 'active' : '' }}">
                <i class="bi bi-heart-pulse"></i>
                <span>System Health</span>
            </a>
        </li>
        @endcan
        @endif

        {{-- ── Users ───────────────────────────────────────────────────── --}}
        @can('user.view')
        <li>
            <a href="{{ route('users.index') }}"
               class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i>
                <span>{{ __('app.nav_users') }}</span>
            </a>
        </li>

        {{-- ── Audit Log ────────────────────────────────────────────────── --}}
        <li>
            <a href="{{ route('audit.index') }}"
               class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}">
                <i class="bi bi-clock-history"></i>
                <span>{{ __('app.nav_audit') }}</span>
            </a>
        </li>
        @endcan

        <li><div class="sidebar-divider"></div></li>

        {{-- ── User Guide ──────────────────────────────────────────────────── --}}
        <li>
            <a href="{{ route('guide.index') }}"
               class="nav-link {{ request()->routeIs('guide.*') ? 'active' : '' }}">
                <i class="bi bi-book"></i>
                <span>User Guide</span>
            </a>
        </li>

    </ul>
</nav>
