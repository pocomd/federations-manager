@extends('layouts.app')

@section('title', 'Deleted Entities')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">Deleted Entities</h1>
            <p class="text-muted mb-0 small">
                Soft-deleted entity records. Restore to make them active again, or permanently
                delete to remove all data.
            </p>
        </div>
        <a href="{{ route('entities.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Entities
        </a>
    </div>

    <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle me-2"></i>
        {{ __('app.trash_restore_note') }}
    </div>

    @if ($entities->isEmpty())
        <div class="alert alert-success py-2 small">
            <i class="bi bi-check-circle me-2"></i> No deleted entities found.
        </div>
    @else
        <div class="card border shadow-none">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Display Name / Entity ID</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Deleted</th>
                                <th class="pe-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entities as $entity)
                                @php
                                    $displayName = $entity->getDisplayName();
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-medium">{{ $displayName ?? $entity->entity_id }}</div>
                                        @if ($displayName)
                                            <div class="text-muted small font-monospace">{{ $entity->entity_id }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $entity->type === 'idp' ? 'bg-primary' : 'bg-info text-dark' }}">
                                            {{ strtoupper($entity->type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $entity->status }}</span>
                                    </td>
                                    <td class="small text-muted">
                                        {{ $entity->deleted_at->diffForHumans() }}
                                    </td>
                                    <td class="pe-3 text-center">
                                        <div>
                                            {{-- Restore → always to suspended --}}
                                            <form method="POST"
                                                  action="{{ route('entities.restore', $entity->id) }}"
                                                  style="display:contents"
                                                  x-data
                                                  @submit.prevent="SwalDefault.fire({title: Lang.entity.restore_title, icon: 'question', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#198754'}).then(r => { if (r.isConfirmed) $el.submit() })">
                                                @csrf
                                                <button type="submit"
                                                        class="btn btn-sm btn-success"
                                                        title="{{ __('app.action_restore') }}">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            </form>

                                            {{-- Force delete --}}
                                            @can('entity.delete')
                                            <form method="POST"
                                                  action="{{ route('entities.force-delete', $entity->id) }}"
                                                  style="display:contents"
                                                  x-data
                                                  @submit.prevent="SwalDefault.fire({title: Lang.entity.force_title, text: Lang.entity.force_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) $el.submit() })">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-sm btn-danger"
                                                        title="{{ __('app.action_force_delete') }}">
                                                    <i class="bi bi-trash3-fill"></i>
                                                </button>
                                            </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $entities->links() }}
        </div>
    @endif

</div>
@endsection
