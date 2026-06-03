{{--
    resources/views/livewire/entity-suspend-modal.blade.php
    EntitySuspendModal — 4-step wizard overlay.
--}}

<div>
@if($show)
<div class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5);z-index:1055;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            {{-- Header --}}
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title">
                    <i class="bi bi-pause-circle me-2 text-warning"></i>
                    {{ __('app.action_suspend') }}: {{ $this->entity?->getDisplayName() ?? $this->entity?->entity_id }}
                </h5>
                <button type="button" class="btn-close" wire:click="close"></button>
            </div>

            {{-- Step progress --}}
            <div class="modal-body border-bottom pb-0 pt-2">
                <div class="d-flex gap-1 mb-0" style="font-size:.72rem;">
                    @foreach([1 => __('app.suspend_step_impact'), 2 => __('app.suspend_step_memberships'), 3 => __('app.suspend_step_notify'), 4 => __('app.suspend_step_confirm')] as $n => $label)
                        <div class="flex-fill text-center py-1 px-2 rounded-top
                            {{ $step === $n ? 'bg-warning text-dark fw-semibold' : ($step > $n ? 'bg-success text-white' : 'bg-light text-muted') }}">
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
                        <div class="alert alert-warning d-flex gap-2 align-items-center py-2 mb-3">
                            <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                            <div>
                                Suspending <strong>{{ $this->entity->entity_id }}</strong> will remove it from metadata generation and disable its federation membership.
                            </div>
                        </div>

                        <table class="table table-sm table-bordered mb-2" style="font-size:.8125rem;">
                            <tbody>
                                <tr>
                                    <th class="text-muted fw-normal w-40">Type</th>
                                    <td>{{ strtoupper($this->entity->type) }}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Status</th>
                                    <td><span class="badge bg-success">{{ $this->entity->status }}</span></td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Active federation</th>
                                    <td>{{ $this->activeFederations->count() }}</td>
                                </tr>
                            </tbody>
                        </table>

                        @if($this->activeFederations->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0" style="font-size:.8rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Federation</th>
                                            <th>URI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($this->activeFederations as $fed)
                                        <tr>
                                            <td>{{ $fed->name }}</td>
                                            <td class="font-monospace text-muted" style="font-size:.72rem;">{{ $fed->uri }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted small">This entity has no active federation membership.</p>
                        @endif
                    @endif

                {{-- Step 2: Memberships --}}
                @elseif($step === 2)
                 <div wire:key="entity-suspend-membership" x-data="{ action: $wire.entangle('membershipAction') }">
                    <p class="fw-semibold mb-3">How should the federation membership be handled?</p>
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" id="ma-disable"
                                   x-model="action" value="disable">
                            <label class="form-check-label" for="ma-disable">
                                {{ __('app.suspend_membership_disable') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="ma-move"
                                   wire:model="membershipAction" value="move">
                            <label class="form-check-label" for="ma-move">
                                {{ __('app.suspend_membership_move') }}
                            </label>
                        </div>
                    </div>
                    <div class="ms-4 mt-2" x-show="action === 'move'" x-cloak>
                        <label class="form-label small fw-semibold">Target federation</label>
                        <select class="form-select form-select-sm" wire:model="targetFederationId">
                            <option value="">{{ __('app.suspend_select_federation') }}</option>
                            @foreach($this->availableTargetFederations as $fed)
                                <option value="{{ $fed->id }}">{{ $fed->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Step 3: Notify --}}
                @elseif($step === 3)
                <div wire:key="entity-suspend-notify" x-data="{ notify: $wire.entangle('notifyOwner') }">
                    <p class="fw-semibold mb-3">Notify entity contacts about the suspension?</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notify-owner"
                               x-model="notify">
                        <label class="form-check-label" for="notify-owner">
                            {{ __('app.suspend_notify_owner') }}
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

                {{-- Step 4: Confirm --}}
                @elseif($step === 4)
                    <p class="fw-semibold mb-3">{{ __('app.suspend_confirm_title') }}</p>

                    <table class="table table-sm table-bordered" style="font-size:.8125rem;">
                        <tbody>
                            <tr>
                                <th class="text-muted fw-normal w-40">Entity</th>
                                <td>{{ $this->entity?->entity_id }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">{{ __('app.suspend_summary_memberships') }}</th>
                                <td>
                                    @if($membershipAction === 'disable')
                                        Suspend membership
                                    @elseif($membershipAction === 'move')
                                        @php $tf = $this->availableTargetFederations->firstWhere('id', $targetFederationId); @endphp
                                        Move to: {{ $tf?->name ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">{{ __('app.suspend_summary_notify') }}</th>
                                <td>
                                    @if($notifyOwner)
                                        {{ __('app.suspend_summary_yes') }} ({{ implode(', ', $notifyContactTypes) }})
                                    @else
                                        {{ __('app.suspend_summary_no') }}
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-danger py-2 mb-0">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i>
                        This action will immediately set the entity status to <strong>suspended</strong> and remove it from all active metadata feeds.
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

                @if($step < 4)
                    <button type="button" class="btn btn-warning btn-sm" wire:click="nextStep">
                        Next <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                @else
                    <button type="button" class="btn btn-danger btn-sm"
                            wire:click="executeSuspend"
                            wire:loading.attr="disabled"
                            wire:target="executeSuspend">
                        <span wire:loading wire:target="executeSuspend"
                              class="spinner-border spinner-border-sm me-1" role="status"></span>
                        <i class="bi bi-pause-circle me-1"></i> {{ __('app.action_suspend') }}
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
                @if($previewFederationNote)
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>{{ $previewFederationNote }}
                </div>
                @endif
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
