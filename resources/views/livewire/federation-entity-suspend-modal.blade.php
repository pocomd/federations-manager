<div>
@if($show)
<div class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:1055;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title">
                    <i class="bi bi-pause-circle me-2 text-warning"></i>
                    Suspend Entity
                </h5>
                <button type="button" class="btn-close" wire:click="close"></button>
            </div>

            <div class="modal-body">
                @if($this->entity)
                    <div class="alert alert-warning d-flex gap-2 align-items-center py-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                        <div>
                            Suspending <strong>{{ $this->entity->entity_id }}</strong> will remove it from metadata generation.
                        </div>
                    </div>

                    <table class="table table-sm table-bordered mb-4" style="font-size:.8125rem;">
                        <tbody>
                            <tr>
                                <th class="text-muted fw-normal w-40">Entity</th>
                                <td class="font-monospace" style="font-size:.75rem;">{{ $this->entity->entity_id }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Type</th>
                                <td>{{ strtoupper($this->entity->type) }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Federation</th>
                                <td>{{ $this->federation?->name ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif

                {{-- Notification --}}
                <div x-data="{ notify: $wire.entangle('notifyOwner') }">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="fed-suspend-notify"
                               x-model="notify">
                        <label class="form-check-label fw-semibold" for="fed-suspend-notify">
                            {{ __('app.suspend_notify_owner') }}
                        </label>
                    </div>

                    <div class="ms-4" x-show="notify" x-cloak>
                        <label class="form-label small fw-semibold mb-1">Contact types to notify</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="fsd-ct-technical"
                                   wire:model="notifyContactTypes" value="technical">
                            <label class="form-check-label" for="fsd-ct-technical">Technical contacts</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="fsd-ct-admin"
                                   wire:model="notifyContactTypes" value="administrative">
                            <label class="form-check-label" for="fsd-ct-admin">Administrative contacts</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="fsd-ct-support"
                                   wire:model="notifyContactTypes" value="support">
                            <label class="form-check-label" for="fsd-ct-support">Support contacts</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="close">
                    {{ __('app.action_cancel') }}
                </button>
                <button type="button" class="btn btn-danger btn-sm"
                        wire:click="confirm"
                        wire:loading.attr="disabled"
                        wire:target="confirm">
                    <span wire:loading wire:target="confirm"
                          class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i class="bi bi-pause-circle me-1" wire:loading.remove wire:target="confirm"></i>
                    {{ __('app.action_suspend') }}
                </button>
            </div>

        </div>
    </div>
</div>
@endif
</div>
