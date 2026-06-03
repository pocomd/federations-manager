@extends('layouts.app')

@section('title', 'Webhook — ' . $webhook->url)

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('webhooks.index') }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 mb-0 text-truncate">{{ $webhook->url }}</h1>
    </div>

    {{-- Details card --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold">Endpoint Details</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">URL</dt>
                        <dd class="col-sm-8 text-break">{{ $webhook->url }}</dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            @if ($webhook->active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Events</dt>
                        <dd class="col-sm-8">
                            @foreach ($webhook->events as $event)
                                <span class="badge bg-secondary me-1">{{ $event }}</span>
                            @endforeach
                        </dd>

                        <dt class="col-sm-4">Secret</dt>
                        <dd class="col-sm-8">
                            <code class="user-select-all">{{ $webhook->secret }}</code>
                        </dd>

                        @if ($webhook->description)
                        <dt class="col-sm-4">Description</dt>
                        <dd class="col-sm-8">{{ $webhook->description }}</dd>
                        @endif

                        <dt class="col-sm-4">Created</dt>
                        <dd class="col-sm-8">{{ $webhook->created_at->toDateTimeString() }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    {{-- Deliveries table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">Delivery Log</div>
        @if ($deliveries->isEmpty())
            <div class="card-body text-center text-muted py-4">No deliveries yet.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Event</th>
                            <th>Status</th>
                            <th>HTTP</th>
                            <th>Attempt</th>
                            <th>When</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveries as $delivery)
                        <tr>
                            <td><code>{{ $delivery->event }}</code></td>
                            <td>
                                @if ($delivery->status === 'delivered')
                                    <span class="badge bg-success">delivered</span>
                                @elseif ($delivery->status === 'failed')
                                    <span class="badge bg-danger">failed</span>
                                @else
                                    <span class="badge bg-warning text-dark">pending</span>
                                @endif
                            </td>
                            <td>{{ $delivery->http_status ?? '—' }}</td>
                            <td>{{ $delivery->attempt }}</td>
                            <td class="text-muted small">{{ $delivery->created_at->diffForHumans() }}</td>
                            <td>
                                @if ($delivery->status === 'failed')
                                <form method="POST"
                                      action="{{ route('webhooks.deliveries.retry', [$webhook, $delivery]) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-warning">Retry</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent">
                {{ $deliveries->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
