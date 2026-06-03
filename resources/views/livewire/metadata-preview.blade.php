{{--
    resources/views/livewire/metadata-preview.blade.php
    Two-panel metadata preview: validation results (left) + XML (right).
    When $entity is null shows only the live search box.
--}}
<div>

    {{-- ── Live entity search ─────────────────────────────────────────────── --}}
    <div class="mb-3"
         x-data="{ open: false }"
         @click.outside="open = false"
         style="position:relative;">

        <input type="text"
               wire:model.live.debounce.400ms="entitySearch"
               @focus="open = true"
               class="form-control form-control-sm"
               placeholder="Search by name or entityID...">

        @if($this->matchedEntities->isNotEmpty())
            <div x-show="open"
                 class="list-group shadow-sm"
                 style="position:absolute;top:100%;left:0;right:0;z-index:1050;max-height:260px;overflow-y:auto;">
                @foreach($this->matchedEntities as $result)
                    <button type="button"
                            class="list-group-item list-group-item-action py-2 px-3"
                            wire:click="selectEntity('{{ $result->id }}')"
                            @click="open = false">
                        <div class="fw-medium small">
                            {{ $result->getDisplayName('en') ?? $result->entity_id }}
                        </div>
                        <div class="text-muted font-monospace" style="font-size:.7rem;">
                            {{ $result->entity_id }}
                        </div>
                    </button>
                @endforeach
            </div>
        @endif

    </div>

    @if($entity !== null)

        {{-- Action bar --}}
        <div class="d-flex align-items-center gap-2 mb-3">

            {{-- Validate Again --}}
            <button class="btn btn-outline-primary btn-sm"
                    wire:click="runValidation"
                    wire:loading.attr="disabled"
                    wire:target="runValidation">
                <span wire:loading.remove wire:target="runValidation">
                    <i class="bi bi-arrow-repeat me-1"></i> Validate Again
                </span>
                <span wire:loading wire:target="runValidation">
                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                    Validating…
                </span>
            </button>

            {{-- Download XML --}}
            @if(!$xmlError && $xml)
                <a href="{{ route('entities.metadata', $entity) }}"
                   class="btn btn-outline-secondary btn-sm" target="_blank">
                    <i class="bi bi-download me-1"></i> Download XML
                </a>
            @endif

            {{-- Overall badge --}}
            @if($hasValidated)
                @if($overallPassed)
                    <span class="badge bg-success ms-1">
                        <i class="bi bi-check-circle me-1"></i> All checks passed
                    </span>
                @else
                    <span class="badge bg-danger ms-1">
                        <i class="bi bi-x-circle me-1"></i> Validation failed
                    </span>
                @endif
            @endif

        </div>

        {{-- Two-panel layout --}}
        <div class="row g-3">

            {{-- ── Left: Validation results ─────────────────────────────────── --}}
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
                        <span class="card-header-label"><i class="bi bi-clipboard2-check me-1"></i> Validation Checks</span>
                        <span wire:loading wire:target="runValidation"
                              class="spinner-border spinner-border-sm text-secondary" role="status"></span>
                    </div>
                    <div class="card-body p-0" style="overflow-y:auto;max-height:620px;">

                        @if(!$hasValidated)
                            <p class="text-muted text-center py-4">Running validation…</p>
                        @elseif(empty($checks))
                            <p class="text-muted text-center py-4">No checks available.</p>
                        @else

                            {{-- Errors --}}
                            @if(!empty($this->errorChecks))
                                <div class="px-3 pt-3 pb-1">
                                    <h6 class="text-danger small fw-semibold mb-2">
                                        <i class="bi bi-x-circle-fill me-1"></i>
                                        Errors ({{ count($this->errorChecks) }})
                                    </h6>
                                    @foreach($this->errorChecks as $check)
                                        <div class="d-flex align-items-start gap-2 mb-2">
                                            <i class="bi {{ $this->checkIcon($check['status']) }} mt-1 flex-shrink-0"></i>
                                            <div>
                                                <span class="badge bg-danger me-1">{{ $check['id'] }}</span>
                                                <span class="small">{{ $check['message'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <hr class="my-2">
                            @endif

                            {{-- Warnings --}}
                            @if(!empty($this->warningChecks))
                                <div class="px-3 pt-2 pb-1">
                                    <h6 class="text-warning small fw-semibold mb-2">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        Warnings ({{ count($this->warningChecks) }})
                                    </h6>
                                    @foreach($this->warningChecks as $check)
                                        <div class="d-flex align-items-start gap-2 mb-2">
                                            <i class="bi {{ $this->checkIcon($check['status']) }} mt-1 flex-shrink-0"></i>
                                            <div>
                                                <span class="badge bg-warning text-dark me-1">{{ $check['id'] }}</span>
                                                <span class="small">{{ $check['message'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <hr class="my-2">
                            @endif

                            {{-- Passed --}}
                            @if(!empty($this->passedChecks))
                                <div class="px-3 pt-2 pb-3">
                                    <h6 class="text-success small fw-semibold mb-2">
                                        <i class="bi bi-check-circle-fill me-1"></i>
                                        Passed ({{ count($this->passedChecks) }})
                                    </h6>
                                    @foreach($this->passedChecks as $check)
                                        <div class="d-flex align-items-start gap-2 mb-2">
                                            <i class="bi {{ $this->checkIcon($check['status']) }} mt-1 flex-shrink-0"></i>
                                            <div>
                                                <span class="badge bg-success me-1">{{ $check['id'] }}</span>
                                                <span class="small text-muted">{{ $check['message'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                        @endif
                    </div>
                </div>
            </div>

            {{-- ── Right: XML preview ───────────────────────────────────────── --}}
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
                        <span class="card-header-label"><i class="bi bi-file-code me-1"></i> EntityDescriptor XML</span>
                        @if(!$xmlError && $xml)
                            <span class="badge bg-secondary">{{ number_format(strlen($xml)) }} bytes</span>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if($xmlError)
                            <div class="alert alert-danger m-3">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <strong>XML render failed:</strong> {{ $xmlErrorMsg }}
                            </div>
                        @elseif($xml)
                            <pre class="mb-0 p-3 small text-break"
                                 style="background:#f8f9fa;max-height:620px;overflow:auto;white-space:pre-wrap;word-break:break-all;font-size:0.75rem;">{{ htmlspecialchars($xml) }}</pre>
                        @else
                            <p class="text-muted text-center py-4">No XML generated.</p>
                        @endif
                    </div>
                </div>
            </div>

        </div>

    @else

        <p class="text-muted text-center py-4">
            Search for an entity above to preview its metadata.
        </p>

    @endif

</div>
