<div>

    {{-- Action buttons ─────────────────────────────────────────────────── --}}
    @can('federation.edit')
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('entities.create') }}?type=idp&federation={{ $federationId }}"
                   class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-plus me-1"></i>Add IdP directly
                </a>
                <a href="{{ route('entities.create') }}?type=sp&federation={{ $federationId }}"
                   class="btn btn-outline-success btn-sm">
                    <i class="bi bi-plus me-1"></i>Add SP directly
                </a>
                <button type="button"
                        onclick="Livewire.dispatch('open-invite-for', {type: 'idp'})"
                        class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-envelope-plus me-1"></i>Invite IdP
                </button>
                <button type="button"
                        onclick="Livewire.dispatch('open-invite-for', {type: 'sp'})"
                        class="btn btn-outline-success btn-sm">
                    <i class="bi bi-envelope-plus me-1"></i>Invite SP
                </button>
                <a href="{{ route('federations.mail.compose', $this->federation) }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-envelope me-1"></i>Send invitation
                </a>
            </div>
        </div>
    </div>

    @livewire('federation-entity-invite-modal', ['federationId' => $federationId])
    @endcan

    {{-- Pending approval ───────────────────────────────────────────────── --}}
    @if($this->pendingEntities->count() > 0)
    <div class="alert alert-warning d-flex align-items-center mb-3">
        <i class="bi bi-clock-history me-2"></i>
        <strong>{{ $this->pendingEntities->count() }} pending approval</strong>
    </div>
    <div class="card mb-4">
        <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
            <i class="bi bi-hourglass-split me-1 text-warning"></i> Pending Approval
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered align-middle mb-0" style="table-layout:fixed;width:100%">
                <colgroup>
                    <col style="width:22%">   {{-- Display name --}}
                    <col style="width:60px">  {{-- Type --}}
                    <col>                     {{-- entityID (fills remaining) --}}
                    <col style="width:110px"> {{-- Expires --}}
                    <col style="width:160px"> {{-- Actions --}}
                </colgroup>
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Display name</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Type</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">entityID</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Expires</th>
                        <th class="text-center fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->pendingEntities as $entity)
                    <tr>
                        <td class="ps-3" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">{{ $entity->getDisplayName() ?? $entity->entity_id }}</td>
                        <td>
                            @if($entity->type === 'idp')
                                <span class="badge bg-info text-dark">IdP</span>
                            @else
                                <span class="badge bg-success">SP</span>
                            @endif
                        </td>
                        <td class="small font-monospace text-muted" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="{{ $entity->entity_id }}">{{ $entity->entity_id }}</td>
                        <td class="small">
                            @if($entity->pivot->expires_at)
                                @php $expiresAt = \Carbon\Carbon::parse($entity->pivot->expires_at); @endphp
                                @if($expiresAt->isPast())
                                    <span class="text-danger fw-semibold" title="{{ $expiresAt->format('Y-m-d H:i') }}">Expired</span>
                                @elseif($expiresAt->diffInHours(now()) <= 24)
                                    <span class="text-warning fw-semibold" title="{{ $expiresAt->format('Y-m-d H:i') }}">{{ $expiresAt->diffForHumans() }}</span>
                                @else
                                    <span class="text-muted" title="{{ $expiresAt->format('Y-m-d H:i') }}">{{ $expiresAt->format('d M') }}</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                @can('federation.approveRequest')
                                <button class="btn btn-sm btn-success"
                                        x-on:click="SwalDefault.fire({
                                            title: 'Approve entity?',
                                            icon: 'question',
                                            showCancelButton: true,
                                            confirmButtonText: Lang.confirm.confirm,
                                            confirmButtonColor: '#198754',
                                        }).then(r => { if (r.isConfirmed) $wire.approveEntity('{{ $entity->id }}') })">
                                    <i class="bi bi-check-lg"></i> Approve
                                </button>
                                @endcan
                                @can('federation.rejectRequest')
                                <button type="button" class="btn btn-sm btn-danger"
                                        x-on:click="SwalDefault.fire({
                                            title: Lang.federation.reject_title,
                                            input: 'textarea',
                                            inputLabel: Lang.federation.reject_input_label,
                                            inputPlaceholder: Lang.federation.reject_placeholder,
                                            icon: 'question',
                                            showCancelButton: true,
                                            confirmButtonText: Lang.confirm.confirm,
                                            confirmButtonColor: '#dc3545',
                                            inputValidator: function(v) { return !v && Lang.federation.reject_required; },
                                        }).then(r => { if (r.isConfirmed) $wire.rejectEntity('{{ $entity->id }}', r.value) })">
                                    <i class="bi bi-x-lg"></i> Reject
                                </button>
                                @endcan
                                <a href="{{ route('entities.show', $entity) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Identity Providers ──────────────────────────────────────────────── --}}
    <h6 class="fw-semibold mt-3">
        <i class="bi bi-server me-1 text-primary"></i>
        Identity Providers ({{ $this->idps->count() }})
    </h6>
    @if($this->idps->isEmpty())
        <p class="text-muted small">No Identity Providers in this federation.</p>
    @else
    <div class="card mb-4">
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered align-middle mb-0" style="table-layout:fixed;width:100%">
                <colgroup>
                    <col style="width:40px">  {{-- # --}}
                    <col style="width:22%">   {{-- Display name --}}
                    <col>                     {{-- entityID (fills remaining) --}}
                    <col style="width:95px">  {{-- Status --}}
                    <col style="width:115px"> {{-- Actions --}}
                </colgroup>
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">#</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Display name</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">entityID</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Status</th>
                        <th class="text-center fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->idps as $i => $entity)
                    <tr>
                        <td class="ps-3 text-muted small">{{ $i + 1 }}</td>
                        <td style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="{{ $entity->getDisplayName() ?? $entity->entity_id }}">{{ $entity->getDisplayName() ?? '—' }}</td>
                        <td class="small font-monospace text-muted" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="{{ $entity->entity_id }}">{{ $entity->entity_id }}</td>
                        <td>
                            @if($entity->status === 'suspended')
                                <span class="badge bg-warning text-dark">Suspended</span>
                            @else
                                <span class="badge bg-success">Active</span>
                            @endif
                            @if($entity->edugain)
                                @if($entity->certificates->isEmpty())
                                    <span class="badge bg-danger ms-1" title="Excluded from eduGAIN feed — no certificates"><i class="bi bi-exclamation-triangle-fill"></i> eduGAIN</span>
                                @elseif(!$entity->hasSecurityContact())
                                    <span class="badge bg-danger ms-1" title="Excluded from eduGAIN feed — no security contact"><i class="bi bi-exclamation-triangle-fill"></i> eduGAIN</span>
                                @endif
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <a href="{{ route('entities.show', $entity) }}"
                                   class="btn btn-sm btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('entity.edit')
                                @if($entity->status !== 'suspended')
                                <button class="btn btn-sm btn-outline-warning"
                                        title="Suspend entity"
                                        x-on:click="$dispatch('open-federation-suspend', { entityId: '{{ $entity->id }}', federationId: '{{ $federationId }}' })">
                                    <i class="bi bi-pause-circle"></i>
                                </button>
                                @else
                                <button class="btn btn-sm btn-outline-success"
                                        title="Enable entity"
                                        x-on:click="SwalDefault.fire({
                                            title: 'Enable entity?',
                                            icon: 'question',
                                            showCancelButton: true,
                                            confirmButtonText: Lang.confirm.confirm,
                                            confirmButtonColor: '#198754',
                                        }).then(r => { if (r.isConfirmed) $wire.enableEntity('{{ $entity->id }}') })">
                                    <i class="bi bi-play-circle"></i>
                                </button>
                                @endif
                                @endcan
                                @can('entity.removeFromFederation')
                                @if($entity->status === 'suspended')
                                <button class="btn btn-sm btn-outline-danger" title="Remove from federation"
                                        x-on:click="SwalDefault.fire({
                                            title: Lang.federation.remove_title,
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonText: Lang.confirm.confirm,
                                            confirmButtonColor: '#dc3545',
                                        }).then(r => { if (r.isConfirmed) $wire.removeEntity('{{ $entity->id }}') })">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @else
                                <button class="btn btn-sm btn-outline-danger" disabled
                                        title="Suspend the entity first to remove it">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Service Providers ───────────────────────────────────────────────── --}}
    <h6 class="fw-semibold mt-3">
        <i class="bi bi-app me-1 text-success"></i>
        Service Providers ({{ $this->sps->count() }})
    </h6>
    @if($this->sps->isEmpty())
        <p class="text-muted small">No Service Providers in this federation.</p>
    @else
    <div class="card mb-4">
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered align-middle mb-0" style="table-layout:fixed;width:100%">
                <colgroup>
                    <col style="width:40px">  {{-- # --}}
                    <col style="width:22%">   {{-- Display name --}}
                    <col>                     {{-- entityID (fills remaining) --}}
                    <col style="width:95px">  {{-- Status --}}
                    <col style="width:115px"> {{-- Actions --}}
                </colgroup>
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">#</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Display name</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">entityID</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Status</th>
                        <th class="text-center fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->sps as $i => $entity)
                    <tr>
                        <td class="ps-3 text-muted small">{{ $i + 1 }}</td>
                        <td style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="{{ $entity->getDisplayName() ?? $entity->entity_id }}">{{ $entity->getDisplayName() ?? '—' }}</td>
                        <td class="small font-monospace text-muted" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="{{ $entity->entity_id }}">{{ $entity->entity_id }}</td>
                        <td>
                            @if($entity->status === 'suspended')
                                <span class="badge bg-warning text-dark">Suspended</span>
                            @else
                                <span class="badge bg-success">Active</span>
                            @endif
                            @if($entity->edugain)
                                @if($entity->certificates->isEmpty())
                                    <span class="badge bg-danger ms-1" title="Excluded from eduGAIN feed — no certificates"><i class="bi bi-exclamation-triangle-fill"></i> eduGAIN</span>
                                @elseif(!$entity->hasSecurityContact())
                                    <span class="badge bg-danger ms-1" title="Excluded from eduGAIN feed — no security contact"><i class="bi bi-exclamation-triangle-fill"></i> eduGAIN</span>
                                @endif
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <a href="{{ route('entities.show', $entity) }}"
                                   class="btn btn-sm btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('entity.edit')
                                @if($entity->status !== 'suspended')
                                <button class="btn btn-sm btn-outline-warning"
                                        title="Suspend entity"
                                        x-on:click="$dispatch('open-federation-suspend', { entityId: '{{ $entity->id }}', federationId: '{{ $federationId }}' })">
                                    <i class="bi bi-pause-circle"></i>
                                </button>
                                @else
                                <button class="btn btn-sm btn-outline-success"
                                        title="Enable entity"
                                        x-on:click="SwalDefault.fire({
                                            title: 'Enable entity?',
                                            icon: 'question',
                                            showCancelButton: true,
                                            confirmButtonText: Lang.confirm.confirm,
                                            confirmButtonColor: '#198754',
                                        }).then(r => { if (r.isConfirmed) $wire.enableEntity('{{ $entity->id }}') })">
                                    <i class="bi bi-play-circle"></i>
                                </button>
                                @endif
                                @endcan
                                @can('entity.removeFromFederation')
                                @if($entity->status === 'suspended')
                                <button class="btn btn-sm btn-outline-danger" title="Remove from federation"
                                        x-on:click="SwalDefault.fire({
                                            title: Lang.federation.remove_title,
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonText: Lang.confirm.confirm,
                                            confirmButtonColor: '#dc3545',
                                        }).then(r => { if (r.isConfirmed) $wire.removeEntity('{{ $entity->id }}') })">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @else
                                <button class="btn btn-sm btn-outline-danger" disabled
                                        title="Suspend the entity first to remove it">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Pending invitations ─────────────────────────────────────────────── --}}
    @if($this->pendingInvitations->count() > 0)
    <h6 class="fw-semibold mt-3">
        <i class="bi bi-envelope-open me-1 text-primary"></i>
        Pending Invitations ({{ $this->pendingInvitations->count() }})
    </h6>
    <div class="card mb-4">
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered align-middle mb-0" style="table-layout:fixed;width:100%">
                <colgroup>
                    <col style="width:40px">  {{-- # --}}
                    <col style="width:20%">   {{-- Display name --}}
                    <col>                     {{-- entityID (fills remaining) --}}
                    <col style="width:60px">  {{-- Type --}}
                    <col style="width:110px"> {{-- Invited --}}
                    <col style="width:90px">  {{-- Actions --}}
                </colgroup>
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">#</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Display name</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">entityID</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Type</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Invited</th>
                        <th class="text-center fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->pendingInvitations as $i => $inv)
                    <tr>
                        <td class="ps-3 text-muted small">{{ $i + 1 }}</td>
                        <td style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="{{ $inv->entity->getDisplayName() ?? $inv->entity->entity_id }}">{{ $inv->entity->getDisplayName() ?? '—' }}</td>
                        <td class="small font-monospace text-muted" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="{{ $inv->entity->entity_id }}">{{ $inv->entity->entity_id }}</td>
                        <td>
                            @if($inv->entity->type === 'idp')
                                <span class="badge bg-info text-dark">IdP</span>
                            @else
                                <span class="badge bg-success">SP</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $inv->created_at->diffForHumans() }}</td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <a href="{{ route('entities.show', $inv->entity) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('update', $this->federation)
                                <button class="btn btn-sm btn-outline-danger"
                                        title="Cancel invitation"
                                        x-on:click="SwalDefault.fire({
                                            title: 'Cancel invitation?',
                                            text: 'The entity manager will no longer be able to accept it.',
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonText: 'Yes, cancel it',
                                            confirmButtonColor: '#dc3545',
                                        }).then(r => { if (r.isConfirmed) $wire.cancelInvitation('{{ $inv->id }}') })">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
