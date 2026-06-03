@extends('layouts.app')

@section('title', 'Edit Attribute Definition')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('attributes.index') }}">Attributes</a></li>
                <li class="breadcrumb-item active">{{ $attribute->name }}</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Edit: {{ $attribute->full_name }}</h1>
    </div>

    <div class="card border shadow-none" style="max-width:720px;">
        <div class="card-body">
            <form method="POST" action="{{ route('attributes.update', $attribute) }}">
                @csrf
                @method('PUT')

                @include('attributes._form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                    <a href="{{ route('attributes.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
