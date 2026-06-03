@extends('layouts.app')

@section('title', 'Send Email — ' . $federation->name)

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('federations.index') }}">Federations</a></li>
                <li class="breadcrumb-item">
                    <a href="{{ route('federations.show', $federation) }}">{{ $federation->name }}</a>
                </li>
                <li class="breadcrumb-item active">Send Email</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-1">Send Email</h1>
        <p class="text-muted small mb-0">Send an email to entity contacts in this federation.</p>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4" x-data="{
        entityType: 'all',
        subject: '',
        body: '',
        templateId: '',
        entityCount: {{ $entityCount }},
        idpCount: {{ $idpCount }},
        spCount: {{ $spCount }},
        allEmailCount: {{ $allEmailCount }},
        idpEmailCount: {{ $idpEmailCount }},
        spEmailCount:  {{ $spEmailCount }},
        preview: null,
        previewLoading: false,
        showPreview: false,
        get recipientEntityCount() {
            if (this.entityType === 'idp') return this.idpCount;
            if (this.entityType === 'sp')  return this.spCount;
            return this.entityCount;
        },
        get recipientEmailCount() {
            if (this.entityType === 'idp') return this.idpEmailCount;
            if (this.entityType === 'sp')  return this.spEmailCount;
            return this.allEmailCount;
        },
        loadTemplate(id) {
            this.templateId = id;
            if (!id) return;
            fetch('/mail/templates/' + id + '/content')
                .then(r => r.json())
                .then(data => {
                    this.subject = data.subject;
                    this.body    = data.body;
                });
        },
        async fetchPreview() {
            if (!this.subject && !this.body) return;
            this.previewLoading = true;
            this.showPreview = false;
            const resp = await fetch('{{ route('federations.mail.preview', $federation) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({
                    template_id:      this.templateId || '{{ $templates->first()?->id }}',
                    subject_override: this.subject,
                    body_override:    this.body,
                })
            });
            this.preview = await resp.json();
            this.previewLoading = false;
            this.showPreview = true;
        },
        confirmSend(formEl) {
            var entities = this.recipientEntityCount;
            var emails   = this.recipientEmailCount;
            SwalDefault.fire({
                title: Lang.mail.send_title,
                text: entities + ' entities — approximately ' + emails + ' emails will be sent.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: Lang.confirm.confirm,
            }).then(function(r) { if (r.isConfirmed) formEl.submit() });
        },
    }">

        {{-- Compose form --}}
        <div class="col-lg-8">
            <div class="card border shadow-none">
                <div class="card-body">
                    <form method="POST"
                          action="{{ route('federations.mail.send', $federation) }}"
                          @submit.prevent="confirmSend($el)">
                        @csrf

                        {{-- Template selector --}}
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label fw-medium mb-0" for="template_id">Load Template (optional)</label>
                                <a href="{{ route('federations.mail.templates.index', $federation) }}"
                                   class="small text-decoration-none">
                                    <i class="bi bi-pencil-square me-1"></i>Manage federation templates
                                </a>
                            </div>
                            <select class="form-select"
                                    id="template_id"
                                    name="template_id"
                                    @change="loadTemplate($event.target.value)">
                                <option value="">— Select a template —</option>
                                @foreach ($templates->groupBy('group') as $groupKey => $groupTemplates)
                                    <optgroup label="{{ \App\Models\MailTemplate::GROUPS[$groupKey] ?? $groupKey }}">
                                        @foreach ($groupTemplates as $tpl)
                                            <option value="{{ $tpl->id }}">
                                                {{ $tpl->_system_name ?? $tpl->name }}
                                                ({{ strtoupper($tpl->lang) }})
                                                {{ $tpl->_is_custom ? '★ Custom' : '' }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <div class="form-text text-muted">Templates marked ★ Custom use this federation's customised version.</div>
                        </div>

                        {{-- Entity type --}}
                        <div class="mb-3">
                            <label class="form-label fw-medium">Recipients</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="entity_type"
                                           id="et_all" value="all" x-model="entityType" checked>
                                    <label class="form-check-label" for="et_all">
                                        All entities (<span x-text="entityCount"></span> entities, ~<span x-text="allEmailCount"></span> emails)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="entity_type"
                                           id="et_idp" value="idp" x-model="entityType">
                                    <label class="form-check-label" for="et_idp">
                                        IdPs only (<span x-text="idpCount"></span> entities, ~<span x-text="idpEmailCount"></span> emails)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="entity_type"
                                           id="et_sp" value="sp" x-model="entityType">
                                    <label class="form-check-label" for="et_sp">
                                        SPs only (<span x-text="spCount"></span> entities, ~<span x-text="spEmailCount"></span> emails)
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Contact types --}}
                        <div class="mb-3">
                            <label class="form-label fw-medium">Contact Types <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3 flex-wrap">
                                @foreach (['technical'=>'Technical','support'=>'Support','security'=>'Security','administrative'=>'Administrative'] as $type => $label)
                                <div class="form-check">
                                    <input class="form-check-input @error('contact_types') is-invalid @enderror"
                                           type="checkbox"
                                           name="contact_types[]"
                                           id="ct_{{ $type }}"
                                           value="{{ $type }}"
                                           {{ $type === 'technical' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="ct_{{ $type }}">{{ $label }}</label>
                                </div>
                                @endforeach
                            </div>
                            @error('contact_types')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Subject --}}
                        <div class="mb-3">
                            <label class="form-label fw-medium" for="subject">Subject <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control @error('subject') is-invalid @enderror"
                                   id="subject" name="subject"
                                   x-model="subject"
                                   value="{{ old('subject') }}"
                                   required>
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Body --}}
                        <div class="mb-4">
                            <label class="form-label fw-medium" for="body">Body <span class="text-danger">*</span></label>
                            <textarea class="form-control font-monospace @error('body') is-invalid @enderror"
                                      id="body" name="body"
                                      x-model="body"
                                      rows="12"
                                      required>{{ old('body') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-send me-1"></i>
                                Send to <span x-text="recipientEntityCount"></span> entities
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm"
                                    @click="fetchPreview()" :disabled="previewLoading">
                                <span x-show="previewLoading" class="spinner-border spinner-border-sm me-1"></span>
                                <i class="bi bi-eye me-1" x-show="!previewLoading"></i> Preview
                            </button>
                            <a href="{{ route('federations.show', $federation) }}"
                               class="btn btn-outline-secondary btn-sm">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Preview panel --}}
            <div class="card border shadow-none mt-3" x-show="showPreview" x-cloak>
                <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                    <i class="bi bi-eye me-1"></i> Preview (sample entity)
                </div>
                <div class="card-body small">
                    <p class="mb-1"><strong>Subject:</strong> <span x-text="preview?.subject"></span></p>
                    <hr class="my-2">
                    <pre class="mb-0 small" style="white-space:pre-wrap;font-family:inherit;" x-text="preview?.body"></pre>
                </div>
            </div>
        </div>

        {{-- Info panel --}}
        <div class="col-lg-4">
            <div class="card border shadow-none mb-3">
                <div class="card-header bg-transparent border-bottom py-2">
                    <span class="fw-semibold small">Federation Info</span>
                </div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted">Name</dt>
                        <dd class="col-7">{{ $federation->name }}</dd>
                        <dt class="col-5 text-muted">Active IdPs</dt>
                        <dd class="col-7">{{ $idpCount }}</dd>
                        <dt class="col-5 text-muted">Active SPs</dt>
                        <dd class="col-7">{{ $spCount }}</dd>
                    </dl>
                    <a href="{{ route('federations.mail.log', $federation) }}"
                       class="btn btn-outline-secondary btn-sm mt-2 w-100">
                        <i class="bi bi-clock-history me-1"></i> View Mail Log
                    </a>
                </div>
            </div>

            <div class="card border shadow-none">
                <div class="card-header bg-transparent border-bottom py-2">
                    <span class="fw-semibold small">Available Placeholders</span>
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
