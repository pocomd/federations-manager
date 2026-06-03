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
    lastField: null,
    lastPos: 0,
    trackCursor(el) { this.lastField = el; this.lastPos = el.selectionStart; },
    insertPlaceholder(tag) {
        if (!this.lastField) return;
        const el = this.lastField;
        const val = el.value;
        el.value = val.slice(0, this.lastPos) + tag + val.slice(this.lastPos);
        this.lastPos += tag.length;
        el.selectionStart = el.selectionEnd = this.lastPos;
        el.dispatchEvent(new Event('input'));
        el.focus();
    }
}">
    {{-- Form column --}}
    <div class="col-lg-8">

        <div class="mb-3">
            <label class="form-label fw-medium" for="name">Template Name <span class="text-danger">*</span></label>
            <input type="text"
                   class="form-control @error('name') is-invalid @enderror"
                   id="name" name="name"
                   value="{{ old('name', $template->name ?? '') }}"
                   required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-medium" for="group">Group <span class="text-danger">*</span></label>
                <select class="form-select @error('group') is-invalid @enderror"
                        id="group" name="group" required>
                    @foreach ($groups as $key => $label)
                        <option value="{{ $key }}"
                                {{ old('group', $template->group ?? '') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('group')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium" for="lang">Language</label>
                <select class="form-select @error('lang') is-invalid @enderror"
                        id="lang" name="lang">
                    @foreach (['en'=>'English','ro'=>'Romanian','de'=>'German','fr'=>'French','ru'=>'Russian','pl'=>'Polish','lt'=>'Lithuanian','lv'=>'Latvian','et'=>'Estonian'] as $code => $label)
                        <option value="{{ $code }}"
                                {{ old('lang', $template->lang ?? 'en') === $code ? 'selected' : '' }}>
                            {{ $label }} ({{ $code }})
                        </option>
                    @endforeach
                </select>
                @error('lang')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3 d-flex align-items-end pb-1">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input"
                           type="checkbox" id="is_active" name="is_active" value="1"
                           {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-medium" for="subject">Subject <span class="text-danger">*</span></label>
            <input type="text"
                   class="form-control @error('subject') is-invalid @enderror"
                   id="subject" name="subject"
                   value="{{ old('subject', $template->subject ?? '') }}"
                   placeholder="Use [[placeholder]] syntax"
                   @focus="trackCursor($el)" @click="trackCursor($el)" @keyup="lastPos = $el.selectionStart"
                   required>
            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-medium" for="body">Body <span class="text-danger">*</span></label>
            <textarea class="form-control font-monospace @error('body') is-invalid @enderror"
                      id="body" name="body" rows="15"
                      placeholder="Use [[placeholder]] syntax for dynamic content"
                      @focus="trackCursor($el)" @click="trackCursor($el)" @keyup="lastPos = $el.selectionStart"
                      required>{{ old('body', $template->body ?? '') }}</textarea>
            <div class="form-text text-muted">Plain text body. Use [[placeholder]] for dynamic values.</div>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

    </div>

    {{-- Placeholder reference --}}
    <div class="col-lg-4">
        <div class="card border shadow-none">
            <div class="card-header bg-transparent border-bottom py-2">
                <span class="fw-semibold small">Available Placeholders</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach ($placeholders as $placeholder => $description)
                        <tr>
                            <td class="ps-2" style="width:55%;">
                                <code class="small" style="font-size:0.7rem;">{{ $placeholder }}</code>
                            </td>
                            <td class="text-muted small pe-1" style="font-size:0.75rem;">{{ $description }}</td>
                            <td class="pe-2">
                                <button type="button"
                                        class="btn btn-link btn-sm p-0"
                                        title="Insert at cursor"
                                        @click="insertPlaceholder('{{ $placeholder }}')">
                                    <i class="bi bi-cursor-text" style="font-size:0.75rem;"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
