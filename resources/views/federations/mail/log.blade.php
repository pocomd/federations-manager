@extends('layouts.app')

@section('title', 'Mail Log — ' . $federation->name)

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federations.index') }}">Federations</a></li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('federations.show', $federation) }}">{{ $federation->name }}</a>
                    </li>
                    <li class="breadcrumb-item active">Mail Log</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-1">Mail Log</h1>
            <p class="text-muted small mb-0">All emails sent for {{ $federation->name }}.</p>
        </div>
        @can('federation.edit')
        <a href="{{ route('federations.mail.compose', $federation) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-envelope me-1"></i> Send Email
        </a>
        @endcan
    </div>

    <div class="card border shadow-none">
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:140px;">Sent</th>
                        <th>Entity</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th style="width:90px;">Status</th>
                        <th style="width:120px;">Sent by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                    <tr>
                        <td class="ps-3 small text-muted">
                            {{ $log->created_at->diffForHumans() }}
                        </td>
                        <td class="small">
                            @if ($log->entity)
                                <a href="{{ route('entities.show', $log->entity) }}"
                                   class="text-decoration-none font-monospace text-muted"
                                   style="max-width:200px;overflow:hidden;text-overflow:ellipsis;display:block;white-space:nowrap;">
                                    {{ $log->entity->entity_id }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="small">
                            <div>{{ $log->to_email }}</div>
                            @if ($log->contact_type)
                                <span class="badge bg-secondary" style="font-size:0.65rem;">{{ $log->contact_type }}</span>
                            @endif
                        </td>
                        <td class="small text-truncate" style="max-width:260px;">
                            {{ $log->subject }}
                        </td>
                        <td>
                            @if ($log->status === 'sent')
                                <span class="badge bg-success">Sent</span>
                            @elseif ($log->status === 'failed')
                                <span class="badge bg-danger" title="{{ $log->error_message }}">Failed</span>
                            @else
                                <span class="badge bg-warning text-dark">Pending</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $log->sentBy?->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No mail log entries found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
        <div class="card-footer bg-transparent border-top-0 pt-2">
            {{ $logs->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
