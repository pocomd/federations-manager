<div>
    <div class="card">
        <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary d-flex align-items-center justify-content-between" style="letter-spacing:.04em;">
            <span><i class="bi bi-pen me-1"></i> Sign Metadata</span>
            <button wire:click="recheck" wire:loading.attr="disabled" wire:target="recheck"
                    class="btn btn-sm btn-link text-secondary p-0 ms-2" title="Refresh status"
                    style="font-size:.85rem; line-height:1;">
                <span wire:loading.remove wire:target="recheck"><i class="bi bi-arrow-clockwise"></i></span>
                <span wire:loading wire:target="recheck" style="display:none;">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                </span>
            </button>
        </div>
        <div class="card-body">

            {{-- Full aggregate status --}}
            @if($federation->metadata_generated_at)
                <p class="text-muted small mb-1">
                    <i class="bi bi-check-circle me-1 text-success"></i>
                    Full aggregate — last signed: {{ $federation->metadata_generated_at->diffForHumans() }}
                </p>
                <p class="text-muted small mb-1">
                    <i class="bi bi-calendar-check me-1"></i>
                    Valid until: {{ $validUntil->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                </p>
            @else
                <p class="text-warning small mb-1">
                    <i class="bi bi-exclamation-triangle me-1"></i>Full aggregate — not yet signed.
                </p>
            @endif

            {{-- eduGAIN subset status --}}
            @if($federation->metadata_edugain_generated_at)
                <p class="text-muted small mb-1">
                    <i class="bi bi-check-circle me-1 text-success"></i>
                    eduGAIN subset — last signed: {{ $federation->metadata_edugain_generated_at->diffForHumans() }}
                </p>
            @else
                <p class="text-warning small mb-1">
                    <i class="bi bi-exclamation-triangle me-1"></i>eduGAIN subset — not yet signed.
                </p>
            @endif

            {{-- Next auto-sign time --}}
            @if($nextSignAt)
                <p class="text-muted small mb-3"
                   title="Approximate — scheduler runs every {{ $autoGenerateInterval }} min on a fixed clock schedule">
                    <i class="bi bi-arrow-repeat me-1"></i>
                    Next auto-sign: {{ $nextSignAt->diffForHumans() }}
                </p>
            @else
                <p class="text-muted small mb-3"></p>
            @endif

            {{-- Staleness warnings (queue worker likely down) --}}
            @if($metadataStale)
                <div class="alert alert-warning small py-2 mb-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>Full aggregate appears stale</strong> — last signed {{ $federation->metadata_generated_at->diffForHumans() }},
                    expected every {{ $autoGenerateInterval }} min.
                    Check queue workers and scheduler on the <a href="{{ route('health.ui') }}" target="_blank">Health page</a>.
                </div>
            @endif
            @if($eduGainStale)
                <div class="alert alert-warning small py-2 mb-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>eduGAIN subset appears stale</strong> — last signed {{ $federation->metadata_edugain_generated_at->diffForHumans() }},
                    expected every {{ $autoGenerateInterval }} min.
                    Check queue workers and scheduler on the <a href="{{ route('health.ui') }}" target="_blank">Health page</a>.
                </div>
            @endif

            {{-- Job failure alerts --}}
            @if($metadataError)
                <div class="alert alert-danger small py-2 mb-2">
                    <i class="bi bi-x-circle me-1"></i>
                    <strong>Full aggregate signing failed:</strong> {{ $metadataError }}
                </div>
            @endif
            @if($eduGainError)
                <div class="alert alert-danger small py-2 mb-3">
                    <i class="bi bi-x-circle me-1"></i>
                    <strong>eduGAIN subset signing failed:</strong> {{ $eduGainError }}
                </div>
            @endif

            {{-- Signing key in use --}}
            @if($signingKeyLabel)
                <p class="text-muted small mb-3">
                    <i class="bi bi-key me-1"></i>
                    Will sign using: <strong>{{ $signingKeyLabel }}</strong>
                </p>
            @endif

            {{-- Validity window --}}
            <p class="text-muted small mb-3">
                <i class="bi bi-clock me-1"></i>
                Signed metadata will be valid for <strong>{{ $validUntilHours }} hours</strong>.
            </p>

            {{-- No signing key available --}}
            @if(! $canSign)
                <div class="alert alert-danger small py-2 mb-3">
                    <i class="bi bi-x-circle me-1"></i>
                    No signing key available. Upload a key pair in the <strong>Signing Keys</strong> tab.
                </div>
            @endif

            {{-- Empty federation notice --}}
            @if($entityCount === 0)
                <div class="alert alert-warning small py-2 mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    This federation has no entities — the signed metadata will be empty.
                </div>
            @endif

            @can('metadata.generate')

                @if(session('success'))
                    <div class="alert alert-success small py-2 mb-3">
                        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger small py-2 mb-3">
                        <i class="bi bi-x-circle me-1"></i>{{ session('error') }}
                    </div>
                @endif

                <button
                    wire:click="sign"
                    wire:loading.attr="disabled"
                    class="btn btn-primary btn-sm"
                    @disabled(! $canSign)
                >
                    <span wire:loading.remove wire:target="sign">
                        <i class="bi bi-pen me-1"></i>Sign metadata
                    </span>
                    <span wire:loading wire:target="sign" style="display:none;">
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        Signing…
                    </span>
                </button>

            @endcan

        </div>
    </div>
</div>
