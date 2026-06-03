{{--
    resources/views/entities/edit.blade.php
    Edit an existing SAML2 entity — hosts the EntityForm Livewire component.
--}}
@extends('layouts.app')

@section('title', 'Edit Entity — Federation Registry')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')

<div class="container py-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('entities.index') }}">Entities</a></li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('entities.show', $entity) }}">{{ $entity->getDisplayName() ?? $entity->entity_id }}</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-1">
                Edit: {{ $entity->getDisplayName() ?? $entity->entity_id }}
                <span class="badge {{ $entity->type === 'idp' ? 'bg-primary' : 'bg-success' }} ms-2 fw-normal" style="font-size:.6em;vertical-align:middle;">
                    {{ strtoupper($entity->type) }}
                </span>
            </h1>
            <p class="text-muted mb-0 small font-monospace">{{ $entity->entity_id }}</p>
        </div>
        <div class="d-flex gap-2"
             x-data="{ status: '{{ $entity->status }}' }"
             @entity-suspended.window="status = 'suspended'"
             @entity-reactivated.window="status = 'active'">
            <a href="{{ route('entities.show', $entity) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i> {{ __('View') }}
            </a>
            @can('entity.edit')
            <template x-if="status === 'active'">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-warning btn-sm"
                            onclick="Livewire.dispatch('open-suspend-modal', { entityId: '{{ $entity->id }}' })">
                        <i class="bi bi-pause-circle me-1"></i> {{ __('app.action_suspend') }}
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm"
                            disabled
                            title="{{ __('app.entity_delete_blocked_active') }}">
                        <i class="bi bi-trash me-1"></i> {{ __('app.action_delete') }}
                    </button>
                </div>
            </template>
            <template x-if="status === 'suspended'">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm"
                            onclick="Livewire.dispatch('open-reactivate-modal', { entityId: '{{ $entity->id }}' })">
                        <i class="bi bi-play-circle me-1"></i> {{ __('app.action_reactivate') }}
                    </button>
                    <form method="POST"
                          action="{{ route('entities.destroy', $entity) }}"
                          class="d-inline"
                          onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.entity.delete_title, text: Lang.entity.delete_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash me-1"></i> {{ __('app.action_delete') }}
                        </button>
                    </form>
                </div>
            </template>
            @if(in_array($entity->status, ['draft', 'pending']))
            <form method="POST"
                  action="{{ route('entities.destroy', $entity) }}"
                  class="d-inline"
                  onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.entity.delete_title, text: Lang.entity.delete_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i> {{ __('app.action_delete') }}
                </button>
            </form>
            @endif
            @endcan
        </div>
    </div>

    @if($pendingInvitations->isNotEmpty())
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header bg-primary-subtle d-flex align-items-center fw-semibold">
                    <i class="bi bi-envelope-open me-1"></i>
                    Federation Invitations
                    <span class="badge bg-primary ms-1">{{ $pendingInvitations->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @foreach($pendingInvitations as $inv)
                    <div class="p-3 d-flex justify-content-between align-items-start {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="fw-semibold">
                                <a href="{{ route('federations.show', $inv->federation) }}" class="text-decoration-none">{{ $inv->federation->name }}</a>
                                <span class="text-muted fw-normal">wants this entity to join their federation</span>
                            </div>
                            @if($inv->note)
                            <div class="text-muted small fst-italic mt-1">"{{ $inv->note }}"</div>
                            @endif
                            <div class="text-muted small mt-1">
                                Invited by {{ $inv->invitedBy?->name ?? 'Federation Manager' }} · {{ $inv->created_at->diffForHumans() }}
                            </div>
                        </div>
                        <div class="d-flex gap-2 ms-3 flex-shrink-0">
                            <form method="POST" action="{{ route('federation-entity-invitations.accept', $inv) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-check-lg me-1"></i>Accept
                                </button>
                            </form>
                            <form method="POST" action="{{ route('federation-entity-invitations.reject', $inv) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-x-lg me-1"></i>Decline
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    @livewire('entity-form', ['entity' => $entity])

    {{-- Reload from XML / JSON --}}
    <div class="card mt-4" x-data="{ open: false }">
        <div class="card-header bg-transparent py-2 px-3 d-flex align-items-center justify-content-between"
             style="cursor:pointer" @click="open = !open">
            <span class="fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                <i class="bi bi-upload me-1"></i> Reload from XML / JSON
            </span>
            <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
        </div>
        <div x-show="open" x-cloak>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Paste a SAML2 <code>EntityDescriptor</code> or JSON export to overwrite all metadata fields.
                    Federation memberships, status, and access settings are preserved.
                </p>
                @if(session('error'))
                    <div class="alert alert-danger py-2 px-3 mb-3 small">{{ session('error') }}</div>
                @endif
                @error('content')
                    <div class="alert alert-danger py-2 px-3 mb-3 small">{{ $message }}</div>
                @enderror
                <form method="POST" action="{{ route('entities.reload', $entity) }}" enctype="multipart/form-data"
                      onsubmit="event.preventDefault(); SwalDefault.fire({
                          title: 'Overwrite metadata?',
                          text: 'All metadata fields will be replaced with the content you provided. Federation memberships and status are preserved.',
                          icon: 'warning',
                          showCancelButton: true,
                          confirmButtonText: 'Yes, apply',
                          confirmButtonColor: '#f0ad4e'
                      }).then(r => { if (r.isConfirmed) this.submit() })">
                    @csrf
                    <div class="mb-3">
                        <textarea name="content" rows="8"
                                  class="form-control font-monospace @error('content') is-invalid @enderror"
                                  placeholder="Paste XML or JSON here…"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Or upload a file</label>
                        <input type="file" name="file" class="form-control form-control-sm" accept=".xml,.json">
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="bi bi-arrow-repeat me-1"></i> Apply
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<livewire:entity-suspend-modal />
<livewire:entity-reactivate-modal />

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endpush

@endsection
