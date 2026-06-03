@extends('layouts.app')

@section('title', 'Add Attribute Definition')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('attributes.index') }}">Attributes</a></li>
                <li class="breadcrumb-item active">Add Definition</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Add Attribute Definition</h1>
    </div>

    <div class="card border shadow-none" style="max-width:720px;">
        <div class="card-body">
            <form method="POST" action="{{ route('attributes.store') }}">
                @csrf

                @include('attributes._form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Create
                    </button>
                    <a href="{{ route('attributes.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
