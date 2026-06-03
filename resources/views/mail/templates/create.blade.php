@extends('layouts.app')

@section('title', 'Add Mail Template')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('mail.templates.index') }}">Mail Templates</a></li>
                <li class="breadcrumb-item active">Add Template</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Add Mail Template</h1>
    </div>

    <form method="POST" action="{{ route('mail.templates.store') }}">
        @csrf

        @include('mail.templates._form')

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-save me-1"></i> Create Template
            </button>
            <a href="{{ route('mail.templates.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
    </form>

</div>
@endsection
