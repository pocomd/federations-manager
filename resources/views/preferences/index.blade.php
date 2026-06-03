@extends('layouts.app')

@section('title', 'System Preferences')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">System Preferences</h1>
            <p class="text-muted mb-0 small">
                Application-wide configuration stored in the database.
            </p>
        </div>
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

    <form method="POST" action="{{ route('preferences.update') }}" novalidate>
        @csrf

        @php
            $categoryLabels = [
                'general'   => ['label' => 'General',        'icon' => 'bi-sliders'],
                'page'      => ['label' => 'Page',           'icon' => 'bi-layout-text-window'],
                'mail'      => ['label' => 'Mail',           'icon' => 'bi-envelope'],
                'authn'     => ['label' => 'Authentication', 'icon' => 'bi-shield-lock'],
                'federation'=> ['label' => 'Federation',     'icon' => 'bi-diagram-3'],
            ];

            $integerConstraints = [
                'session_timeout_minutes'       => ['min' => 5,   'max' => 1440],
                'max_login_attempts'            => ['min' => 3,   'max' => 20],
                'pending_membership_expiry_days'=> ['min' => 1,   'max' => 7],
            ];
        @endphp

        @foreach ($categoryLabels as $cat => $meta)
            @php $group = $preferences->get($cat, collect()); @endphp
            @if ($group->isEmpty()) @continue @endif

            <div class="card border shadow-none mb-4">
                <div class="card-header bg-transparent border-bottom py-2">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi {{ $meta['icon'] }} me-2 text-muted"></i>
                        {{ $meta['label'] }}
                    </h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width:220px;">Name</th>
                                <th>Description</th>
                                <th style="width:340px;">Value</th>
                                <th style="width:100px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group as $pref)
                                <tr>
                                    <td class="ps-3">
                                        <label class="fw-medium mb-0 small" for="p-{{ $pref->key }}">
                                            {{ $pref->label }}
                                        </label>
                                    </td>
                                    <td class="text-muted small">
                                        {{ $pref->description }}
                                    </td>
                                    <td>
                                        @if ($pref->type === 'boolean')
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="{{ $pref->key }}" value="0">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       id="p-{{ $pref->key }}"
                                                       name="{{ $pref->key }}"
                                                       value="1"
                                                       {{ $pref->typedValue() ? 'checked' : '' }}>
                                            </div>

                                        @elseif ($pref->type === 'integer')
                                            @php $c = $integerConstraints[$pref->key] ?? ['min' => 1, 'max' => null]; @endphp
                                            <input type="number"
                                                   class="form-control form-control-sm @error($pref->key) is-invalid @enderror"
                                                   id="p-{{ $pref->key }}"
                                                   name="{{ $pref->key }}"
                                                   value="{{ old($pref->key, $pref->value) }}"
                                                   min="{{ $c['min'] }}"
                                                   {{ $c['max'] !== null ? 'max="' . $c['max'] . '"' : '' }}>
                                            @error($pref->key)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror

                                        @elseif ($pref->type === 'textarea')
                                            <textarea class="form-control form-control-sm @error($pref->key) is-invalid @enderror"
                                                      id="p-{{ $pref->key }}"
                                                      name="{{ $pref->key }}"
                                                      rows="3">{{ old($pref->key, $pref->value) }}</textarea>
                                            @error($pref->key)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror

                                        @elseif ($pref->type === 'email')
                                            <input type="email"
                                                   class="form-control form-control-sm @error($pref->key) is-invalid @enderror"
                                                   id="p-{{ $pref->key }}"
                                                   name="{{ $pref->key }}"
                                                   value="{{ old($pref->key, $pref->value) }}">
                                            @error($pref->key)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror

                                        @elseif ($pref->type === 'url')
                                            <input type="text"
                                                   class="form-control form-control-sm @error($pref->key) is-invalid @enderror"
                                                   id="p-{{ $pref->key }}"
                                                   name="{{ $pref->key }}"
                                                   value="{{ old($pref->key, $pref->value) }}"
                                                   placeholder="https://registry.example.com">
                                            @error($pref->key)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror

                                        @else
                                            <input type="text"
                                                   class="form-control form-control-sm @error($pref->key) is-invalid @enderror"
                                                   id="p-{{ $pref->key }}"
                                                   name="{{ $pref->key }}"
                                                   value="{{ old($pref->key, $pref->value) }}">
                                            @error($pref->key)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        @endif
                                    </td>
                                    <td>
                                        @if ($pref->type === 'boolean')
                                            @if ($pref->typedValue())
                                                <span class="badge bg-success">Enabled</span>
                                            @else
                                                <span class="badge bg-secondary">Disabled</span>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        @can('federation.edit')
            <div class="d-flex justify-content-end mb-4">
                <button type="submit" class="btn btn-sm btn-primary d-flex align-items-center gap-2">
                    <i class="bi bi-save"></i>
                    Save Preferences
                </button>
            </div>
        @endcan

    </form>

</div>
@endsection
