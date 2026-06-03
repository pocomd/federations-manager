{{--
    resources/views/federations/index.blade.php
    Federation list — hosts the FederationManager Livewire component.
--}}
@extends('layouts.app')

@section('title', 'Federations — Federation Registry')

@section('content')

<div class="container-fluid py-4 px-4">

    {{-- Page header --}}
    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">Federations</h1>
            <p class="text-muted mb-0 small">
                Manage SAML2 federation groups, entity membership, and signed aggregate metadata.
            </p>
        </div>
        <div class="d-flex gap-2">
            @can('federation.create')
                <a href="{{ route('federations.trashed') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-trash me-1"></i>
                    Deleted Federations
                    @if(($trashedCount ?? 0) > 0)
                        <span class="badge bg-warning text-dark ms-1">{{ $trashedCount }}</span>
                    @endif
                </a>
            @endcan
            @can('federation.create')
                <a href="{{ route('federations.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> New Federation
                </a>
            @endcan
        </div>
    </div>

    @livewire('federation-manager')

</div>

@endsection
