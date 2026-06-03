{{--
    resources/views/entities/index.blade.php
    Main entity list page — hosts the Livewire EntitySearch component
--}}
@extends('layouts.app')

@section('title', 'Entities — Federation Registry')

@section('content')

<div class="container-fluid py-4 px-4">

    {{-- Page header --}}
    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">SAML Entities</h1>
            <p class="text-muted mb-0 small">
                Identity Providers and Service Providers registered in this federation registry.
                Each entity maps to a SAML2 <code>&lt;md:EntityDescriptor&gt;</code>.
            </p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @can('entity.edit')
                <a href="{{ route('entities.trashed') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-trash me-1"></i>
                    Deleted Entities
                    @if(($trashedCount ?? 0) > 0)
                        <span class="badge bg-warning text-dark ms-1">{{ $trashedCount }}</span>
                    @endif
                </a>
            @endcan
            @can('entity.create')
                <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-plus-lg me-1"></i> Register Entity
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="{{ route('entities.create') }}">
                                <i class="bi bi-pencil me-2 text-muted"></i>Fill form manually
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="{{ route('entities.import.xml') }}">
                                <i class="bi bi-code-square me-2 text-muted"></i>Import from XML
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('entities.import.array') }}">
                                <i class="bi bi-braces me-2 text-muted"></i>Import from JSON
                            </a>
                        </li>
                    </ul>
                </div>
            @endcan
        </div>
    </div>

    {{-- Livewire component --}}
    @livewire('entity-search')

</div>

@endsection