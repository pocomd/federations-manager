@extends('layouts.app')

@section('title', 'Registration Policies — ' . $federation->name)

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federations.index') }}">Federations</a></li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('federations.show', $federation) }}">{{ $federation->name }}</a>
                    </li>
                    <li class="breadcrumb-item active">Registration Policies</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-1">Registration Policies</h1>
            <p class="text-muted small mb-0">
                <span class="font-monospace">{{ $federation->uri }}</span>
            </p>
        </div>
        @can('federation.edit')
        <a href="{{ route('federations.policies.create', $federation) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Policy
        </a>
        @endcan
    </div>

    <div class="alert alert-info small mb-4" role="alert">
        <i class="bi bi-info-circle me-1"></i>
        Registration policies are included in entity metadata XML as
        <code>mdrpi:RegistrationPolicy</code> elements inside
        <code>mdrpi:RegistrationInfo</code>. One policy per language.
    </div>

    @if ($policies->isEmpty())
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>
        No registration policies defined. Entities in this federation will not include
        <code>mdrpi:RegistrationPolicy</code> in their metadata.
    </div>
    @else
    <div class="card border shadow-none">
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:90px;">Language</th>
                        <th>Display Name</th>
                        <th>URL</th>
                        <th style="width:100px;">Status</th>
                        @can('federation.edit')
                        <th class="text-center" style="width:120px;">Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($policies as $policy)
                    <tr>
                        <td class="ps-3">
                            <span class="badge bg-secondary">{{ strtoupper($policy->lang) }}</span>
                        </td>
                        <td class="small fw-medium">{{ $policy->display_name }}</td>
                        <td>
                            <a href="{{ $policy->url }}"
                               target="_blank"
                               rel="noopener"
                               class="text-truncate d-inline-block small"
                               style="max-width:380px;">
                                {{ $policy->url }}
                            </a>
                        </td>
                        <td>
                            @if ($policy->enabled)
                                <span class="badge bg-success">Enabled</span>
                            @else
                                <span class="badge bg-secondary">Disabled</span>
                            @endif
                        </td>
                        @can('federation.edit')
                        <td class="text-center">
                            <div>
                                <a href="{{ route('federations.policies.edit', [$federation, $policy]) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST"
                                      action="{{ route('federations.policies.destroy', [$federation, $policy]) }}"
                                      style="display:contents"
                                      onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.federation.policy_del_title, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                        @endcan
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
