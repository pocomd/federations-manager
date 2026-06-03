{{--
    resources/views/entities/create.blade.php
    Register a new SAML2 entity — hosts the EntityForm Livewire component.
--}}
@extends('layouts.app')

@section('title', 'Register Entity — Federation Registry')

@section('content')

<div class="container py-4">

    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('entities.index') }}">Entities</a></li>
                <li class="breadcrumb-item active" aria-current="page">Register New Entity</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-1">Register SAML2 Entity</h1>
        <p class="text-muted mb-0 small">
            Each entity maps to a SAML2 <code>&lt;md:EntityDescriptor&gt;</code>.
            Newly registered entities start in <span class="badge bg-secondary">draft</span> status.
        </p>
    </div>

    @livewire('entity-form')

</div>

@endsection
