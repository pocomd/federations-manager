@extends('layouts.app')

@section('title', 'Requested Attributes — ' . ($entity->getDisplayName() ?? $entity->entity_id))

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('entities.index') }}">Entities</a></li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('entities.show', $entity) }}">
                            {{ $entity->getDisplayName() ?? $entity->entity_id }}
                        </a>
                    </li>
                    <li class="breadcrumb-item active">Requested Attributes</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-1">Requested Attributes</h1>
            <p class="text-muted mb-0 small font-monospace">{{ $entity->entity_id }}</p>
        </div>
    </div>

    {{-- Current requested attributes --}}
    <div class="card border shadow-none mb-4">
        <div class="card-header bg-transparent border-bottom py-2">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-list-check me-2 text-muted"></i>
                Current Requested Attributes
            </h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Attribute</th>
                        <th>OID</th>
                        <th style="width:100px;">Required</th>
                        <th>Reason</th>
                        @can('entity.edit')
                        <th style="width:80px;">Action</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entity->entityRequestedAttributes as $ra)
                    <tr>
                        <td class="ps-3">
                            <div class="fw-medium small">{{ $ra->attributeDefinition->full_name }}</div>
                            <code class="text-muted" style="font-size:0.75rem;">{{ $ra->attributeDefinition->name }}</code>
                        </td>
                        <td>
                            @if ($ra->attributeDefinition->saml2_oid)
                                <code class="small text-muted">{{ $ra->attributeDefinition->saml2_oid }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($ra->is_required)
                                <span class="badge bg-danger">Required</span>
                            @else
                                <span class="badge bg-secondary">Optional</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $ra->reason ?? '—' }}</td>
                        @can('entity.edit')
                        <td>
                            <form method="POST"
                                  action="{{ route('entities.requested-attributes.destroy', [$entity, $ra]) }}"
                                  onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.entity.remove_title, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Remove">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                        @endcan
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No requested attributes defined for this entity.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add attribute form --}}
    @can('entity.edit')
    <div class="card border shadow-none" style="max-width:720px;">
        <div class="card-header bg-transparent border-bottom py-2">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-plus-circle me-2 text-muted"></i>
                Add Attribute
            </h6>
        </div>
        <div class="card-body">
            {{-- Schema filter --}}
            <div class="mb-3">
                <span class="text-muted small fw-semibold me-1">Filter by schema:</span>
                <div class="d-flex gap-1 flex-wrap mt-1">
                    <a href="{{ route('entities.requested-attributes', $entity) }}"
                       class="btn btn-sm {{ $schema === '' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        All ({{ $total }})
                    </a>
                    @foreach(['eduperson' => 'eduPerson', 'ldap' => 'LDAP', 'schac' => 'SCHAC', 'voperson' => 'voPerson'] as $key => $label)
                    <a href="{{ route('entities.requested-attributes', [$entity, 'schema' => $key]) }}"
                       class="btn btn-sm {{ $schema === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $label }} ({{ $counts[$key] ?? 0 }})
                    </a>
                    @endforeach
                </div>
            </div>

            @if ($available->isEmpty())
                <p class="text-muted mb-0">All active attribute definitions have already been added.</p>
            @else
            <form method="POST" action="{{ route('entities.requested-attributes.store', $entity) }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-medium" for="attribute_definition_id">Attribute</label>
                    <select class="form-select @error('attribute_definition_id') is-invalid @enderror"
                            id="attribute_definition_id"
                            name="attribute_definition_id"
                            required>
                        <option value="">— Select attribute —</option>
                        @foreach ($available as $attr)
                            <option value="{{ $attr->id }}" {{ old('attribute_definition_id') === $attr->id ? 'selected' : '' }}>
                                {{ $attr->full_name }} ({{ $attr->name }})
                            </option>
                        @endforeach
                    </select>
                    @error('attribute_definition_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_required" value="0">
                        <input class="form-check-input"
                               type="checkbox"
                               id="is_required"
                               name="is_required"
                               value="1"
                               {{ old('is_required') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_required">Required (must be released)</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium" for="reason">Reason / Justification</label>
                    <textarea class="form-control @error('reason') is-invalid @enderror"
                              id="reason"
                              name="reason"
                              rows="2"
                              placeholder="Why does this SP need this attribute?">{{ old('reason') }}</textarea>
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Add Attribute
                </button>
            </form>
            @endif
        </div>
    </div>
    @endcan

</div>
@endsection
