@extends('layouts.app')

@section('title', 'Import Entity from JSON')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">Import Entity from JSON</h1>
            <p class="text-muted mb-0 small">
                Paste a JSON object with entity fields to pre-fill the registration form.
            </p>
        </div>
        <a href="{{ route('entities.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Entities
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first('json') }}
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card border shadow-none">
                <div class="card-header bg-transparent border-bottom py-2">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-braces me-2 text-muted"></i>Entity JSON
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('entities.import.array.submit') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="json" class="form-label fw-medium">Paste JSON</label>
                            <textarea
                                id="json"
                                name="json"
                                class="form-control font-monospace @error('json') is-invalid @enderror"
                                rows="18"
                                placeholder='{&#10;  "entity_id": "https://sp.example.org",&#10;  "type": "sp"&#10;}'
                                spellcheck="false">{{ old('json') }}</textarea>
                            @error('json')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-search me-1"></i> Parse JSON
                            </button>
                            <button type="reset" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-circle me-1"></i> Clear
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border shadow-none">
                <div class="card-header bg-transparent border-bottom py-2">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-file-code me-2 text-muted"></i>Minimal example
                    </h6>
                </div>
                <div class="card-body">
                    <pre class="small text-muted mb-0" style="white-space: pre-wrap; font-size: 0.75rem;">{
  "entity_id": "https://sp.example.org",
  "type": "sp",
  "scope": "example.org",
  "registration_authority": "https://registry.example.org"
}</pre>
                </div>
            </div>

            <div class="card border shadow-none mt-3">
                <div class="card-header bg-transparent border-bottom py-2">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-question-circle me-2 text-muted"></i>Other import options
                    </h6>
                </div>
                <div class="card-body">
                    <a href="{{ route('entities.import.xml') }}" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                        <i class="bi bi-code-square me-1"></i> Import from XML
                    </a>
                    <a href="{{ route('entities.create') }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-pencil me-1"></i> Fill form manually
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
