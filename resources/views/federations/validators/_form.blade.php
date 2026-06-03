{{-- Section 1: Connection settings --}}
<div class="card mb-4">
    <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
        <i class="bi bi-plug me-1"></i> Connection Settings
    </div>
    <div class="card-body">

        <div class="mb-3">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" id="name" name="name"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $validator->name ?? '') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="2"
                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $validator->description ?? '') }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="url" class="form-label">URL <span class="text-danger">*</span></label>
            <input type="url" id="url" name="url"
                   class="form-control @error('url') is-invalid @enderror"
                   value="{{ old('url', $validator->url ?? '') }}"
                   placeholder="https://validator.example.org/validate" required>
            @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label for="http_method" class="form-label">HTTP Method <span class="text-danger">*</span></label>
                <select id="http_method" name="http_method"
                        class="form-select @error('http_method') is-invalid @enderror">
                    <option value="GET"  {{ old('http_method', $validator->http_method ?? 'GET')  === 'GET'  ? 'selected' : '' }}>GET</option>
                    <option value="POST" {{ old('http_method', $validator->http_method ?? 'GET')  === 'POST' ? 'selected' : '' }}>POST</option>
                </select>
                @error('http_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label for="metadata_arg_name" class="form-label">Metadata Param Name <span class="text-danger">*</span></label>
                <input type="text" id="metadata_arg_name" name="metadata_arg_name"
                       class="form-control @error('metadata_arg_name') is-invalid @enderror"
                       value="{{ old('metadata_arg_name', $validator->metadata_arg_name ?? 'metadata') }}" required>
                @error('metadata_arg_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label for="args_separator" class="form-label">Args Separator <span class="text-danger">*</span></label>
                <input type="text" id="args_separator" name="args_separator"
                       class="form-control @error('args_separator') is-invalid @enderror"
                       value="{{ old('args_separator', $validator->args_separator ?? '&') }}" required>
                <div class="form-text">Use <code>&amp;</code> for query-string style, <code>/</code> for path style.</div>
                @error('args_separator')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="timeout" class="form-label">Timeout (s) <span class="text-danger">*</span></label>
                <input type="number" id="timeout" name="timeout" min="5" max="120"
                       class="form-control @error('timeout') is-invalid @enderror"
                       value="{{ old('timeout', $validator->timeout ?? 30) }}" required>
                <div class="form-text">Max wait before treating as a timeout error (5–120 s).</div>
                @error('timeout')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="optional_args" class="form-label">Optional Additional Arguments</label>
            <textarea id="optional_args" name="optional_args" rows="2"
                      class="form-control @error('optional_args') is-invalid @enderror"
                      placeholder="key=value&key2=value2">{{ old('optional_args', $validator->optional_args ?? '') }}</textarea>
            <div class="form-text">Additional GET/POST params e.g. key=value&amp;key2=value2</div>
            @error('optional_args')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="form-check form-switch mb-1">
                    <input type="hidden" name="enabled" value="0">
                    <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1"
                           {{ old('enabled', $validator->enabled ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-medium" for="enabled">Active</label>
                </div>
                <div class="form-text">When off, this validator is skipped everywhere. Manual tests still work.</div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch mb-1">
                    <input type="hidden" name="enabled_on_registration" value="0">
                    <input class="form-check-input" type="checkbox" id="enabled_on_registration"
                           name="enabled_on_registration" value="1"
                           {{ old('enabled_on_registration', $validator->enabled_on_registration ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label fw-medium" for="enabled_on_registration">Run on Registration</label>
                </div>
                <div class="form-text">Runs automatically when an entity is registered, updated, or approved. The entity is never blocked by the result.</div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch mb-1">
                    <input type="hidden" name="mandatory" value="0">
                    <input class="form-check-input" type="checkbox" id="mandatory" name="mandatory" value="1"
                           {{ old('mandatory', $validator->mandatory ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label fw-medium" for="mandatory">Mandatory</label>
                </div>
                <div class="form-text">If this validator reports an error or critical result, a notification is sent to all federation managers.</div>
            </div>
        </div>

    </div>
</div>

{{-- Section 2: Response parsing --}}
<div class="card mb-4">
    <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
        <i class="bi bi-code-slash me-1"></i> Response Parsing
    </div>
    <div class="card-body">

        <p class="text-muted small mb-3">Configure how to read the validator XML response.</p>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label for="response_code_element" class="form-label">Code Element <span class="text-danger">*</span></label>
                <input type="text" id="response_code_element" name="response_code_element"
                       class="form-control @error('response_code_element') is-invalid @enderror"
                       value="{{ old('response_code_element', $validator->response_code_element ?? 'returncode') }}" required>
                @error('response_code_element')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="response_message_element" class="form-label">Message Element <span class="text-danger">*</span></label>
                <input type="text" id="response_message_element" name="response_message_element"
                       class="form-control @error('response_message_element') is-invalid @enderror"
                       value="{{ old('response_message_element', $validator->response_message_element ?? 'message') }}" required>
                @error('response_message_element')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label for="success_value" class="form-label">Success Value <span class="text-danger">*</span></label>
                <input type="text" id="success_value" name="success_value"
                       class="form-control @error('success_value') is-invalid @enderror"
                       value="{{ old('success_value', $validator->success_value ?? '0') }}" required>
                @error('success_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="warning_value" class="form-label">Warning Value <span class="text-danger">*</span></label>
                <input type="text" id="warning_value" name="warning_value"
                       class="form-control @error('warning_value') is-invalid @enderror"
                       value="{{ old('warning_value', $validator->warning_value ?? '1') }}" required>
                @error('warning_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="error_value" class="form-label">Error Value <span class="text-danger">*</span></label>
                <input type="text" id="error_value" name="error_value"
                       class="form-control @error('error_value') is-invalid @enderror"
                       value="{{ old('error_value', $validator->error_value ?? '2') }}" required>
                @error('error_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="critical_value" class="form-label">Critical Value <span class="text-danger">*</span></label>
                <input type="text" id="critical_value" name="critical_value"
                       class="form-control @error('critical_value') is-invalid @enderror"
                       value="{{ old('critical_value', $validator->critical_value ?? '3') }}" required>
                @error('critical_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <p class="small text-muted mb-1">Example response:</p>
        <pre class="bg-light p-3 rounded small">&lt;?xml version="1.0"?&gt;
&lt;validation&gt;
  &lt;returncode&gt;0&lt;/returncode&gt;
  &lt;message&gt;Validation passed&lt;/message&gt;
&lt;/validation&gt;</pre>

    </div>
</div>
