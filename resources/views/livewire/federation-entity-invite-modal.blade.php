<div>

    @if($showModal)

    {{-- Backdrop --}}
    <div class="modal-backdrop fade show" wire:click="close"></div>

    {{-- Modal --}}
    <div class="modal fade show d-block"
         tabindex="-1"
         x-on:keydown.escape.window="$wire.close()"
         style="z-index:1055;">
        <div class="modal-dialog modal-lg" wire:click.stop>
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-envelope-plus me-1"></i>
                        Invite {{ strtoupper($entityType) }}s to join federation
                    </h5>
                    <button type="button" class="btn-close" wire:click="close"></button>
                </div>

                <div class="modal-body">

                    {{-- Selected pills --}}
                    @if($this->selectedEntities->isNotEmpty())
                    <div class="mb-3">
                        <div class="fw-semibold small text-uppercase text-secondary mb-1" style="letter-spacing:.04em;">
                            Selected ({{ count($selectedIds) }})
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($this->selectedEntities as $sel)
                            <span class="badge bg-primary d-inline-flex align-items-center gap-1 py-1 px-2"
                                  style="font-size:.8rem;font-weight:500;">
                                {{ $sel->getDisplayName() ?? $sel->entity_id }}
                                <button type="button"
                                        wire:click="deselect('{{ $sel->id }}')"
                                        class="btn-close btn-close-white ms-1"
                                        style="font-size:.6rem;"
                                        aria-label="Remove"></button>
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Search --}}
                    <div class="mb-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text"
                                   wire:model.live.debounce.250ms="search"
                                   class="form-control"
                                   placeholder="Search by entityID or display name…"
                                   autocomplete="off">
                            @if($search)
                            <button class="btn btn-outline-secondary" type="button" wire:click="$set('search','')">
                                <i class="bi bi-x"></i>
                            </button>
                            @endif
                        </div>
                    </div>

                    {{-- Candidate list --}}
                    <div class="border rounded" style="max-height:260px;overflow-y:auto;">
                        @forelse($this->candidates as $entity)
                        @php $checked = in_array($entity->id, $selectedIds, true); @endphp
                        <label class="d-flex align-items-start gap-2 p-2 {{ !$loop->last ? 'border-bottom' : '' }}
                                      {{ $checked ? 'bg-primary-subtle' : '' }}"
                               style="cursor:pointer;">
                            <input type="checkbox"
                                   wire:click="toggle('{{ $entity->id }}')"
                                   {{ $checked ? 'checked' : '' }}
                                   class="form-check-input mt-1 flex-shrink-0">
                            <div class="overflow-hidden">
                                <div class="fw-semibold small text-truncate">
                                    {{ $entity->getDisplayName() ?? $entity->entity_id }}
                                </div>
                                <div class="text-muted font-monospace text-truncate" style="font-size:.7rem;">
                                    {{ $entity->entity_id }}
                                </div>
                            </div>
                        </label>
                        @empty
                        <div class="p-3 text-muted small text-center">
                            @if($search)
                                No matching entities found.
                            @elseif(empty($selectedIds))
                                No eligible {{ strtoupper($entityType) }}s — all are already in a federation or have a pending invitation.
                            @else
                                All eligible entities are already selected.
                            @endif
                        </div>
                        @endforelse
                    </div>

                    @error('selectedIds')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror

                    {{-- Note --}}
                    <div class="mt-3">
                        <label class="form-label fw-semibold small mb-1">
                            Note to entity managers
                            <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <textarea wire:model="note"
                                  class="form-control form-control-sm"
                                  rows="2"
                                  placeholder="Reason for the invitation…"
                                  maxlength="500"></textarea>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="close">Cancel</button>
                    <button type="button"
                            wire:click="send"
                            wire:loading.attr="disabled"
                            wire:target="send"
                            class="btn btn-primary btn-sm"
                            @disabled(empty($selectedIds))>
                        <span wire:loading wire:target="send" class="spinner-border spinner-border-sm me-1"></span>
                        Send {{ count($selectedIds) > 0 ? count($selectedIds) : '' }}
                        invitation{{ count($selectedIds) !== 1 ? 's' : '' }}
                    </button>
                </div>

            </div>
        </div>
    </div>

    @endif

</div>
