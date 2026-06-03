@extends('layouts.app')

@section('title', 'Webhooks')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Webhook Endpoints</h1>
        <a href="{{ route('webhooks.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Endpoint
        </a>
    </div>

    @if ($endpoints->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-webhook fs-1 d-block mb-2"></i>
                No webhook endpoints configured.
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>URL</th>
                            <th>Events</th>
                            <th>Status</th>
                            <th>Deliveries</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($endpoints as $endpoint)
                        <tr>
                            <td>
                                <a href="{{ route('webhooks.show', $endpoint) }}" class="text-decoration-none fw-medium">
                                    {{ $endpoint->url }}
                                </a>
                                @if ($endpoint->description)
                                    <div class="text-muted small">{{ $endpoint->description }}</div>
                                @endif
                            </td>
                            <td>
                                @foreach ($endpoint->events as $event)
                                    <span class="badge bg-secondary me-1">{{ $event }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if ($endpoint->active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>{{ $endpoint->deliveries_count }}</td>
                            <td class="text-muted small">{{ $endpoint->created_at->diffForHumans() }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('webhooks.destroy', $endpoint) }}"
                                      onsubmit="return confirm('Delete this webhook endpoint?')"
                                      class="d-inline">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
