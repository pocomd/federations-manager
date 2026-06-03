@extends('layouts.app')

@section('title', 'Mail Templates')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">Mail Templates</h1>
            <p class="text-muted small mb-0">
                Reusable email templates with <code>[[placeholder]]</code> syntax.
            </p>
        </div>
        @can('federation.edit')
        <a href="{{ route('mail.templates.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Template
        </a>
        @endcan
    </div>

    @foreach (\App\Models\MailTemplate::GROUPS as $groupKey => $groupLabel)
    @php $group = $templates->get($groupKey, collect()); @endphp
    <div class="card border shadow-none mb-4">
        <div class="card-header bg-transparent border-bottom py-2">
            <h6 class="mb-0 fw-semibold">{{ $groupLabel }}</h6>
        </div>
        <div class="card-body p-0">
            @if ($group->isEmpty())
            <p class="text-muted small p-3 mb-0">No templates in this group.</p>
            @else
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Name</th>
                        <th style="width:60px;">Lang</th>
                        <th>Subject Preview</th>
                        <th style="width:90px;">Status</th>
                        @can('federation.edit')
                        <th class="text-center" style="width:160px;">Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group as $template)
                    <tr>
                        <td class="ps-3 fw-medium small">{{ $template->name }}</td>
                        <td><span class="badge bg-secondary">{{ strtoupper($template->lang) }}</span></td>
                        <td class="small text-muted text-truncate" style="max-width:340px;">
                            {{ $template->subject }}
                        </td>
                        <td>
                            @if ($template->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        @can('federation.edit')
                        <td class="text-center">
                            <div>
                                <a href="{{ route('mail.templates.edit', $template) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="{{ route('mail.templates.preview', $template) }}"
                                   class="btn btn-sm btn-outline-info" target="_blank" title="Preview">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <form method="POST"
                                      action="{{ route('mail.templates.destroy', $template) }}"
                                      style="display:contents"
                                      onsubmit="event.preventDefault(); SwalDefault.fire({title: Lang.mail.delete_title, icon: 'warning', showCancelButton: true, confirmButtonText: Lang.confirm.confirm, confirmButtonColor: '#dc3545'}).then(r => { if (r.isConfirmed) this.submit() })">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                        @endcan
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
    @endforeach

</div>
@endsection
