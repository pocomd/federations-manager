@php
    $p          = $migrationPreflight;
    $tokenLabel = 'jagger-fed-' . $federation->id;
    $keyPath    = storage_path("app/signing-keys/{$federation->id}/signing.key");
    $certPath   = storage_path("app/signing-keys/{$federation->id}/signing.crt");
    $lib        = config('federation.pkcs11_library', '/usr/lib/softhsm/libsofthsm2.so');
    $soConf     = config('federation.softhsm2_conf', '/etc/softhsm/softhsm2.conf');
@endphp

<div class="card border-secondary mt-4">
    <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi bi-arrow-left-right text-secondary"></i>
        <span class="fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">Migrate to SoftHSM2</span>
    </div>
    <div class="card-body">

        <p class="small text-muted mb-3">
            Import the existing PEM key pair into a SoftHSM2 PKCS#11 token and switch this federation to the
            hardware-token signing driver. The same certificate is reused — no update to metadata consumers is needed.
        </p>

        {{-- ── Pre-flight checks ──────────────────────────────────────────── --}}
        <h6 class="small fw-semibold text-secondary text-uppercase mb-2" style="letter-spacing:.04em;">Prerequisites</h6>

        <ul class="list-unstyled small mb-3">

            <li class="d-flex align-items-center gap-2 py-1 border-bottom">
                <span class="text-{{ $p['exec_ok'] ? 'success' : 'danger' }}">
                    <i class="bi bi-{{ $p['exec_ok'] ? 'check-circle-fill' : 'x-circle-fill' }}"></i>
                </span>
                <span class="flex-fill">Shell execution <span class="text-muted">(exec / proc_open)</span></span>
                <span class="text-{{ $p['exec_ok'] ? 'success' : 'danger' }} text-end" style="font-size:.75rem;">
                    {{ $p['exec_ok'] ? 'Available' : 'Blocked in php.ini' }}
                </span>
            </li>

            <li class="d-flex align-items-center gap-2 py-1 border-bottom">
                <span class="text-{{ $p['softhsm_bin'] ? 'success' : 'danger' }}">
                    <i class="bi bi-{{ $p['softhsm_bin'] ? 'check-circle-fill' : 'x-circle-fill' }}"></i>
                </span>
                <span class="flex-fill">softhsm2-util</span>
                <code class="text-{{ $p['softhsm_bin'] ? 'success' : 'danger' }}" style="font-size:.75rem;">
                    {{ $p['softhsm_bin'] ?? 'Not found' }}
                </code>
            </li>

            <li class="d-flex align-items-center gap-2 py-1 border-bottom">
                <span class="text-{{ $p['pkcs11_bin'] ? 'success' : 'danger' }}">
                    <i class="bi bi-{{ $p['pkcs11_bin'] ? 'check-circle-fill' : 'x-circle-fill' }}"></i>
                </span>
                <span class="flex-fill">pkcs11-tool</span>
                <code class="text-{{ $p['pkcs11_bin'] ? 'success' : 'danger' }}" style="font-size:.75rem;">
                    {{ $p['pkcs11_bin'] ?? 'Not found' }}
                </code>
            </li>

            <li class="d-flex align-items-center gap-2 py-1 border-bottom">
                <span class="text-{{ $p['pin_set'] ? 'success' : 'danger' }}">
                    <i class="bi bi-{{ $p['pin_set'] ? 'check-circle-fill' : 'x-circle-fill' }}"></i>
                </span>
                <span class="flex-fill">JAGGER_HSM_PIN</span>
                <span class="text-{{ $p['pin_set'] ? 'success' : 'danger' }}" style="font-size:.75rem;">
                    {{ $p['pin_set'] ? 'Set' : 'Not set in .env' }}
                </span>
            </li>

            <li class="d-flex align-items-center gap-2 py-1 border-bottom">
                <span class="text-{{ $p['conf_ok'] ? 'success' : 'danger' }}">
                    <i class="bi bi-{{ $p['conf_ok'] ? 'check-circle-fill' : 'x-circle-fill' }}"></i>
                </span>
                <span class="flex-fill">SOFTHSM2_CONF</span>
                <code class="text-{{ $p['conf_ok'] ? 'success' : 'danger' }}" style="font-size:.75rem;">
                    {{ $p['conf_ok'] ? $soConf : ($soConf ?: 'Not set') . ' (not readable)' }}
                </code>
            </li>

            <li class="d-flex align-items-center gap-2 py-1">
                <span class="text-{{ $p['lib_ok'] ? 'success' : 'danger' }}">
                    <i class="bi bi-{{ $p['lib_ok'] ? 'check-circle-fill' : 'x-circle-fill' }}"></i>
                </span>
                <span class="flex-fill">PKCS11_LIBRARY</span>
                <code class="text-{{ $p['lib_ok'] ? 'success' : 'danger' }}" style="font-size:.75rem;">
                    {{ $p['lib_ok'] ? $lib : ($lib ?: 'Not set') . ' (not found)' }}
                </code>
            </li>

        </ul>

        @if(! $p['exec_ok'])
        <div class="alert alert-warning small mb-3 d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
            <div>
                <strong>Shell execution is disabled.</strong>
                The wizard cannot run because <code>exec()</code> / <code>proc_open()</code> are listed in
                <code>disable_functions</code> in your <code>php.ini</code>. Either enable them and use the wizard,
                or follow the manual steps below.
            </div>
        </div>
        @elseif(! $p['wizard_ready'])
        <div class="alert alert-warning small mb-3 d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
            <div>
                <strong>One or more prerequisites are missing.</strong>
                Install the missing binaries or set the missing .env values, then reload the page.
                You can also perform the migration manually using the steps below.
            </div>
        </div>
        @endif

        {{-- ── Manual CLI steps (always visible) ─────────────────────────── --}}
        <div class="mb-3">
            <button class="btn btn-sm btn-outline-secondary w-100 text-start d-flex align-items-center gap-2"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#manualSteps-{{ $federation->id }}"
                    aria-expanded="false">
                <i class="bi bi-terminal me-1"></i>
                <span class="flex-fill small">Manual CLI migration steps</span>
                <i class="bi bi-chevron-down" style="font-size:.7rem;"></i>
            </button>
            <div class="collapse mt-2" id="manualSteps-{{ $federation->id }}">
                <div class="bg-dark text-light rounded p-3 font-monospace" style="font-size:.76rem;line-height:1.7;">
<span class="text-secondary"># Variables for this federation</span>
TOKEN_LABEL="{{ $tokenLabel }}"
KEY_FILE="{{ $keyPath }}"
CERT_FILE="{{ $certPath }}"
PKCS11_LIB="{{ $lib }}"
SOFTHSM2_CONF="{{ $soConf }}"
SOFTHSM2_CONF="{{ $soConf }}" softhsm2-util --show-slots
<br>
<span class="text-secondary"># 1. Initialise a SoftHSM2 token for this federation</span>
softhsm2-util --init-token --slot 0 \
  --label "${TOKEN_LABEL}" \
  --so-pin YOUR_SO_PIN \
  --pin YOUR_PIN
<br>
<span class="text-secondary"># 2. Import the private key</span>
softhsm2-util --import "${KEY_FILE}" \
  --token "${TOKEN_LABEL}" \
  --label "${TOKEN_LABEL}" \
  --id 01 \
  --pin YOUR_PIN
<br>
<span class="text-secondary"># 3. Find the assigned slot ID (note the number after "Slot")</span>
softhsm2-util --show-slots | grep -A8 "${TOKEN_LABEL}"
<br>
<span class="text-secondary"># 4. Convert cert PEM → DER and import it</span>
openssl x509 -in "${CERT_FILE}" -outform DER -out /tmp/signing.crt.der
pkcs11-tool --module "${PKCS11_LIB}" \
  --write-object /tmp/signing.crt.der --type cert \
  --label "${TOKEN_LABEL}" --id 01 \
  --token-label "${TOKEN_LABEL}" \
  --pin YOUR_PIN
rm /tmp/signing.crt.der
<br>
<span class="text-secondary"># 5. Register the token in the database (use the slot ID from step 3)</span>
php artisan tinker --execute="
  App\Models\SoftHsmToken::create([
    'federation_id' => '{{ $federation->id }}',
    'token_label'   => '{{ $tokenLabel }}',
    'slot_id'       => 'SLOT_ID_FROM_STEP_3',
  ]);
"
<br>
<span class="text-secondary"># 6. Write the pkcs11.cfg for xmlsectool</span>
php artisan tinker --execute="
  \$f = App\Models\Federation::find('{{ $federation->id }}');
  app(App\Services\Signing\SoftHsmSigningDriver::class)->healthCheck();
"
<br>
<span class="text-secondary"># 7. Flip the signing driver</span>
php artisan tinker --execute="
  App\Models\Federation::find('{{ $federation->id }}')
    ->update(['signing_driver' => 'softhsm']);
"
<br>
<span class="text-secondary"># 8. (Optional) Remove PEM files</span>
rm "${KEY_FILE}" "${CERT_FILE}"
                </div>
            </div>
        </div>

        {{-- ── Wizard ──────────────────────────────────────────────────────── --}}
        @if($migrationStep === 0)

            <div class="d-flex align-items-center gap-3 flex-wrap pt-2 border-top">
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input"
                           type="checkbox"
                           id="deletePem-{{ $federation->id }}"
                           wire:model="deletePemFiles">
                    <label class="form-check-label small" for="deletePem-{{ $federation->id }}">
                        Delete PEM files from disk after migration
                    </label>
                </div>

                <button type="button"
                        class="btn btn-sm btn-primary ms-auto"
                        {{ $p['wizard_ready'] ? '' : 'disabled' }}
                        wire:loading.attr="disabled"
                        wire:target="startMigration"
                        @click="SwalDefault.fire({
                            title: 'Migrate to SoftHSM2?',
                            html: 'The existing PEM key pair will be imported into a SoftHSM2 token and this federation\'s signing driver will be switched.<br><br>This cannot be undone from the UI.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, migrate',
                            confirmButtonColor: '#0d6efd'
                        }).then(r => { if (r.isConfirmed) $wire.startMigration() })">
                    <i class="bi bi-arrow-left-right me-1"></i>
                    Migrate to SoftHSM2
                </button>
            </div>

        @elseif($migrationStep === 1)

            <div class="border rounded p-3 bg-light mt-2">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                    <span class="small fw-semibold">Migrating…</span>
                </div>
                @foreach($migrationLog as [$status, $message])
                <div class="small {{ $status === 'ok' ? 'text-success' : 'text-danger' }} d-flex align-items-start gap-1">
                    <i class="bi bi-{{ $status === 'ok' ? 'check' : 'x' }}-circle-fill mt-1 flex-shrink-0"></i>
                    {{ $message }}
                </div>
                @endforeach
            </div>

        @elseif($migrationStep === 2)

            <div class="alert alert-success small d-flex align-items-start gap-2 mt-2 mb-0">
                <i class="bi bi-check-circle-fill mt-1 flex-shrink-0"></i>
                <div>
                    <strong>Migration complete.</strong>
                    This federation is now signing metadata using the SoftHSM2 driver.
                    The panel has switched to the SoftHSM2 view.
                </div>
            </div>

        @elseif($migrationStep === -1)

            <div class="border rounded p-3 bg-light mt-2">
                @foreach($migrationLog as [$status, $message])
                <div class="small {{ $status === 'ok' ? 'text-success' : 'text-danger' }} d-flex align-items-start gap-1 mb-1">
                    <i class="bi bi-{{ $status === 'ok' ? 'check' : 'x' }}-circle-fill mt-1 flex-shrink-0"></i>
                    {{ $message }}
                </div>
                @endforeach
            </div>
            <div class="d-flex justify-content-end mt-2">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        wire:click="resetMigration">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Try again
                </button>
            </div>

        @endif

    </div>
</div>
