@extends('layouts.app')

@section('title', 'Add Validator — ' . $federation->name . ' — Federation Registry')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('federations.index') }}">Federations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('federations.show', $federation) }}">{{ $federation->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('federations.validators.index', $federation) }}">Validators</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Add Validator</h1>
    </div>

    <form method="POST" action="{{ route('federations.validators.store', $federation) }}">
        @csrf
        @include('federations.validators._form')
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Save Validator</button>
            <button type="submit" name="_open_test" value="1" class="btn btn-outline-info btn-sm">
                <i class="bi bi-play-circle me-1"></i> Save & Test
            </button>
            <a href="{{ route('federations.validators.index', $federation) }}"
               class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
    </form>

</div>
@endsection
