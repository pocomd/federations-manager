<div>

{{-- ══════════════════════════════════════════════════════════════════════════
     STEP 1 — Status + upload form (driver-specific partial)
═══════════════════════════════════════════════════════════════════════════ --}}
@if($step === 1)

@include('livewire.partials.' . $driverViewName)

{{-- ── Credential info modal ──────────────────────────────────────────────── --}}
@if($credentialModal !== null)
<div class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.45);z-index:1055;"
     @keydown.escape.window="$wire.closeModal()"
     x-init="$el.focus()">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                @if($credentialModal['type'] === 'key')
                    <h5 class="modal-title">
                        <i class="bi bi-key me-2 text-secondary"></i>Private Key Info
                    </h5>
                @else
                    <h5 class="modal-title">
                        <i class="bi bi-patch-check me-2 text-success"></i>Certificate Details
                    </h5>
                @endif
                <button type="button" class="btn-close" wire:click="closeModal"></button>
            </div>

            <div class="modal-body">

                @if($credentialModal['type'] === 'key')

                    <table class="table table-sm table-bordered small mb-3">
                        <tbody>
                            <tr>
                                <th class="text-muted fw-semibold bg-light" style="width:35%">Key type</th>
                                <td>{{ $credentialModal['key_type'] }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-semibold bg-light">Key size</th>
                                <td>{{ $credentialModal['bits'] }} bits</td>
                            </tr>
                            @if($credentialModal['created_at'])
                            <tr>
                                <th class="text-muted fw-semibold bg-light">Stored</th>
                                <td>{{ $credentialModal['created_at'] }}</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>

                    @if($credentialModal['preview'])
                    <p class="small text-muted mb-1">PEM preview — first and last 50 characters only:</p>
                    <pre class="bg-dark text-light rounded p-3 small font-monospace mb-0" style="word-break:break-all;white-space:pre-wrap;">{{ $credentialModal['preview'] }}</pre>
                    @endif

                @else

                    <table class="table table-sm table-bordered small mb-0">
                        <tbody>
                            <tr>
                                <th class="text-muted fw-semibold bg-light" style="width:35%">Subject</th>
                                <td class="font-monospace">{{ $credentialModal['subject'] }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-semibold bg-light">Issuer</th>
                                <td class="font-monospace">{{ $credentialModal['issuer'] }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-semibold bg-light">Serial</th>
                                <td class="font-monospace" style="word-break:break-all;">{{ $credentialModal['serial'] }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-semibold bg-light">Valid from</th>
                                <td>{{ $credentialModal['valid_from'] }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-semibold bg-light">Valid to</th>
                                <td>
                                    {{ $credentialModal['valid_to'] }}
                                    <span class="badge bg-{{ $credentialModal['validity_class'] }} ms-1">
                                        {{ $credentialModal['validity_label'] }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    @if($credentialModal['pem'])
                    <div class="mt-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <p class="small text-muted mb-0">Certificate PEM:</p>
                            <button type="button"
                                    class="copy-cmd-btn btn btn-sm btn-outline-secondary py-0 px-2"
                                    title="Copy PEM to clipboard"
                                    onclick="var btn=this,pre=btn.closest('div').nextElementSibling;copyToClipboard(pre.textContent.trim()).then(function(){btn.innerHTML='<i class=\'bi bi-clipboard-check text-success\'></i>';setTimeout(function(){btn.innerHTML='<i class=\'bi bi-clipboard\'></i>'},1500)})">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <pre class="bg-dark text-light rounded p-3 small font-monospace mb-0"
                             style="max-height:180px;overflow-y:auto;word-break:break-all;white-space:pre-wrap;">{{ $credentialModal['pem'] }}</pre>
                    </div>
                    @endif

                @endif

            </div>

            <div class="modal-footer">
                @if($credentialModal['type'] === 'cert' && $driverViewName === 'signing-keys-file')
                    <a href="{{ route('federations.signing-cert.download', $federation) }}"
                       class="btn btn-sm btn-outline-primary me-auto">
                        <i class="bi bi-download me-1"></i>Download .crt
                    </a>
                @endif
                <button type="button" class="btn btn-sm btn-secondary" wire:click="closeModal">Close</button>
            </div>

        </div>
    </div>
</div>
@endif

@endif {{-- step 1 --}}

{{-- ══════════════════════════════════════════════════════════════════════════
     STEP 2 — Upload the missing credential
═══════════════════════════════════════════════════════════════════════════ --}}
@if($step === 2)

@php
    $savedLabel   = $pendingType === 'key' ? 'private key' : 'certificate';
    $missingLabel = $pendingType === 'key' ? 'certificate' : 'private key';
@endphp

{{-- Step progress --}}
<div class="d-flex align-items-center gap-2 mb-4 small">
    <span class="badge bg-success rounded-pill px-3 py-2">
        <i class="bi bi-check me-1"></i> Step 1 — {{ ucfirst($savedLabel) }} ready
    </span>
    <i class="bi bi-chevron-right text-muted"></i>
    <span class="badge bg-primary rounded-pill px-3 py-2">
        Step 2 — Upload {{ $missingLabel }}
    </span>
</div>

<div class="card">
    <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
        <i class="bi bi-upload me-1"></i> Upload {{ ucfirst($missingLabel) }}
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            The <strong>{{ $savedLabel }}</strong> is ready. Now upload the matching
            <strong>{{ $missingLabel }}</strong> to complete the key pair.
            The app verifies that the two files form a valid matching pair before storing either.
        </p>

        <form wire:submit="complete">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">
                        {{ ucfirst($missingLabel) }} file
                    </label>
                    <input type="file"
                           wire:model="uploadedFile"
                           accept=".pem,.key,.crt,.cer,.p12,.pfx,.txt"
                           wire:loading.attr="disabled"
                           wire:target="uploadedFile,complete"
                           class="form-control form-control-sm @error('uploadedFile') is-invalid @enderror">
                    @error('uploadedFile')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">
                        Password <span class="text-muted fw-normal">(if encrypted)</span>
                    </label>
                    <input type="password"
                           wire:model="password"
                           wire:loading.attr="disabled"
                           wire:target="uploadedFile,complete"
                           class="form-control form-control-sm"
                           placeholder="Leave blank if not encrypted"
                           autocomplete="off">
                </div>
                <div class="col-md-2">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="uploadedFile,complete"
                            class="btn btn-success btn-sm w-100">
                        <span wire:loading.remove wire:target="uploadedFile,complete">
                            <i class="bi bi-check-circle me-1"></i> Save
                        </span>
                        <span wire:loading wire:target="complete">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Saving…
                        </span>
                    </button>
                </div>
            </div>
        </form>

        <div class="mt-3 pt-3 border-top">
            <button type="button" class="btn btn-sm btn-link text-muted p-0" wire:click="cancel">
                <i class="bi bi-x-circle me-1"></i>
                Cancel — discard the {{ $savedLabel }} and start over
            </button>
        </div>

    </div>
</div>

@endif {{-- step 2 --}}

</div>
