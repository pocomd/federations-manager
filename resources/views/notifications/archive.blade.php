@extends('layouts.app')

@section('title', 'Notification Archive')

@section('content')

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">Notification Archive</h1>
            <p class="text-muted small mb-0">Previously archived notifications</p>
        </div>
        <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to notifications
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="small">Type</th>
                        <th class="small">Title</th>
                        <th class="small">Message</th>
                        <th class="small">Received</th>
                        <th class="small">Archived</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $notification)
                    <tr>
                        <td class="small">{{ $notification->type }}</td>
                        <td class="small">{{ $notification->title }}</td>
                        <td class="small text-muted">{{ \Illuminate\Support\Str::limit($notification->body, 80) }}</td>
                        <td class="small text-muted text-nowrap">{{ $notification->created_at->diffForHumans() }}</td>
                        <td class="small text-muted text-nowrap">{{ $notification->archived_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4 small">No archived notifications</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($notifications->hasPages())
        <div class="card-footer bg-white">
            {{ $notifications->links() }}
        </div>
        @endif
    </div>

</div>

@endsection
