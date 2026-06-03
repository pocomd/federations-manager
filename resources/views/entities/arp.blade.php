@extends('layouts.app')

@section('title', 'Attribute Release Policy — ' . ($entity->getDisplayName() ?? $entity->entity_id))

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
                    <li class="breadcrumb-item active">ARP</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-1">Attribute Release Policy</h1>
            <p class="text-muted mb-0 small">
                Configure which attributes this IdP releases to each Service Provider.
            </p>
        </div>
    </div>

    {{-- Schema filter --}}
    <div class="card card-body p-2 mb-3">
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="text-muted small fw-semibold">Filter attributes by schema:</span>
            <a href="{{ route('entities.arp', $entity) }}"
               class="btn btn-sm {{ $schema === '' ? 'btn-primary' : 'btn-outline-secondary' }}">
                All
            </a>
            @foreach(['eduperson' => 'eduPerson', 'ldap' => 'LDAP', 'schac' => 'SCHAC', 'voperson' => 'voPerson'] as $key => $label)
            <a href="{{ route('entities.arp', [$entity, 'schema' => $key]) }}"
               class="btn btn-sm {{ $schema === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }} ({{ $attrCounts[$key] ?? 0 }})
            </a>
            @endforeach
        </div>
    </div>

    @if ($orphanedRules->isNotEmpty())
    <div class="card border-warning shadow-none mb-3">
        <div class="card-header bg-warning-subtle border-warning py-2 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-warning"></i>
            <strong class="small">Stale ARP rules</strong>
            <span class="small text-muted">— these SPs no longer share a federation with this IdP</span>
        </div>
        <div class="card-body p-0">
            @foreach($orphanedRules as $spId => $rules)
            @php $spName = $rules->first()->sp?->getDisplayName() ?? $rules->first()->sp?->entity_id ?? $spId; @endphp
            <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                <div class="fw-semibold small mb-2">{{ $spName }}</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($rules as $rule)
                    @can('arp.edit')
                    <form method="POST" action="{{ route('entities.arp.destroy', [$entity, $rule]) }}"
                          onsubmit="return confirm('Remove this stale rule?')" class="d-inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                            <i class="bi bi-trash3 me-1"></i>{{ $rule->attributeDefinition?->name ?? 'Unknown attribute' }}
                        </button>
                    </form>
                    @endcan
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if ($sps->isEmpty())
    <div class="alert alert-info">
        No Service Providers are currently in the same federation(s) as this IdP.
    </div>
    @else

    @foreach ($sps as $sp)
    @php
        $spDisplayName = $sp->getDisplayName() ?? $sp->entity_id;
        $spRules = $arpRules->get($sp->id, collect());
        $spAttrs = $sp->entityRequestedAttributes;
        if ($schema !== '') {
            $spAttrs = $spAttrs->filter(
                fn($ra) => ($ra->attributeDefinition?->schema ?? '') === $schema
            );
        }
    @endphp

    <div class="card border shadow-none mb-4">
        <div class="card-header bg-transparent border-bottom py-2">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-semibold">{{ $spDisplayName }}</h6>
                    <code class="text-muted" style="font-size:0.75rem;">{{ $sp->entity_id }}</code>
                </div>
                @can('arp.edit')
                <div class="d-flex gap-2">
                    {{-- Permit All --}}
                    @if ($spAttrs->isNotEmpty())
                    <form method="POST" action="{{ route('entities.arp.store', $entity) }}">
                        @csrf
                        @foreach ($spAttrs as $ra)
                            <input type="hidden" name="batch[{{ $loop->index }}][sp_entity_id]" value="{{ $sp->id }}">
                            <input type="hidden" name="batch[{{ $loop->index }}][attribute_definition_id]" value="{{ $ra->attribute_definition_id }}">
                            <input type="hidden" name="batch[{{ $loop->index }}][is_permitted]" value="1">
                        @endforeach
                    </form>
                    @endif
                </div>
                @endcan
            </div>
        </div>

        <div class="card-body p-0">
            @if ($spAttrs->isEmpty())
                <p class="text-muted small p-3 mb-0">This SP has no requested attributes defined.</p>
            @else
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Attribute</th>
                        <th>OID</th>
                        <th style="width:110px;">SP Requires</th>
                        <th style="width:120px;">Permitted</th>
                        <th>Notes</th>
                        @can('arp.edit')
                        <th style="width:110px;">Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($spAttrs as $ra)
                    @php
                        $existingRule = $spRules->where('attribute_definition_id', $ra->attribute_definition_id)->first();
                    @endphp
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
                        <td>
                            @can('arp.edit')
                            <form method="POST"
                                  action="{{ route('entities.arp.store', $entity) }}"
                                  id="arp-{{ $sp->id }}-{{ $ra->attribute_definition_id }}">
                                @csrf
                                <input type="hidden" name="sp_entity_id" value="{{ $sp->id }}">
                                <input type="hidden" name="attribute_definition_id" value="{{ $ra->attribute_definition_id }}">
                                <div class="form-check form-switch mb-0">
                                    <input type="hidden" name="is_permitted" value="0">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="is_permitted"
                                           value="1"
                                           {{ ($existingRule?->is_permitted ?? true) ? 'checked' : '' }}
                                           onchange="this.closest('form').querySelector('[name=is_permitted][type=hidden]').value = this.checked ? '1' : '0'">
                                </div>
                            @else
                                @if ($existingRule?->is_permitted ?? true)
                                    <span class="badge bg-success">Permitted</span>
                                @else
                                    <span class="badge bg-danger">Blocked</span>
                                @endif
                            @endcan
                        </td>
                        <td>
                            @can('arp.edit')
                                <input type="text"
                                       class="form-control form-control-sm"
                                       name="notes"
                                       value="{{ old('notes', $existingRule?->notes ?? '') }}"
                                       placeholder="Optional notes"
                                       form="arp-{{ $sp->id }}-{{ $ra->attribute_definition_id }}">
                            @else
                                <span class="small text-muted">{{ $existingRule?->notes ?? '—' }}</span>
                            @endcan
                        </td>
                        @can('arp.edit')
                        <td>
                            <div class="d-flex gap-1">
                                <button type="submit"
                                        class="btn btn-outline-primary btn-sm py-0 px-2"
                                        form="arp-{{ $sp->id }}-{{ $ra->attribute_definition_id }}"
                                        title="Save rule">
                                    <i class="bi bi-save"></i>
                                </button>
                            </form>
                            @if($existingRule)
                            <form method="POST"
                                  action="{{ route('entities.arp.destroy', [$entity, $existingRule]) }}"
                                  onsubmit="return confirm('Remove this ARP rule?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="btn btn-outline-danger btn-sm py-0 px-2"
                                        title="Remove rule">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                            @endif
                            </div>
                        </td>
                        @endcan
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
    @endforeach

    @endif

</div>
@endsection
