@extends('layouts.app')

@section('title', 'Attribute Definitions')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">Attribute Definitions</h1>
            <p class="text-muted mb-0 small">
                Standard SAML attribute registry used by SP requested attributes and IdP ARP policies.
            </p>
        </div>
        @can('entity.edit')
        <a href="{{ route('attributes.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>
            Add Attribute Definition
        </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('attributes.index') }}">
        {{-- Schema filter bar --}}
        <div class="card card-body p-2 mb-3">
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <span class="text-muted small fw-semibold">Schema:</span>
                <a href="{{ route('attributes.index', array_filter(['search' => request('search')])) }}"
                   class="btn btn-sm {{ !request('schema') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    All ({{ $total }})
                </a>
                @foreach(['eduperson' => 'eduPerson', 'ldap' => 'LDAP', 'schac' => 'SCHAC', 'voperson' => 'voPerson'] as $key => $label)
                <a href="{{ route('attributes.index', array_filter(['schema' => $key, 'search' => request('search')])) }}"
                   class="btn btn-sm {{ request('schema') === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $label }} ({{ $counts[$key] ?? 0 }})
                </a>
                @endforeach

                {{-- Search input --}}
                <div class="ms-auto d-flex gap-2 align-items-center">
                    @if(request('schema'))
                        <input type="hidden" name="schema" value="{{ request('schema') }}">
                    @endif
                    <input type="text" name="search"
                           value="{{ request('search') }}"
                           class="form-control form-control-sm"
                           style="min-width:220px;"
                           placeholder="Search by name or OID..."
                           onchange="this.form.submit()">
                    @if(request('search'))
                    <a href="{{ route('attributes.index', array_filter(['schema' => request('schema')])) }}"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="card border shadow-none">
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Name</th>
                        <th>Schema</th>
                        <th>Full Name</th>
                        <th>SAML2 OID</th>
                        <th>SAML1 URN</th>
                        <th style="width:100px;">Status</th>
                        @can('entity.edit')
                        <th class="text-center" style="width:120px;">Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @php
                    $schemaLabels = [
                        'eduperson' => ['eduPerson', 'bg-info text-dark'],
                        'ldap'      => ['LDAP',      'bg-secondary'],
                        'schac'     => ['SCHAC',     'bg-success'],
                        'voperson'  => ['voPerson',  'bg-warning text-dark'],
                    ];
                    @endphp
                    @forelse ($attributes as $attr)
                    <tr class="{{ $attr->is_active ? '' : 'text-muted' }}">
                        <td class="ps-3">
                            <code class="small">{{ $attr->name }}</code>
                        </td>
                        <td>
                            @php
                                [$schLabel, $schClass] = $schemaLabels[$attr->schema] ?? [$attr->schema, 'bg-light text-dark'];
                            @endphp
                            <span class="badge {{ $schClass }}">{{ $schLabel }}</span>
                        </td>
                        <td class="small">{{ $attr->full_name }}</td>
                        <td>
                            @if ($attr->saml2_oid)
                                <code class="small text-muted">{{ $attr->saml2_oid }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($attr->saml1_urn)
                                <code class="small text-muted">{{ $attr->saml1_urn }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($attr->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        @can('entity.edit')
                        <td class="text-center">
                            <div>
                                <a href="{{ route('attributes.edit', $attr) }}"
                                   class="btn btn-sm btn-outline-secondary"
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST"
                                      action="{{ route('attributes.destroy', $attr) }}"
                                      style="display:contents"
                                      onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.attribute.delete_title, text: Lang.attribute.delete_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm}).then(r => { if (r.isConfirmed) this.submit() })">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-warning"
                                            title="Deactivate">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                        @endcan
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No attribute definitions found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($attributes->hasPages())
        <div class="card-footer bg-transparent border-top-0 pt-2">
            {{ $attributes->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
