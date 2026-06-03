@extends('layouts.app')

@section('title', 'Customise Template — ' . $federation->name)

@section('content')
<div class="container-fluid py-4 px-4"
     x-data="{
         subject: @js($override?->subject ?? $system->subject),
         body:    @js($override?->body ?? $system->body),
         preview: null,
         loading: false,
         showPreview: false,
         async fetchPreview() {
             this.loading = true;
             this.showPreview = false;
             const resp = await fetch('{{ route('federations.mail.preview', $federation) }}?template_id={{ $override?->id ?? $system->id }}&_subject=' + encodeURIComponent(this.subject) + '&_body=' + encodeURIComponent(this.body), {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                 body: JSON.stringify({ template_id: '{{ $override?->id ?? $system->id }}', subject_override: this.subject, body_override: this.body })
             });
             this.preview = await resp.json();
             this.loading = false;
             this.showPreview = true;
         }
     }">

    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('federations.index') }}">Federations</a></li>
                <li class="breadcrumb-item"><a href="{{ route('federations.show', $federation) }}">{{ $federation->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('federations.mail.templates.index', $federation) }}">Email Templates</a></li>
                <li class="breadcrumb-item active">{{ $system->name }}</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">
            {{ $override ? 'Edit custom template' : 'Customise template' }}:
            <span class="text-muted fw-normal">{{ $system->name }}</span>
        </h1>
    </div>

    @if ($override)
    <div class="alert alert-info small mb-4">
        <i class="bi bi-info-circle me-1"></i>
        This federation has a custom version of this template. Editing replaces it. Use <strong>Reset to default</strong> on the templates list to revert to the system template.
    </div>
    @endif

    <div class="row g-4">
        {{-- Edit form --}}
        <div class="col-lg-7">
            <form method="POST" action="{{ route('federations.mail.templates.store', $federation) }}">
                @csrf
                <input type="hidden" name="source_template_id" value="{{ $system->id }}">

                <div class="card border shadow-none mb-3">
                    <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                        <i class="bi bi-envelope me-1"></i> Template content
                    </div>
                    <div class="card-body">

                        <div class="mb-3">
                            <label class="form-label fw-medium" for="subject">Subject <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control @error('subject') is-invalid @enderror"
                                   id="subject" name="subject"
                                   x-model="subject"
                                   value="{{ old('subject', $override?->subject ?? $system->subject) }}"
                                   required>
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-medium" for="body">Body <span class="text-danger">*</span></label>
                            <textarea class="form-control font-monospace @error('body') is-invalid @enderror"
                                      id="body" name="body"
                                      x-model="body"
                                      rows="16"
                                      required>{{ old('body', $override?->body ?? $system->body) }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-save me-1"></i> Save Custom Template
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" @click="fetchPreview()" :disabled="loading">
                        <span x-show="loading" class="spinner-border spinner-border-sm me-1"></span>
                        <i class="bi bi-eye me-1" x-show="!loading"></i> Preview
                    </button>
                    <a href="{{ route('federations.mail.templates.index', $federation) }}"
                       class="btn btn-outline-secondary btn-sm">Cancel</a>
                </div>
            </form>
        </div>

        {{-- Sidebar: placeholders + preview --}}
        <div class="col-lg-5">
            {{-- Preview panel --}}
            <div class="card border shadow-none mb-3" x-show="showPreview" x-cloak>
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-eye me-1"></i> Preview (sample data)
                </div>
                <div class="card-body small">
                    <p class="mb-1"><strong>Subject:</strong> <span x-text="preview?.subject"></span></p>
                    <hr class="my-2">
                    <pre class="mb-0 small" style="white-space:pre-wrap;font-family:inherit;" x-text="preview?.body"></pre>
                </div>
            </div>

            {{-- System template reference --}}
            @if ($override)
            <div class="card border shadow-none mb-3">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> System default (reference)
                </div>
                <div class="card-body small">
                    <p class="mb-1"><strong>Subject:</strong> {{ $system->subject }}</p>
                    <hr class="my-2">
                    <pre class="mb-0 small text-muted" style="white-space:pre-wrap;font-family:inherit;">{{ $system->body }}</pre>
                </div>
            </div>
            @endif

            {{-- Placeholders --}}
            <div class="card border shadow-none">
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-braces me-1"></i> Available Placeholders
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @foreach (\App\Models\MailTemplate::PLACEHOLDERS as $ph => $desc)
                            <tr>
                                <td class="ps-2"><code style="font-size:0.7rem;">{{ $ph }}</code></td>
                                <td class="small text-muted pe-2" style="font-size:0.75rem;">{{ $desc }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
