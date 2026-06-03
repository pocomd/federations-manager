@extends('layouts.app')

@section('title', 'Deleted Federations')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">Deleted Federations</h1>
            <p class="text-muted mb-0 small">
                Soft-deleted federation records. Restore to make them active again, or permanently
                delete to remove all data.
            </p>
        </div>
        <a href="{{ route('federations.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Federations
        </a>
    </div>

    <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle me-2"></i>
        Records shown here are soft-deleted. Restore to make them active again, or permanently
        delete to remove all data. Permanent deletion is blocked if the federation still has
        entities attached.
    </div>

    @if ($federations->isEmpty())
        <div class="alert alert-success py-2 small">
            <i class="bi bi-check-circle me-2"></i> No deleted federations found.
        </div>
    @else
        <div class="card border shadow-none">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Name</th>
                                <th>URI</th>
                                <th>Deleted</th>
                                <th>Entities</th>
                                <th class="pe-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($federations as $federation)
                                <tr>
                                    <td class="ps-3 fw-medium">{{ $federation->name }}</td>
                                    <td class="text-muted small font-monospace">{{ $federation->uri }}</td>
                                    <td class="small text-muted">
                                        {{ $federation->deleted_at->diffForHumans() }}
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $federation->entities_count }}</span>
                                    </td>
                                    <td class="pe-3 text-center">
                                        <div>
                                            {{-- Restore --}}
                                            <form method="POST"
                                                  action="{{ route('federations.restore', $federation->id) }}"
                                                  style="display:contents">
                                                @csrf
                                                <button type="submit"
                                                        class="btn btn-sm btn-success"
                                                        title="Restore">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                                </button>
                                            </form>

                                            {{-- Permanent delete --}}
                                            <form method="POST"
                                                  action="{{ route('federations.force-delete', $federation->id) }}"
                                                  style="display:contents"
                                                  onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.federation.force_title, text: Lang.federation.force_text, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Delete permanently">
                                                    <i class="bi bi-trash me-1"></i> Delete permanently
                                                </button>
                                            </form>
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
            {{ $federations->links() }}
        </div>
    @endif

</div>
@endsection
