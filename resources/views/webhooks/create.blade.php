@extends('layouts.app')

@section('title', 'New Webhook Endpoint')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('webhooks.index') }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 mb-0">New Webhook Endpoint</h1>
    </div>

    <div class="card border-0 shadow-sm" style="max-width: 640px;">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('webhooks.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="url" class="form-label fw-semibold">Payload URL <span class="text-danger">*</span></label>
                    <input type="url" id="url" name="url"
                           class="form-control @error('url') is-invalid @enderror"
                           value="{{ old('url') }}" placeholder="https://example.com/webhook" required>
                    @error('url')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Events <span class="text-danger">*</span></label>
                    <div class="border rounded p-3">
                        @foreach (['*', 'entity.created', 'entity.approved', 'metadata.generated'] as $evt)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="events[]"
                                   id="evt_{{ $loop->index }}" value="{{ $evt }}"
                                   {{ in_array($evt, old('events', [])) ? 'checked' : '' }}>
                            <label class="form-check-label" for="evt_{{ $loop->index }}">
                                <code>{{ $evt }}</code>
                                @if ($evt === '*')
                                    <span class="text-muted ms-1">(all events)</span>
                                @endif
                            </label>
                        </div>
                        @endforeach
                    </div>
                    @error('events')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="description" class="form-label fw-semibold">Description</label>
                    <input type="text" id="description" name="description"
                           class="form-control @error('description') is-invalid @enderror"
                           value="{{ old('description') }}" placeholder="Optional note">
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Endpoint</button>
                    <a href="{{ route('webhooks.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
