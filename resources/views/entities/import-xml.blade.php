@extends('layouts.app')

@section('title', 'Import Entity from XML')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">Import Entity from XML</h1>
            <p class="text-muted mb-0 small">
                Paste a SAML2 <code>&lt;md:EntityDescriptor&gt;</code> XML document to parse and pre-fill the registration form.
            </p>
        </div>
        <a href="{{ route('entities.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Entities
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first('xml') }}
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card border shadow-none">
                <div class="card-header bg-transparent border-bottom py-2">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-code-square me-2 text-muted"></i>EntityDescriptor XML
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('entities.import.xml.submit') }}"
                          enctype="multipart/form-data" id="xml-import-form">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-medium">Upload XML file</label>
                            <input type="file"
                                   id="xml_file"
                                   name="xml_file"
                                   accept=".xml,application/xml,text/xml"
                                   class="form-control form-control-sm @error('xml_file') is-invalid @enderror">
                            @error('xml_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Select an <code>.xml</code> file — its contents will be loaded into the text area below.</div>
                        </div>
                        <div class="mb-3">
                            <label for="xml" class="form-label fw-medium">Or paste XML</label>
                            <textarea
                                id="xml"
                                name="xml"
                                class="form-control font-monospace @error('xml') is-invalid @enderror"
                                rows="15"
                                placeholder="<?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot;?>&#10;<md:EntityDescriptor ...>&#10;  ...&#10;</md:EntityDescriptor>"
                                spellcheck="false">{{ old('xml') }}</textarea>
                            @error('xml')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-search me-1"></i> Parse XML
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    onclick="document.getElementById('xml').value=''; document.getElementById('xml_file').value='';">
                                <i class="bi bi-x-circle me-1"></i> Clear
                            </button>
                        </div>
                    </form>
                </div>

                @push('scripts')
                <script>
                document.getElementById('xml_file').addEventListener('change', function () {
                    const file = this.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = e => { document.getElementById('xml').value = e.target.result; };
                    reader.readAsText(file);
                });
                </script>
                @endpush
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border shadow-none">
                <div class="card-header bg-transparent border-bottom py-2">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-info-circle me-2 text-muted"></i>What gets parsed
                    </h6>
                </div>
                <div class="card-body small">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i>entityID and type (IdP / SP)</li>
                        <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i>Display names, description, logo</li>
                        <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i>Organization details</li>
                        <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i>Technical &amp; security contacts</li>
                        <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i>SSO / ACS / SLO endpoints</li>
                        <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i>Signing &amp; encryption certificates</li>
                        <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i>Entity categories (REFEDS)</li>
                        <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i>Shibboleth scope</li>
                        <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i>Registration authority</li>
                    </ul>
                </div>
            </div>

            <div class="card border shadow-none mt-3">
                <div class="card-header bg-transparent border-bottom py-2">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-question-circle me-2 text-muted"></i>Other import options
                    </h6>
                </div>
                <div class="card-body">
                    <a href="{{ route('entities.import.array') }}" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                        <i class="bi bi-braces me-1"></i> Import from JSON
                    </a>
                    <a href="{{ route('entities.create') }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-pencil me-1"></i> Fill form manually
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
