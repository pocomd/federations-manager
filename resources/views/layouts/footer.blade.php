{{-- Footer — institutional style (eduGAIN portal reference) --}}
<footer class="mt-auto border-top bg-white py-2 px-4">
    <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">

        <div class="d-flex align-items-center gap-2 small text-muted">
            <i class="bi bi-shield-fill-check text-orange"></i>
            <span class="fw-semibold">{{ \App\Models\SystemPreference::get('app_name', config('app.name', 'Federation Registry')) }}</span>
            <span class="opacity-50">·</span>
            <span>v{{ config('app.version', '1.0') }}</span>
            @if(\App\Models\SystemPreference::get('footer_text'))
                <span class="opacity-50">·</span>
                <span>{{ \App\Models\SystemPreference::get('footer_text') }}</span>
            @endif
        </div>

        <div class="text-muted small">&copy; {{ date('Y') }} pocomd</div>

        <div class="d-flex align-items-center gap-3 small"></div>

    </div>
</footer>
