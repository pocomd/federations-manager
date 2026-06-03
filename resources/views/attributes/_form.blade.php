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

<div class="mb-3">
    <label class="form-label fw-medium" for="name">Short Name <span class="text-danger">*</span></label>
    <input type="text"
           class="form-control @error('name') is-invalid @enderror"
           id="name"
           name="name"
           value="{{ old('name', $attribute->name ?? '') }}"
           placeholder="eduPersonPrincipalName"
           required>
    <div class="form-text text-muted">Camel-case technical identifier. Letters, numbers, dashes and underscores only.</div>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label fw-medium" for="full_name">Full Name <span class="text-danger">*</span></label>
    <input type="text"
           class="form-control @error('full_name') is-invalid @enderror"
           id="full_name"
           name="full_name"
           value="{{ old('full_name', $attribute->full_name ?? '') }}"
           placeholder="Principal Name"
           required>
    @error('full_name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label fw-medium" for="saml2_oid">SAML2 OID</label>
    <input type="text"
           class="form-control font-monospace @error('saml2_oid') is-invalid @enderror"
           id="saml2_oid"
           name="saml2_oid"
           value="{{ old('saml2_oid', $attribute->saml2_oid ?? '') }}"
           placeholder="urn:oid:1.3.6.1.4.1.5923.1.1.1.6">
    @error('saml2_oid')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label fw-medium" for="saml1_urn">SAML1 URN</label>
    <input type="text"
           class="form-control font-monospace @error('saml1_urn') is-invalid @enderror"
           id="saml1_urn"
           name="saml1_urn"
           value="{{ old('saml1_urn', $attribute->saml1_urn ?? '') }}"
           placeholder="urn:mace:dir:attribute-def:eduPersonPrincipalName">
    @error('saml1_urn')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label fw-medium" for="description">Description</label>
    <textarea class="form-control @error('description') is-invalid @enderror"
              id="description"
              name="description"
              rows="3">{{ old('description', $attribute->description ?? '') }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <div class="form-check form-switch">
        <input type="hidden" name="is_required" value="0">
        <input class="form-check-input"
               type="checkbox"
               id="is_required"
               name="is_required"
               value="1"
               {{ old('is_required', $attribute->is_required ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_required">
            Requires justification — SP must provide a reason when requesting this attribute
        </label>
    </div>
</div>

<div class="mb-3">
    <div class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input"
               type="checkbox"
               id="is_active"
               name="is_active"
               value="1"
               {{ old('is_active', $attribute->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">Active</label>
    </div>
</div>
