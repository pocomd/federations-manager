{{--
    resources/views/livewire/entity-reactivate-modal.blade.php
    EntityReactivateModal — 4-step wizard overlay.
--}}

<div>
@if($show)
<div class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:1055;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            {{-- Header --}}
            <div class="modal-header bg-success-subtle">
                <h5 class="modal-title">
                    <i class="bi bi-play-circle me-2 text-success"></i>
                    {{ __('app.action_reactivate') }}: {{ $this->entity?->getDisplayName() ?? $this->entity?->entity_id }}
                </h5>
                <button type="button" class="btn-close" wire:click="close"></button>
            </div>

            {{-- Step progress --}}
            <div class="modal-body border-bottom pb-0 pt-2">
                <div class="d-flex gap-1 mb-0" style="font-size:.72rem;">
                    @foreach([
                        1 => __('app.reactivate_step_impact'),
                        2 => __('app.reactivate_step_federation'),
                        3 => __('app.reactivate_step_notify'),
                        4 => __('app.reactivate_step_confirm'),
                    ] as $n => $label)
                        <div class="flex-fill text-center py-1 px-2 rounded-top
                            {{ $step === $n ? 'bg-success text-white fw-semibold' : ($step > $n ? 'bg-success text-white opacity-75' : 'bg-light text-muted') }}">
                            {{ $n }}. {{ $label }}
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Body --}}
            <div class="modal-body">

                {{-- Step 1: Impact --}}
                @if($step === 1)
                    @if($this->entity)
                        <div class="alert alert-success d-flex gap-2 align-items-center py-2 mb-3">
                            <i class="bi bi-play-circle-fill fs-5 flex-shrink-0"></i>
                            <div>
                                Reactivating <strong>{{ $this->entity->entity_id }}</strong> will restore its active status.
                                The entity will <strong>not</strong> appear in any metadata feed until the federation admin re-approves membership.
                            </div>
                        </div>

                        <table class="table table-sm table-bordered mb-3" style="font-size:.8125rem;">
                            <tbody>
                                <tr>
                                    <th class="text-muted fw-normal w-40">Type</th>
                                    <td>{{ strtoupper($this->entity->type) }}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Current status</th>
                                    <td><span class="badge bg-danger">{{ $this->entity->status }}</span></td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Federation membership</th>
                                    <td>
                                        @if($this->existingFederation)
                                            <span class="fw-semibold">{{ $this->existingFederation->name }}</span>
                                            <span class="badge ms-1
                                                {{ $this->existingMembership->status === 'suspended' ? 'bg-warning text-dark' : ($this->existingMembership->status === 'pending' ? 'bg-info text-dark' : 'bg-secondary') }}">
                                                {{ $this->existingMembership->status }}
                                            </span>
                                        @else
                                            <span class="text-muted">None — must apply to a federation</span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        @if($this->entity->certificates->isNotEmpty())
                            @php $certIssues = $this->entity->certificates->filter(fn($c) => $c->not_after <= now()->addDays(30)); @endphp
                            @if($certIssues->isNotEmpty())
                                <div class="alert alert-warning py-2 mb-0">
                                    <i class="bi bi-key-fill me-1"></i>
                                    <strong>Certificate warning:</strong>
                                    {{ $certIssues->count() }} certificate(s) expire within 30 days — consider renewing before reactivation.
                                </div>
                            @else
                                <div class="d-flex align-items-center gap-1 text-success small">
                                    <i class="bi bi-patch-check-fill"></i>
                                    {{ $this->entity->certificates->count() }} certificate(s) healthy
                                </div>
                            @endif
                        @else
                            <div class="alert alert-danger py-2 mb-0">
                                <i class="bi bi-exclamation-octagon-fill me-1"></i>
                                No certificates registered — the entity will fail metadata validation.
                            </div>
                        @endif
                    @endif

                {{-- Step 2: Federation --}}
                @elseif($step === 2)
                    @php $m = $this->existingMembership; @endphp

                    @if($m && $m->status === 'pending')
                        <div class="alert alert-info d-flex gap-2 py-2 mb-0">
                            <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
                            <div>
                                A membership request for <strong>{{ $this->existingFederation?->name }}</strong>
                                is already <strong>pending</strong> federation admin approval.
                                No further action is needed — the entity will appear in metadata once approved.
                            </div>
                        </div>

                    @elseif($m && in_array($m->status, ['suspended', 'rejected']))
                        <p class="fw-semibold mb-3">{{ __('app.reactivate_federation_label') }}</p>

                        <div x-data="{ opt: $wire.entangle('membershipOption') }">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" id="opt-reapply"
                                       value="reapply" x-model="opt">
                                <label class="form-check-label" for="opt-reapply">
                                    {{ __('app.reactivate_reapply') }}<strong>{{ $this->existingFederation?->name }}</strong>
                                    <span class="badge bg-secondary ms-1" style="font-size:.7rem;">{{ $m->status }}</span>
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" id="opt-new"
                                       value="new" x-model="opt">
                                <label class="form-check-label" for="opt-new">
                                    {{ __('app.reactivate_new_federation') }}
                                </label>
                            </div>
                            <div x-show="opt === 'new'" x-cloak class="ps-3 border-start border-success">
                                <label class="form-label fw-semibold small">{{ __('app.reactivate_select_federation') }}</label>
                                <select wire:model.live="targetFederationId" class="form-select form-select-sm">
                                    <option value="">— {{ __('app.reactivate_select_federation') }} —</option>
                                    @foreach($this->availableFederations as $fed)
                                        <option value="{{ $fed->id }}">{{ $fed->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    @else
                        <div class="alert alert-warning d-flex gap-2 py-2 mb-3">
                            <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
                            <div>
                                This entity has no federation membership. An active entity outside any federation
                                will not appear in metadata. Select a federation to apply to.
                            </div>
                        </div>
                        <label class="form-label fw-semibold small">{{ __('app.reactivate_select_federation') }}</label>
                        <select wire:model.live="targetFederationId" class="form-select form-select-sm">
                            <option value="">— {{ __('app.reactivate_select_federation') }} —</option>
                            @foreach($this->availableFederations as $fed)
                                <option value="{{ $fed->id }}">{{ $fed->name }}</option>
                            @endforeach
                        </select>
                        @php $this->membershipOption = 'new'; @endphp
                    @endif

                {{-- Step 3: Notify --}}
                @elseif($step === 3)
                <div wire:key="reactivate-notify" x-data="{ notify: $wire.entangle('notifyOwner') }">
                    <p class="fw-semibold mb-3">Notify entity contacts about the reactivation?</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="reactivate-notify-check"
                               x-model="notify">
                        <label class="form-check-label" for="reactivate-notify-check">
                            {{ __('app.reactivate_notify_owner') }}
                        </label>
                    </div>
                    <div class="ms-4" x-show="notify" x-cloak>
                        <label class="form-label small fw-semibold">Contact types to notify</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rct-technical"
                                   wire:model="notifyContactTypes" value="technical">
                            <label class="form-check-label" for="rct-technical">Technical contacts</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rct-admin"
                                   wire:model="notifyContactTypes" value="administrative">
                            <label class="form-check-label" for="rct-admin">Administrative contacts</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rct-support"
                                   wire:model="notifyContactTypes" value="support">
                            <label class="form-check-label" for="rct-support">Support contacts</label>
                        </div>
                    </div>
                </div>

                {{-- Step 4: Confirm --}}
                @elseif($step === 4)
                    <p class="fw-semibold mb-3">{{ __('app.reactivate_confirm_title') }}</p>

                    <table class="table table-sm table-bordered mb-3" style="font-size:.8125rem;">
                        <tbody>
                            <tr>
                                <th class="text-muted fw-normal w-40">Entity</th>
                                <td>{{ $this->entity?->entity_id }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Federation request</th>
                                <td>
                                    @php $m = $this->existingMembership; @endphp
                                    @if($m && $m->status === 'pending')
                                        Already pending — <strong>{{ $this->existingFederation?->name }}</strong>
                                    @elseif($membershipOption === 'reapply')
                                        Re-request approval — <strong>{{ $this->existingFederation?->name }}</strong>
                                    @elseif($membershipOption === 'new')
                                        @php $tf = $this->availableFederations->firstWhere('id', $targetFederationId); @endphp
                                        New request — <strong>{{ $tf?->name ?? '—' }}</strong>
                                    @else
                                        No federation selected
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Notify contacts</th>
                                <td>
                                    @if($notifyOwner)
                                        Yes ({{ implode(', ', $notifyContactTypes) }})
                                    @else
                                        No
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-success py-2 mb-0">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        Entity status will be set to <strong>active</strong> immediately.
                        It will appear in federation metadata only after admin approval.
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
                        <i class="bi bi-arrow-left me-1"></i> {{ __('app.action_back') }}
                    </button>
                @endif

                @if($step < 4)
                    <button type="button" class="btn btn-success btn-sm" wire:click="nextStep">
                        {{ __('app.action_next') }} <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                @else
                    <button type="button" class="btn btn-success btn-sm"
                            wire:click="executeReactivate"
                            wire:loading.attr="disabled"
                            wire:target="executeReactivate">
                        <span wire:loading wire:target="executeReactivate"
                              class="spinner-border spinner-border-sm me-1" role="status"></span>
                        <i class="bi bi-play-circle me-1" wire:loading.remove wire:target="executeReactivate"></i>
                        {{ __('app.action_reactivate') }}
                    </button>
                @endif
            </div>

        </div>
    </div>
</div>
@endif
</div>{{-- /livewire root --}}
