@extends('layouts.app')

@section('title', 'New Federation')

@section('content')
<div class="container py-4" style="max-width:720px">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('federations.index') }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <h1 class="h3 mb-0">New Federation</h1>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border shadow-none">
        <div class="card-body p-4">
            <form action="{{ route('federations.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="uri" class="form-label fw-semibold">Registration Authority URI <span class="text-danger">*</span></label>
                    <input type="url" id="uri" name="uri" class="form-control @error('uri') is-invalid @enderror"
                           value="{{ old('uri') }}" placeholder="https://federation.example.com" required>
                    <div class="form-text">Globally unique HTTPS URI used in metadata registration info.</div>
                    @error('uri')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label fw-semibold">Description</label>
                    <textarea id="description" name="description" rows="3"
                              class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="metadata_url" class="form-label fw-semibold">Metadata URL</label>
                    <input type="url" id="metadata_url" name="metadata_url"
                           class="form-control @error('metadata_url') is-invalid @enderror"
                           value="{{ old('metadata_url') }}" placeholder="https://...">
                    @error('metadata_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                @if(count($signingDrivers) > 1)
                <div class="mb-3">
                    <label for="signing_driver" class="form-label fw-semibold">Signing Backend <span class="text-danger">*</span></label>
                    <select id="signing_driver" name="signing_driver"
                            class="form-select @error('signing_driver') is-invalid @enderror" required>
                        @foreach($signingDrivers as $value => $label)
                            <option value="{{ $value }}" {{ old('signing_driver', 'file') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Determines how signing credentials are stored for this federation.</div>
                    @error('signing_driver')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @else
                    {{-- Only one driver active — submit silently --}}
                    <input type="hidden" name="signing_driver" value="{{ array_key_first($signingDrivers) }}">
                @endif

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Create Federation</button>
                    <a href="{{ route('federations.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
