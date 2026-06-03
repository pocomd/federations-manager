@php
    $pairOk = $hasKey && $hasCert;
@endphp

{{-- Intro --}}
<p class="text-muted small mb-3">
    Upload a private key and certificate for this federation. The app auto-detects the file format —
    upload a combined file (PEM or PKCS#12) to configure both at once, or upload them separately.
    Uploaded keys are stored on the server filesystem and used automatically when generating signed metadata.
</p>

{{-- Incomplete pair notice --}}
@if(! $pairOk)
<div class="alert alert-info small mb-4 d-flex align-items-start gap-2">
    <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
    <div>
        @if(! $hasKey && ! $hasCert)
            <strong>No key pair uploaded yet.</strong>
            Metadata for this federation will be generated <strong>unsigned</strong> until a key pair is uploaded.
        @elseif($hasKey && ! $hasCert)
            <strong>Private key uploaded — certificate is missing.</strong>
            The key pair is incomplete. Upload the matching certificate to enable signing.
        @elseif(! $hasKey && $hasCert)
            <strong>Certificate uploaded — private key is missing.</strong>
            The key pair is incomplete. Upload the matching private key to enable signing.
        @endif
    </div>
</div>
@endif

{{-- Status cards --}}
<div class="row g-3 mb-3">

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
                        <span class="badge bg-success">Stored</span>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary py-0 px-2"
                                title="View key info"
                                wire:click="showKeyInfo"
                                wire:loading.attr="disabled">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    @else
                        <span class="badge bg-secondary">Not uploaded</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Certificate card --}}
    <div class="col-sm-6">
        @php
            $certExpiry = null;
            if ($hasCert) {
                $certParsed = $credentialModal && $credentialModal['type'] === 'cert'
                    ? $credentialModal
                    : null;
                // Quick expiry check from disk without full certInfo call
                $certPath = storage_path("app/signing-keys/{$federation->id}/signing.crt");
                if (file_exists($certPath)) {
                    $ci = @openssl_x509_parse(file_get_contents($certPath));
                    if ($ci) {
                        $vt = $ci['validTo_time_t'] ?? null;
                        $dl = $vt ? (int)(($vt - time()) / 86400) : null;
                        if ($vt && $vt < time()) $certExpiry = ['class' => 'danger', 'label' => 'Expired'];
                        elseif ($dl !== null && $dl <= 30) $certExpiry = ['class' => 'warning', 'label' => $dl . 'd left'];
                        elseif ($dl !== null && $dl <= 90) $certExpiry = ['class' => 'info', 'label' => $dl . 'd left'];
                    }
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
                            <span class="badge bg-success">Stored</span>
                        @endif
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary py-0 px-2"
                                title="View certificate info"
                                wire:click="showCertInfo"
                                wire:loading.attr="disabled">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    @else
                        <span class="badge bg-secondary">Not uploaded</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Remove key pair button (only when pair is complete) --}}
@if($pairOk)
<div class="mb-4 d-flex justify-content-end">
    <button type="button"
            class="btn btn-sm btn-outline-danger"
            wire:loading.attr="disabled"
            @click="SwalDefault.fire({
                title: 'Remove key pair?',
                text: 'Both the signing key and certificate will be deleted. Metadata signing will be disabled.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Remove Both',
                confirmButtonColor: '#dc3545'
            }).then(r => { if (r.isConfirmed) $wire.deleteBoth() })">
        <i class="bi bi-trash me-1"></i> Remove Key Pair
    </button>
</div>
@endif

{{-- Upload form --}}
<div class="card {{ $pairOk ? 'opacity-75' : '' }}">
    <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary d-flex align-items-center justify-content-between" style="letter-spacing:.04em;">
        <span><i class="bi bi-upload me-1"></i>Upload Key Pair</span>
        @if(! $pairOk && ($hasKey || $hasCert))
            <span class="badge bg-primary fw-normal text-lowercase" style="letter-spacing:0;">step 1 of 2</span>
        @elseif(! $pairOk && ! $hasKey && ! $hasCert)
            <span class="text-muted fw-normal" style="font-size:.72rem;letter-spacing:0;">Uploading a single file will prompt for the other</span>
        @endif
    </div>
    <div class="card-body">

        @if($pairOk)
        <div class="alert alert-secondary small d-flex align-items-start gap-2 mb-3">
            <i class="bi bi-lock-fill mt-1 flex-shrink-0"></i>
            <div>
                A complete key pair is already uploaded. To replace it, remove the existing pair first using the
                <strong>Remove Key Pair</strong> button above.
            </div>
        </div>
        @else
        <p class="small text-muted mb-3">
            Upload a single file — the app detects the content automatically. Supported formats:
        </p>
        <ul class="small text-muted mb-3">
            <li><strong>PEM private key</strong> (<code>.key</code>, <code>.pem</code>) — you will be asked for the certificate next</li>
            <li><strong>PEM certificate</strong> (<code>.crt</code>, <code>.cer</code>, <code>.pem</code>) — you will be asked for the key next</li>
            <li><strong>PEM file with both</strong> — both are extracted and stored in one step</li>
            <li><strong>PKCS#12 bundle</strong> (<code>.p12</code>, <code>.pfx</code>) — both are extracted and stored in one step; enter the password if encrypted</li>
        </ul>
        @endif

        <form wire:submit="detect">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">File</label>
                    <input type="file"
                           wire:model="uploadedFile"
                           accept=".pem,.key,.crt,.cer,.p12,.pfx,.txt"
                           {{ $pairOk ? 'disabled' : '' }}
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
                           {{ $pairOk ? 'disabled' : '' }}
                           wire:loading.attr="disabled"
                           wire:target="uploadedFile,detect"
                           class="form-control form-control-sm"
                           placeholder="Leave blank if not encrypted"
                           autocomplete="off">
                </div>
                <div class="col-md-2">
                    <button type="submit"
                            {{ $pairOk ? 'disabled' : '' }}
                            wire:loading.attr="disabled"
                            wire:target="uploadedFile,detect"
                            class="btn btn-primary btn-sm w-100">
                        <span wire:loading.remove wire:target="uploadedFile,detect">
                            <i class="bi bi-upload me-1"></i> Upload
                        </span>
                        <span wire:loading wire:target="uploadedFile">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Uploading…
                        </span>
                        <span wire:loading wire:target="detect">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Detecting…
                        </span>
                    </button>
                </div>
            </div>
        </form>

    </div>
</div>

{{-- Migration wizard — only when SoftHSM2 driver is enabled, a full key pair exists,
     and the federation hasn't already been migrated --}}
@if($softHsmActive && $pairOk && $federation->signing_driver === 'file')
    @include('livewire.partials.signing-keys-migrate')
@endif
