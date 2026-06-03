@extends('layouts.app')

@section('title', 'Notifications')

@section('content')

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">Notifications</h1>
            <p class="text-muted small mb-0">Your in-app notifications</p>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">Mark all read</button>
            </form>
            <a href="{{ route('notifications.archive') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-archive me-1"></i>Archive
            </a>
        </div>
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
                        <th class="small">Status</th>
                        <th class="small">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $notification)
                    <tr class="{{ $notification->read_at ? '' : 'fw-semibold' }}">
                        <td class="small">{{ $notification->type }}</td>
                        <td class="small">{{ $notification->title }}</td>
                        <td class="small text-muted">{{ \Illuminate\Support\Str::limit($notification->body, 80) }}</td>
                        <td class="small text-muted text-nowrap">{{ $notification->created_at->diffForHumans() }}</td>
                        <td class="small">
                            @if($notification->read_at)
                                <span class="badge bg-secondary">Read</span>
                            @else
                                <span class="badge bg-primary">Unread</span>
                            @endif
                        </td>
                        <td class="small">
                            <div class="d-flex gap-1">
                                @if(!$notification->read_at)
                                <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-xs btn-outline-success" style="font-size:.75rem;padding:.1rem .4rem;">Mark read</button>
                                </form>
                                @endif
                                @if($notification->action_url)
                                <a href="{{ $notification->action_url }}" class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:.1rem .4rem;">Go to</a>
                                @endif
                                <form method="POST" action="{{ route('notifications.destroy', $notification) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-secondary" style="font-size:.75rem;padding:.1rem .4rem;">Archive</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4 small">No notifications</td>
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
