@extends('layouts.app')

@section('title', $isManager ? 'Invitation Requests' : 'My Invitation Requests')

@section('content')

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">
                {{ $isManager ? 'Invitation Requests' : 'My Invitation Requests' }}
            </h1>
            <p class="text-muted small mb-0">
                {{ $isManager
                    ? 'Review contact invitation requests from Entity Managers'
                    : 'Track the status of your submitted co-manager requests' }}
            </p>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            @if($isManager)
            {{-- ── Federation Manager view — pending requests with Approve/Reject ── --}}
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="small">Entity</th>
                        <th class="small">Contact Name</th>
                        <th class="small">Contact Email</th>
                        <th class="small">Type</th>
                        <th class="small">Requested By</th>
                        <th class="small">Requested At</th>
                        <th class="small">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr x-data="{ showReject: false }">
                        <td class="small">
                            {{ $req->entity?->display_name ?? $req->entity?->entity_id ?? '—' }}
                        </td>
                        <td class="small">{{ $req->contact_name ?? '—' }}</td>
                        <td class="small">{{ $req->contact_email }}</td>
                        <td class="small">
                            <span class="badge bg-secondary">{{ $req->contact_type }}</span>
                        </td>
                        <td class="small">{{ $req->requestedBy?->name ?? '—' }}</td>
                        <td class="small text-muted">{{ $req->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <form method="POST"
                                      action="{{ route('invitation-requests.approve', $req) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="bi bi-check-lg me-1"></i>Approve
                                    </button>
                                </form>

                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        @click="showReject = !showReject">
                                    <i class="bi bi-x-lg me-1"></i>Reject
                                </button>
                            </div>

                            <div x-show="showReject" class="mt-2" x-cloak>
                                <form method="POST"
                                      action="{{ route('invitation-requests.reject', $req) }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="mb-2">
                                        <textarea name="fm_note"
                                                  class="form-control form-control-sm"
                                                  rows="2"
                                                  placeholder="Reason for rejection (required)"
                                                  required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-danger btn-sm">Confirm Reject</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            @click="showReject = false">Cancel</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted small py-4">
                            No pending invitation requests.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            @else
            {{-- ── Entity Manager view — own requests, all statuses ── --}}
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="small">Entity</th>
                        <th class="small">Federation</th>
                        <th class="small">Contact Email</th>
                        <th class="small">Type</th>
                        <th class="small">Submitted</th>
                        <th class="small">Status</th>
                        <th class="small">Note</th>
                        <th class="small"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr>
                        <td class="small">
                            {{ $req->entity?->display_name ?? $req->entity?->entity_id ?? '—' }}
                        </td>
                        <td class="small text-muted">{{ $req->federation?->name ?? '—' }}</td>
                        <td class="small">{{ $req->contact_email }}</td>
                        <td class="small">
                            <span class="badge bg-secondary">{{ $req->contact_type }}</span>
                        </td>
                        <td class="small text-muted">{{ $req->created_at->format('Y-m-d H:i') }}</td>
                        <td class="small">
                            @if($req->status === 'pending')
                                <span class="badge bg-warning text-dark">Pending</span>
                            @elseif($req->status === 'approved')
                                <span class="badge bg-success">Approved</span>
                            @elseif($req->status === 'rejected')
                                <span class="badge bg-danger">Rejected</span>
                            @else
                                <span class="badge bg-secondary">{{ $req->status }}</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            @if($req->status === 'rejected' && $req->fm_note)
                                <span class="text-danger">{{ $req->fm_note }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($req->status === 'pending')
                            <form method="POST"
                                  action="{{ route('invitation-requests.cancel', $req) }}"
                                  onsubmit="return confirm('Cancel this invitation request?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-x-circle me-1"></i>Cancel
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted small py-4">
                            You have not submitted any co-manager requests yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            @endif
        </div>
    </div>

</div>

@endsection
