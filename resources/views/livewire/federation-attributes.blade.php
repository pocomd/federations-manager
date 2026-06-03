<div>

    <div class="alert alert-info small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Attributes listed here are required or recommended for all
        Service Provider members of this federation.
    </div>

    {{-- Current attribute requirements ─────────────────────────────────── --}}
    @if($this->requiredAttributes->isEmpty())
        <p class="text-muted small">No federation-level attribute requirements defined.</p>
    @else
    <div class="card mb-4">
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered align-middle mb-0" style="table-layout:fixed;width:100%">
                <colgroup>
                    <col style="width:25%">  {{-- Attribute name --}}
                    <col style="width:22%">  {{-- OID --}}
                    <col style="width:110px"> {{-- Level --}}
                    <col>                    {{-- Notes (fills remaining) --}}
                    <col style="width:70px"> {{-- Remove --}}
                </colgroup>
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Attribute name</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">OID</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Level</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Notes</th>
                        <th class="pe-3 text-end fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Remove</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->requiredAttributes as $ra)
                    <tr>
                        <td class="ps-3" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="{{ $ra->attributeDefinition->name }}">{{ $ra->attributeDefinition->name }}</td>
                        <td style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"><code class="small" title="{{ $ra->attributeDefinition->saml2_oid }}">{{ $ra->attributeDefinition->saml2_oid }}</code></td>
                        <td>
                            @if($ra->is_required)
                                <span class="badge bg-danger">Required</span>
                            @else
                                <span class="badge bg-warning text-dark">Recommended</span>
                            @endif
                        </td>
                        <td class="small text-muted" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="{{ $ra->notes }}">{{ $ra->notes ?? '—' }}</td>
                        <td class="pe-3 text-end">
                            @can('federation.edit')
                            <button class="btn btn-sm btn-outline-danger"
                                    x-on:click="SwalDefault.fire({
                                        title: Lang.federation.remove_title,
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonText: Lang.confirm.confirm,
                                        confirmButtonColor: '#dc3545',
                                    }).then(r => { if (r.isConfirmed) $wire.removeAttribute('{{ $ra->id }}') })">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Add attribute form ───────────────────────────────────────────────── --}}
    @can('federation.edit')
    @if($this->availableAttributes->isNotEmpty())
    <div class="card">
        <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
            <i class="bi bi-plus-circle me-1"></i> Add Attribute Requirement
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Attribute</label>
                    <select wire:model="selectedAttributeId" class="form-select @error('selectedAttributeId') is-invalid @enderror" required>
                        <option value="">Select attribute...</option>
                        @foreach($this->availableAttributes as $attr)
                            <option value="{{ $attr->id }}">{{ $attr->name }} — {{ $attr->full_name }}</option>
                        @endforeach
                    </select>
                    @error('selectedAttributeId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Level</label>
                    <select wire:model="isRequired" class="form-select">
                        <option value="1">Required</option>
                        <option value="0">Recommended</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Notes <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" wire:model="notes" class="form-control"
                           placeholder="Why this attribute is needed">
                </div>
                <div class="col-md-2">
                    <button type="button" wire:click="addAttribute" class="btn btn-primary btn-sm w-100">
                        <span wire:loading wire:target="addAttribute" class="spinner-border spinner-border-sm me-1"></span>
                        Add
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endcan

</div>
