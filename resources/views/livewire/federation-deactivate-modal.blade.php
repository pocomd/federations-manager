{{--
    resources/views/livewire/federation-deactivate-modal.blade.php
    FederationDeactivateModal — 6-step wizard overlay (step 3 skipped when no pending entities).
--}}

<div>
@if($show)
<div class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:1055;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            {{-- Header --}}
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title">
                    <i class="bi bi-slash-circle me-2 text-warning"></i>
                    {{ __('app.action_deactivate') }}: {{ $this->federation?->name }}
                </h5>
                <button type="button" class="btn-close" wire:click="close"></button>
            </div>

            {{-- Step progress --}}
            <div class="modal-body border-bottom pb-0 pt-2">
                @php
                    $hasPending = $this->pendingEntities->isNotEmpty();
                    $steps = [
                        1 => __('app.deactivate_step_impact'),
                        2 => __('app.deactivate_step_active_entities'),
                        3 => __('app.deactivate_step_pending_entities'),
                        4 => __('app.deactivate_step_jobs'),
                        5 => __('app.deactivate_step_notify'),
                        6 => __('app.deactivate_step_confirm'),
                    ];
                    if (!$hasPending) unset($steps[3]);
                    $steps = array_values($steps);
                    $displayStep = $step;
                    if (!$hasPending && $step >= 4) $displayStep = $step - 1;
                    if (!$hasPending && $step === 3) $displayStep = 3; // shouldn't happen
                @endphp
                <div class="d-flex gap-1 mb-0" style="font-size:.72rem;">
                    @foreach($steps as $i => $label)
                        @php $n = $i + 1; @endphp
                        <div class="flex-fill text-center py-1 px-2 rounded-top
                            {{ $displayStep === $n ? 'bg-warning text-dark fw-semibold' : ($displayStep > $n ? 'bg-success text-white' : 'bg-light text-muted') }}">
                            {{ $n }}. {{ $label }}
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Body --}}
            <div class="modal-body">

                {{-- Step 1: Impact --}}
                @if($step === 1)
                    @if($this->federation)
                        <div class="alert alert-warning d-flex gap-2 align-items-center py-2 mb-3">
                            <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                            <div>
                                Deactivating <strong>{{ $this->federation->name }}</strong> will stop automatic metadata generation and affect all member entities.
                            </div>
                        </div>

                        <table class="table table-sm table-bordered mb-2" style="font-size:.8125rem;">
                            <tbody>
                                <tr>
                                    <th class="text-muted fw-normal w-40">Status</th>
                                    <td><span class="badge bg-success">{{ $this->federation->status }}</span></td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Active member entities</th>
                                    <td>{{ $this->activeEntities->count() }}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Pending requests</th>
                                    <td>{{ $this->pendingEntities->count() }}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">URI</th>
                                    <td class="font-monospace" style="font-size:.72rem;">{{ $this->federation->uri }}</td>
                                </tr>
                            </tbody>
                        </table>
                    @endif

                {{-- Step 2: Active Entities --}}
                @elseif($step === 2)
                <div wire:key="federation-suspend-membership" 
                     x-data="{ action: $wire.entangle('entityAction') }">
                    <p class="fw-semibold mb-2">
                        {{ __('app.deactivate_step_entities_label') }}
                        ({{ $this->activeEntities->count() }})
                    </p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio"
                               id="act_leave" value="leave" x-model="action">
                        <label class="form-check-label" for="act_leave">
                            {{ __('app.deactivate_leave') }}
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio"
                               id="act_disable" value="disable" x-model="action">
                        <label class="form-check-label" for="act_disable">
                            {{ __('app.deactivate_disable') }}
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio"
                               id="act_move" value="move" x-model="action">
                        <label class="form-check-label" for="act_move">
                            {{ __('app.deactivate_move') }}
                        </label>
                    </div>
                    <div x-show="action === 'move'"
                         x-cloak
                         class="mt-3 ps-3 border-start border-warning">
                        <label class="form-label fw-semibold small">
                            {{ __('app.deactivate_target_federation') }}
                        </label>
                        <select wire:model.live="targetFederationId"
                                class="form-select form-select-sm">
                            <option value="">— {{ __('app.select_federation') }} —</option>
                            @foreach($this->availableTargetFederations as $fed)
                                <option value="{{ $fed->id }}">{{ $fed->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($this->activeEntities->isNotEmpty())
                        <div class="table-responsive mt-3">
                            <p class="small text-muted mb-1">Affected entities ({{ $this->activeEntities->count() }}):</p>
                            <table class="table table-sm table-hover mb-0" style="font-size:.8rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Entity ID</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->activeEntities as $entity)
                                    <tr>
                                        <td>{{ $entity->getDisplayName() ?? $entity->entity_id }}</td>
                                        <td><span class="badge bg-secondary">{{ strtoupper($entity->type) }}</span></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Step 3: Pending Entities (only shown when they exist) --}}
                @elseif($step === 3)
                    <p class="fw-semibold mb-3">How should pending membership requests be handled?</p>

                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" id="pa-keep"
                                   wire:model="pendingAction" value="keep">
                            <label class="form-check-label" for="pa-keep">
                                {{ __('app.deactivate_pending_keep') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="pa-reject"
                                   wire:model="pendingAction" value="reject">
                            <label class="form-check-label" for="pa-reject">
                                {{ __('app.deactivate_pending_reject') }}
                            </label>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <p class="small text-muted mb-1">Pending requests ({{ $this->pendingEntities->count() }}):</p>
                        <table class="table table-sm table-hover mb-0" style="font-size:.8rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Entity ID</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->pendingEntities as $entity)
                                <tr>
                                    <td>{{ $entity->entity_id }}</td>
                                    <td><span class="badge bg-secondary">{{ strtoupper($entity->type) }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                {{-- Step 4: Jobs --}}
                @elseif($step === 4)
                    <div class="alert alert-info d-flex gap-2 py-2 mb-3">
                        <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
                        <div>
                            <strong>Automatic metadata generation will stop.</strong><br>
                            The scheduled <code>AutoGenerateMetadataJob</code> only targets federations with status <em>active</em>, so no further action is required — deactivating the federation removes it from the queue automatically.
                        </div>
                    </div>
                    <div class="alert alert-secondary py-2 mb-0">
                        <i class="bi bi-database me-1"></i>
                        The cached metadata file for this federation will be cleared immediately.
                    </div>

                {{-- Step 5: Notify --}}
                @elseif($step === 5)
                <div wire:key="federation-suspend-notify" x-data="{ notify: $wire.entangle('notifyOwners') }">
                    <p class="fw-semibold mb-3">Notify entity contacts about the federation deactivation?</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notify-owners"
                               x-model="notify">
                        <label class="form-check-label" for="notify-owners">
                            {{ __('app.deactivate_notify_owners') }}
                        </label>
                    </div>
                    <div class="ms-4" x-show="notify" x-cloak>
                        <label class="form-label small fw-semibold">Contact types to notify</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dct-technical"
                                   wire:model="notifyContactTypes" value="technical">
                            <label class="form-check-label" for="dct-technical">Technical contacts</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dct-admin"
                                   wire:model="notifyContactTypes" value="administrative">
                            <label class="form-check-label" for="dct-admin">Administrative contacts</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dct-support"
                                   wire:model="notifyContactTypes" value="support">
                            <label class="form-check-label" for="dct-support">Support contacts</label>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    wire:click="previewTemplate"
                                    wire:loading.attr="disabled"
                                    wire:target="previewTemplate">
                                <span wire:loading wire:target="previewTemplate"
                                      class="spinner-border spinner-border-sm me-1" role="status"></span>
                                <i class="bi bi-eye me-1" wire:loading.remove wire:target="previewTemplate"></i>
                                {{ __('app.mail_preview_title') }}
                            </button>
                        </div>
                    </div>
                </div>
                {{-- Step 6: Confirm --}}
                @elseif($step === 6)
                    <p class="fw-semibold mb-3">{{ __('app.deactivate_confirm_title') }}</p>

                    <table class="table table-sm table-bordered" style="font-size:.8125rem;">
                        <tbody>
                            <tr>
                                <th class="text-muted fw-normal w-40">Federation</th>
                                <td>{{ $this->federation?->name }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Active entities</th>
                                <td>
                                    @if($entityAction === 'leave') Leave as-is
                                    @elseif($entityAction === 'disable') Suspend all ({{ $this->activeEntities->count() }})
                                    @else
                                        @php $tf = $this->availableTargetFederations->firstWhere('id', $targetFederationId); @endphp
                                        Move to: {{ $tf?->name ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Pending requests</th>
                                <td>{{ $pendingAction === 'reject' ? 'Reject all (' . $this->pendingEntities->count() . ')' : 'Keep' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Notify contacts</th>
                                <td>
                                    @if($notifyOwners)
                                        Yes ({{ implode(', ', $notifyContactTypes) }})
                                    @else
                                        No
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-danger py-2 mb-0">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i>
                        This will immediately set the federation status to <strong>inactive</strong> and clear its metadata cache.
                    </div>
                @endif

            </div>

            {{-- Footer --}}
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="close">
                    {{ __('app.action_cancel') }}
                </button>

                @if($step > 1)
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="prevStep">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </button>
                @endif

                @if($step < 6)
                    <button type="button" class="btn btn-warning btn-sm" wire:click="nextStep">
                        Next <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                @else
                    <button type="button" class="btn btn-danger btn-sm"
                            wire:click="executeDeactivate"
                            wire:loading.attr="disabled"
                            wire:target="executeDeactivate">
                        <span wire:loading wire:target="executeDeactivate"
                              class="spinner-border spinner-border-sm me-1" role="status"></span>
                        <i class="bi bi-slash-circle me-1"></i> {{ __('app.action_deactivate') }}
                    </button>
                @endif
            </div>

        </div>
    </div>
</div>
@endif

{{-- ── Email preview modal ──────────────────────────────────────────────── --}}
@if($showPreview)
<div class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.35);z-index:1065;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-envelope me-2"></i>{{ __('app.mail_preview_title') }}
                </h5>
                <button type="button" class="btn-close" wire:click="closePreview"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="text-muted small fw-semibold mb-1">Subject</div>
                    <div class="border rounded px-3 py-2 bg-light font-monospace small">{{ $previewSubject }}</div>
                </div>
                <div>
                    <div class="text-muted small fw-semibold mb-1">Body</div>
                    <div class="border rounded px-3 py-2 bg-white small" style="white-space:pre-wrap;line-height:1.6">{!! nl2br(e($previewBody)) !!}</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="closePreview">
                    {{ __('app.action_cancel') }}
                </button>
                @if($previewTemplateId)
                <a href="{{ route('mail.templates.edit', $previewTemplateId) }}"
                   target="_blank"
                   class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i>Edit template
                </a>
                @endif
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        wire:click="previewTemplate"
                        wire:loading.attr="disabled"
                        wire:target="previewTemplate">
                    <span wire:loading wire:target="previewTemplate"
                          class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i class="bi bi-arrow-clockwise me-1" wire:loading.remove wire:target="previewTemplate"></i>
                    Refresh
                </button>
            </div>
        </div>
    </div>
</div>
@endif

</div>{{-- /livewire root --}}
