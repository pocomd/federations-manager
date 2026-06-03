@extends('layouts.app')

@section('title', 'Edit Federation')

@section('content')
<div class="container py-4" style="max-width:720px">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('federations.show', $federation) }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <h1 class="h3 mb-0">Edit Federation</h1>
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
            <form action="{{ route('federations.update', $federation) }}" method="POST">
                @csrf
                @method('PATCH')

                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $federation->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="uri" class="form-label fw-semibold">Registration Authority URI <span class="text-danger">*</span></label>
                    <input type="url" id="uri" name="uri" class="form-control @error('uri') is-invalid @enderror"
                           value="{{ old('uri', $federation->uri) }}" required>
                    @error('uri')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label fw-semibold">Description</label>
                    <textarea id="description" name="description" rows="3"
                              class="form-control @error('description') is-invalid @enderror">{{ old('description', $federation->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label fw-semibold">Status</label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="active"   {{ old('status', $federation->status) === 'active'   ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $federation->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="metadata_url" class="form-label fw-semibold">Metadata URL</label>
                    <input type="url" id="metadata_url" name="metadata_url"
                           class="form-control @error('metadata_url') is-invalid @enderror"
                           value="{{ old('metadata_url', $federation->metadata_url) }}">
                    @error('metadata_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    <a href="{{ route('federations.show', $federation) }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-4"
         x-data="{ contactCount: {{ $federation->contacts()->count() }} }"
         x-on:contacts-updated.window="contactCount = $event.detail.count">
        @livewire('federation-contacts', ['federation' => $federation])
    </div>

    @can('federation.edit')
        <div class="mt-4">
            <div class="card border-danger border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-danger fw-semibold">Delete Federation</h6>
                    <p class="text-muted small mb-3">
                        Deleting a federation is reversible (soft delete), but member entities will no longer be published in this federation's metadata.
                    </p>
                    <form action="{{ route('federations.destroy', $federation) }}" method="POST"
                          onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.federation.delete_title, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete Federation</button>
                    </form>
                </div>
            </div>
        </div>
    @endcan
</div>
@endsection
