@extends('layouts.app')

@section('title', 'Preview Import — Federation Registry')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">Preview Import</h1>
            <p class="text-muted mb-0 small">
                Review the parsed data below. You can import directly or open the full form to edit details first.
            </p>
        </div>
        <a href="{{ route('entities.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Entities
        </a>
    </div>


    {{-- Core fields ─────────────────────────────────────────────────────── --}}
    <div class="card border shadow-none mb-4">
        <div class="card-header bg-transparent border-bottom py-2">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-diagram-3 me-2 text-muted"></i>Core
            </h6>
        </div>
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-sm-3 text-muted">Entity ID</dt>
                <dd class="col-sm-9 font-monospace">{{ $data['entity_id'] }}</dd>

                <dt class="col-sm-3 text-muted">Type</dt>
                <dd class="col-sm-9">{{ strtoupper($data['type']) }}</dd>

                @if ($data['scope'])
                    <dt class="col-sm-3 text-muted">Scope</dt>
                    <dd class="col-sm-9">{{ $data['scope'] }}</dd>
                @endif

                @if ($data['registration_authority'])
                    <dt class="col-sm-3 text-muted">Registration Authority</dt>
                    <dd class="col-sm-9">{{ $data['registration_authority'] }}</dd>
                @endif
            </dl>
        </div>
    </div>

    {{-- UI Info ──────────────────────────────────────────────────────────── --}}
    @if (!empty($data['ui_info']))
        <div class="card border shadow-none mb-4">
            <div class="card-header bg-transparent border-bottom py-2">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-info-circle me-2 text-muted"></i>UI Info
                </h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    @foreach (collect($data['ui_info'])->sortBy('field') as $ui)
                        <dt class="col-sm-3 text-muted">{{ str_replace('_', ' ', ucfirst($ui['field'])) }}
                            @if (!empty($ui['lang'])) <span class="badge bg-secondary ms-1">{{ $ui['lang'] }}</span>@endif
                        </dt>
                        <dd class="col-sm-9">
                            @if ($ui['field'] === 'logo_url')
                                <a href="{{ $ui['value'] }}" target="_blank" rel="noopener">{{ $ui['value'] }}</a>
                                @if (!empty($ui['logo_width'])) <span class="text-muted ms-2">{{ $ui['logo_width'] }}×{{ $ui['logo_height'] }}px</span>@endif
                            @else
                                {{ $ui['value'] }}
                            @endif
                        </dd>
                    @endforeach
                </dl>
            </div>
        </div>
    @endif

    {{-- Contacts ─────────────────────────────────────────────────────────── --}}
    @if (!empty($data['contacts']))
        <div class="card border shadow-none mb-4">
            <div class="card-header bg-transparent border-bottom py-2">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-person me-2 text-muted"></i>Contacts
                </h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    @foreach ($data['contacts'] as $contact)
                        <dt class="col-sm-3 text-muted">{{ ucfirst($contact['type']) }}</dt>
                        <dd class="col-sm-9">
                            @if (!empty($contact['given_name']) || !empty($contact['sur_name']))
                                {{ trim(($contact['given_name'] ?? '') . ' ' . ($contact['sur_name'] ?? '')) }}
                                @if (!empty($contact['email']))
                                    &lt;{{ $contact['email'] }}&gt;
                                @endif
                            @else
                                {{ $contact['email'] ?? '' }}
                            @endif
                            @if (!empty($contact['phone']))
                                <span class="text-muted ms-2"><i class="bi bi-telephone"></i> {{ $contact['phone'] }}</span>
                            @endif
                        </dd>
                    @endforeach
                </dl>
            </div>
        </div>
    @endif

    {{-- Endpoints ────────────────────────────────────────────────────────── --}}
    @if (!empty($data['endpoints']))
        <div class="card border shadow-none mb-4">
            <div class="card-header bg-transparent border-bottom py-2">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-arrow-left-right me-2 text-muted"></i>Endpoints
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-borderless small mb-0">
                        <thead>
                            <tr class="text-muted border-bottom">
                                <th>Type</th>
                                <th>Binding</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['endpoints'] as $ep)
                                <tr>
                                    <td><span class="badge bg-secondary">{{ strtoupper($ep['type']) }}</span></td>
                                    <td class="text-muted small">{{ last(explode(':', $ep['binding'])) }}</td>
                                    <td class="font-monospace">{{ $ep['location'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Certificates ─────────────────────────────────────────────────────── --}}
    @if (!empty($data['certificates']))
        <div class="card border shadow-none mb-4">
            <div class="card-header bg-transparent border-bottom py-2">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-key me-2 text-muted"></i>Certificates
                </h6>
            </div>
            <div class="card-body">
                @foreach ($data['certificates'] as $cert)
                    <div class="mb-2 small">
                        <span class="badge bg-secondary me-2">{{ $cert['use'] }}</span>
                        <code class="text-muted">{{ substr($cert['pem'], 0, 60) }}…</code>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Entity categories + assurance ───────────────────────────────────── --}}
    @if (!empty($data['entity_categories']) || !empty($data['assurance_profiles']))
        <div class="card border shadow-none mb-4">
            <div class="card-header bg-transparent border-bottom py-2">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-tags me-2 text-muted"></i>REFEDS Attributes
                </h6>
            </div>
            <div class="card-body small">
                @foreach ($data['entity_categories'] as $cat)
                    <span class="badge bg-info text-dark me-1 mb-1">{{ $cat }}</span>
                @endforeach
                @foreach ($data['assurance_profiles'] as $prof)
                    <span class="badge bg-warning text-dark me-1 mb-1">{{ $prof }}</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Action buttons ───────────────────────────────────────────────────── --}}
    <div class="d-flex gap-2 justify-content-end mb-4">
        <a href="{{ route('entities.import.xml') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Start Over
        </a>
        <a href="{{ route('entities.create') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i> Edit in Full Form
        </a>
        <form method="POST" action="{{ route('entities.import.confirm') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-cloud-upload me-1"></i> Import Entity
            </button>
        </form>
    </div>

</div>
@endsection
