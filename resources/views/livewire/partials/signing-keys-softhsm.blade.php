@php
    $tokenRecord = $federation->softHsmToken;
    $tokenInit   = $tokenRecord !== null;
    $pinSet      = (bool) config('federation.softhsm_pin');
    $pairOk      = $hasKey && $hasCert;
@endphp

{{-- Security notice --}}
<div class="alert alert-warning small d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-shield-exclamation mt-1 flex-shrink-0"></i>
    <div>
        <strong>Software token — security notice.</strong>
        Keys are stored in a SoftHSM2 software token. Anyone with root access to this server and the
        <code>JAGGER_HSM_PIN</code> value can sign metadata directly via xmlsectool,
        bypassing this application. The audit log is the only detection mechanism for such bypass.
    </div>
</div>

{{-- PIN configuration status --}}
@if(! $pinSet)
<div class="alert alert-danger small mb-4">
    <i class="bi bi-x-circle me-1"></i>
    <strong>JAGGER_HSM_PIN is not set in .env.</strong>
    Token operations will fail until the PIN is configured.
</div>
@endif

{{-- Status cards --}}
<div class="row g-3 mb-3">

    {{-- Token status card --}}
    <div class="col-sm-6">
        <div class="card border {{ $tokenInit ? 'border-success' : 'border-secondary' }} h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-hdd-stack fs-5 {{ $tokenInit ? 'text-success' : 'text-secondary' }}"></i>
                    <span class="fw-semibold small">HSM Token</span>
                    @if($tokenInit)
                        <span class="badge bg-success ms-auto">Initialised</span>
                    @else
                        <span class="badge bg-secondary ms-auto">Not initialised</span>
                    @endif
                </div>
                @if($tokenInit)
                    <div class="text-muted" style="font-size:.72rem;">
                        Label: <code>{{ $tokenRecord->token_label }}</code><br>
                        Slot: <code>{{ $tokenRecord->slot_id }}</code>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Private key card --}}
    <div class="col-sm-6">
        <div class="card border {{ $hasKey ? 'border-success' : 'border-secondary' }} h-100">
            <div class="card-body d-flex align-items-center justify-content-between py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-key fs-5 {{ $hasKey ? 'text-success' : 'text-secondary' }}"></i>
                    <span class="fw-semibold small">Private Key</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($hasKey)
                        <span class="badge bg-success">In token</span>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary py-0 px-2"
                                title="View key info"
                                wire:click="showKeyInfo"
                                wire:loading.attr="disabled">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    @else
                        <span class="badge bg-secondary">Not stored</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Certificate card --}}
    <div class="col-sm-6">
        @php
            $certExpiry = null;
            if ($hasCert && $credentialModal && $credentialModal['type'] === 'cert') {
                $validityClass = $credentialModal['validity_class'] ?? null;
                $validityLabel = $credentialModal['validity_label'] ?? null;
                if ($validityClass && $validityClass !== 'success') {
                    $certExpiry = ['class' => $validityClass, 'label' => $validityLabel];
                }
            }
            $certBorder = $hasCert ? ($certExpiry ? 'border-' . $certExpiry['class'] : 'border-success') : 'border-secondary';
            $certIcon   = $hasCert ? ($certExpiry ? 'text-' . $certExpiry['class'] : 'text-success') : 'text-secondary';
        @endphp
        <div class="card border {{ $certBorder }} h-100">
            <div class="card-body d-flex align-items-center justify-content-between py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-patch-check fs-5 {{ $certIcon }}"></i>
                    <span class="fw-semibold small">Certificate</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($hasCert)
                        @if($certExpiry)
                            <span class="badge bg-{{ $certExpiry['class'] }}">{{ $certExpiry['label'] }}</span>
                        @else
                            <span class="badge bg-success">In token</span>
                        @endif
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary py-0 px-2"
                                title="View certificate info"
                                wire:click="showCertInfo"
                                wire:loading.attr="disabled">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    @else
                        <span class="badge bg-secondary">Not stored</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Delete token button --}}
@if($tokenInit || $hasKey || $hasCert)
<div class="mb-4 d-flex justify-content-end">
    <button type="button"
            class="btn btn-sm btn-outline-danger"
            wire:loading.attr="disabled"
            @click="SwalDefault.fire({
                title: 'Delete token?',
                text: 'The SoftHSM2 token and all credentials stored inside it will be permanently deleted. This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete Token',
                confirmButtonColor: '#dc3545'
            }).then(r => { if (r.isConfirmed) $wire.deleteBoth() })">
        <i class="bi bi-trash me-1"></i> Delete Token
    </button>
</div>
@endif

{{-- Upload form --}}
@if(! $pairOk)
<div class="card">
    <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary d-flex align-items-center justify-content-between" style="letter-spacing:.04em;">
        <span><i class="bi bi-upload me-1"></i>Upload Key Pair</span>
        @if(! $pairOk && ($hasKey || $hasCert))
            <span class="badge bg-primary fw-normal text-lowercase" style="letter-spacing:0;">step 1 of 2</span>
        @elseif(! $pairOk && ! $hasKey && ! $hasCert)
            <span class="text-muted fw-normal" style="font-size:.72rem;letter-spacing:0;">Key is imported into the token; certificate also stored in token (not on disk)</span>
        @endif
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Upload the private key first — the token will be initialised automatically.
            Then upload the matching certificate; it will be imported into the token alongside the key.
        </p>
        <ul class="small text-muted mb-3">
            <li><strong>PEM private key</strong> — initialises the token and imports the key</li>
            <li><strong>PEM certificate</strong> — imports the certificate object into the existing token</li>
            <li><strong>PEM file with both / PKCS#12 bundle</strong> — both are imported in one step</li>
        </ul>

        <form wire:submit="detect">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">File</label>
                    <input type="file"
                           wire:model="uploadedFile"
                           accept=".pem,.key,.crt,.cer,.p12,.pfx,.txt"
                           wire:loading.attr="disabled"
                           wire:target="uploadedFile,detect"
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
                           wire:target="uploadedFile,detect"
                           class="form-control form-control-sm"
                           placeholder="Leave blank if not encrypted"
                           autocomplete="off">
                </div>
                <div class="col-md-2">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="uploadedFile,detect"
                            class="btn btn-primary btn-sm w-100">
                        <span wire:loading.remove wire:target="uploadedFile,detect">
                            <i class="bi bi-upload me-1"></i> Upload
                        </span>
                        <span wire:loading wire:target="detect">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Processing…
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@else
<div class="alert alert-secondary small d-flex align-items-start gap-2">
    <i class="bi bi-lock-fill mt-1 flex-shrink-0"></i>
    <div>
        A complete key pair is stored in the token. To replace it, delete the existing token first using the
        <strong>Delete Token</strong> button above.
    </div>
</div>
@endif
