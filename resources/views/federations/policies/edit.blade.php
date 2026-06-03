@extends('layouts.app')

@section('title', 'Edit Registration Policy')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('federations.index') }}">Federations</a></li>
                <li class="breadcrumb-item">
                    <a href="{{ route('federations.show', $federation) }}">{{ $federation->name }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('federations.policies.index', $federation) }}">Registration Policies</a>
                </li>
                <li class="breadcrumb-item active">Edit Policy</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">Edit Registration Policy</h1>
    </div>

    <div class="card border shadow-none" style="max-width:640px;">
        <div class="card-body">

            @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            <form method="POST" action="{{ route('federations.policies.update', [$federation, $policy]) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label fw-medium" for="lang">Language <span class="text-danger">*</span></label>
                    <select class="form-select @error('lang') is-invalid @enderror"
                            id="lang"
                            name="lang"
                            required>
                        @foreach ($availableLangs as $code => $name)
                            <option value="{{ $code }}" {{ old('lang', $policy->lang) === $code ? 'selected' : '' }}>
                                {{ $name }} ({{ $code }})
                            </option>
                        @endforeach
                    </select>
                    @error('lang')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium" for="display_name">Display Name <span class="text-danger">*</span></label>
                    <input type="text"
                           class="form-control @error('display_name') is-invalid @enderror"
                           id="display_name"
                           name="display_name"
                           value="{{ old('display_name', $policy->display_name) }}"
                           required>
                    @error('display_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium" for="url">Policy URL <span class="text-danger">*</span></label>
                    <input type="url"
                           class="form-control @error('url') is-invalid @enderror"
                           id="url"
                           name="url"
                           value="{{ old('url', $policy->url) }}"
                           required>
                    <div class="form-text text-muted">Public URL where the policy document is hosted. Must use HTTPS.</div>
                    @error('url')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium" for="description">Internal Note</label>
                    <textarea class="form-control @error('description') is-invalid @enderror"
                              id="description"
                              name="description"
                              rows="2">{{ old('description', $policy->description) }}</textarea>
                    <div class="form-text text-muted">Internal note — not included in metadata XML.</div>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <div class="form-check form-switch">
                        <input type="hidden" name="enabled" value="0">
                        <input class="form-check-input"
                               type="checkbox"
                               id="enabled"
                               name="enabled"
                               value="1"
                               {{ old('enabled', $policy->enabled) ? 'checked' : '' }}>
                        <label class="form-check-label" for="enabled">Enabled — include in metadata</label>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-save me-1"></i> Update Policy
                    </button>
                    <a href="{{ route('federations.policies.index', $federation) }}"
                       class="btn btn-outline-secondary btn-sm">Cancel</a>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection
