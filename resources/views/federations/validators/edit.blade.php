@extends('layouts.app')

@section('title', 'Edit Validator — ' . $federation->name . ' — Federation Registry')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('federations.index') }}">Federations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('federations.show', $federation) }}">{{ $federation->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('federations.validators.index', $federation) }}">Validators</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Edit Validator: {{ $validator->name }}</h1>
    </div>

    <form method="POST"
          action="{{ route('federations.validators.update', [$federation, $validator]) }}">
        @csrf @method('PUT')
        @include('federations.validators._form')
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Update Validator</button>
            <a href="{{ route('federations.validators.index', $federation) }}"
               class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
    </form>

</div>
@endsection
