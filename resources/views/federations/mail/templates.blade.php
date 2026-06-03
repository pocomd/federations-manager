@extends('layouts.app')

@section('title', 'Email Templates — ' . $federation->name)

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federations.index') }}">Federations</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('federations.show', $federation) }}">{{ $federation->name }}</a></li>
                    <li class="breadcrumb-item active">Email Templates</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-1">Email Templates</h1>
            <p class="text-muted small mb-0">Customise system email templates for this federation. Custom templates override the system default for all automated and manual emails sent in the context of this federation.</p>
        </div>
        <a href="{{ route('federations.mail.log', $federation) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clock-history me-1"></i> Mail Log
        </a>
    </div>

    <div class="card border shadow-none">
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Template</th>
                        <th>Group</th>
                        <th>Lang</th>
                        <th>Status</th>
                        <th class="text-center pe-3" style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($systemTemplates as $tpl)
                    @php
                        $key      = $tpl->group . '|' . $tpl->lang;
                        $override = $overrides->get($key);
                    @endphp
                    <tr>
                        <td class="ps-3 fw-medium">{{ $tpl->name }}</td>
                        <td class="small text-muted">{{ \App\Models\MailTemplate::GROUPS[$tpl->group] ?? $tpl->group }}</td>
                        <td><span class="badge bg-secondary">{{ strtoupper($tpl->lang) }}</span></td>
                        <td>
                            @if ($override)
                                <span class="badge bg-success">Custom</span>
                            @else
                                <span class="badge bg-light text-secondary border">System default</span>
                            @endif
                        </td>
                        <td class="text-center pe-3">
                            <a href="{{ route('federations.mail.templates.edit', [$federation, $tpl]) }}"
                               class="btn btn-sm btn-outline-primary" title="{{ $override ? 'Edit custom' : 'Customise' }}">
                                <i class="bi bi-pencil"></i>
                                {{ $override ? 'Edit' : 'Customise' }}
                            </a>
                            @if ($override)
                            <form method="POST"
                                  action="{{ route('federations.mail.templates.destroy', [$federation, $override]) }}"
                                  style="display:contents"
                                  onsubmit="event.preventDefault(); SwalDefault.fire({title: 'Reset to system default?', text: 'The custom template for this federation will be deleted.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Reset', confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger ms-1" title="Reset to system default">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
