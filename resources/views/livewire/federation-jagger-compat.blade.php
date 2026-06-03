<div>
    <div class="card mb-4">
        <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
            <i class="bi bi-arrow-repeat me-1"></i> Jagger Compatibility Endpoint
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Enable a legacy endpoint at the Jagger URL pattern
                <code>/signedmetadata/federation/{name}/metadata.xml</code>
                so existing consumers do not need to reconfigure after migration.
            </p>

            <div class="mb-3 d-flex align-items-center gap-3">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox"
                           id="jagger_compat_enabled"
                           wire:model="jaggerCompatEnabled">
                    <label class="form-check-label small fw-semibold" for="jagger_compat_enabled">
                        Enable Jagger-compatible endpoint
                    </label>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold" for="jagger_fed_name">
                    Jagger federation name <span class="text-danger">*</span>
                </label>
                <input type="text" id="jagger_fed_name"
                       wire:model.live="jaggerFedName"
                       class="form-control form-control-sm @error('jaggerFedName') is-invalid @enderror"
                       placeholder="{{ $federation->slug }}"
                       pattern="[a-zA-Z0-9_\-]+"
                       title="Letters, numbers, hyphens and underscores only">
                <div class="form-text">Must match the federation name used in your existing Jagger URLs. Letters, numbers, hyphens and underscores only.</div>
                @error('jaggerFedName')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if($jaggerCompatEnabled)
                <div class="alert alert-info small py-2 mb-3">
                    <i class="bi bi-link-45deg me-1"></i>
                    Active endpoint:
                    <code>{{ url('/signedmetadata/federation/') }}/{{ $jaggerFedName ?: $federation->slug }}/metadata.xml</code>
                </div>
            @endif

            <button
                wire:click="save"
                wire:loading.attr="disabled"
                class="btn btn-sm btn-primary"
            >
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save" style="display:none;">
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    Saving…
                </span>
            </button>
        </div>
    </div>
</div>
