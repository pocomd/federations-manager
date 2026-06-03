@extends('layouts.app')

@section('title', 'Scheduler Settings')

@section('content')

{{-- ── Run Now mini-forms (must appear before the main settings form) ──────── --}}
<form id="run-metadata"   method="POST" action="{{ route('scheduler.run', 'metadata') }}">@csrf</form>
<form id="run-validate"   method="POST" action="{{ route('scheduler.run', 'validate') }}">@csrf</form>
<form id="run-cert-check" method="POST" action="{{ route('scheduler.run', 'cert-check') }}">@csrf</form>
<form id="run-edugain"    method="POST" action="{{ route('scheduler.run', 'edugain') }}">@csrf</form>
<form id="run-cleanup"    method="POST" action="{{ route('scheduler.run', 'cleanup') }}">@csrf</form>

{{-- ── Validation errors ───────────────────────────────────────────────────── --}}
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

{{-- ── Main settings form ─────────────────────────────────────────────────── --}}
<form method="POST" action="{{ route('scheduler.update') }}" id="settings-form">
    @csrf

    @php
        $groupMeta = [
            'metadata'     => ['label' => 'Metadata Generation',     'icon' => 'bi-file-code',        'job' => 'metadata',   'run_form' => 'run-metadata'],
            'validation'   => ['label' => 'Entity Validation',       'icon' => 'bi-check2-circle',    'job' => 'validate',   'run_form' => 'run-validate'],
            'certificates' => ['label' => 'Certificate Monitoring',  'icon' => 'bi-key',              'job' => 'cert-check', 'run_form' => 'run-cert-check'],
            'edugain'      => ['label' => 'eduGAIN Sync',            'icon' => 'bi-cloud-download',   'job' => 'edugain',    'run_form' => 'run-edugain'],
            'cleanup'      => ['label' => 'Cleanup',                 'icon' => 'bi-trash',            'job' => 'cleanup',    'run_form' => 'run-cleanup'],
        ];

        $lastRunKeys = [
            'metadata'     => 'metadata',
            'validation'   => 'validate',
            'certificates' => 'cert-check',
            'edugain'      => 'edugain',
            'cleanup'      => 'cleanup',
        ];

        $selectOptions = [
            'metadata_auto_generate_interval' => [
                5 => '5 minutes', 10 => '10 minutes', 15 => '15 minutes',
                30 => '30 minutes', 60 => '1 hour',
            ],
            'validation_schedule_day' => [
                'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
                'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday',
                'sunday' => 'Sunday', 'daily' => 'Every day',
            ],
            'edugain_sync_interval_hours' => [
                1 => '1 hour', 3 => '3 hours', 6 => '6 hours',
                12 => '12 hours', 24 => '24 hours',
            ],
        ];

        // Per-key min/max for integer inputs
        $integerConstraints = [
            'metadata_valid_until_hours'    => ['min' => 1,  'max' => 168],
            'metadata_cache_duration_hours' => ['min' => 1,  'max' => 72],
            'cert_notify_days_critical'     => ['min' => 1,  'max' => 30],
            'cert_notify_days_warning'      => ['min' => 1,  'max' => 60],
            'cert_notify_days_advisory'     => ['min' => 1,  'max' => 120],
            'cert_notify_days_info'         => ['min' => 1,  'max' => 365],
            'cleanup_metadata_days'         => ['min' => 1,  'max' => 365],
            'cleanup_validation_days'       => ['min' => 1,  'max' => 730],
            'cleanup_audit_days'            => ['min' => 30, 'max' => 3650],
        ];

        // Keys that are HH:MM time strings
        $timeFields = ['validation_schedule_time', 'cert_check_time'];

        // Keys that are URL strings
        $urlFields = ['edugain_metadata_url'];
    @endphp

    @foreach ($groupMeta as $groupName => $meta)
        @php
            $groupSettings  = $settings->get($groupName, collect());
            $lastRunKey     = $lastRunKeys[$groupName];
            $lastRun        = $lastRuns[$lastRunKey] ?? null;
        @endphp

        <div class="card border shadow-none mb-4">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center
                        justify-content-between py-3">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi {{ $meta['icon'] }} me-2 text-muted"></i>
                    {{ $meta['label'] }}
                </h6>
                <div class="d-flex align-items-center gap-3">
                    @if ($lastRun)
                        <span class="text-muted small">
                            Last run: {{ $lastRun->diffForHumans() }}
                        </span>
                    @endif
                    @can('federation.edit')
                        <button type="submit"
                                form="{{ $meta['run_form'] }}"
                                class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                            <i class="bi bi-play-circle"></i>
                            Run Now
                        </button>
                    @endcan
                </div>
            </div>
            <div class="card-body">
                @if ($groupSettings->isEmpty())
                    <p class="text-muted small mb-0">No settings configured for this group.</p>
                @else
                    <div class="row g-3">
                        @foreach ($groupSettings as $setting)
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-medium mb-1" for="s-{{ $setting->key }}">
                                    {{ $setting->label }}
                                </label>
                                @if ($setting->description)
                                    <div class="text-muted small mb-1">{{ $setting->description }}</div>
                                @endif

                                @if ($setting->type === 'boolean')
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="{{ $setting->key }}" value="0">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               id="s-{{ $setting->key }}"
                                               name="{{ $setting->key }}"
                                               value="1"
                                               {{ $setting->typedValue() ? 'checked' : '' }}>
                                        <label class="form-check-label" for="s-{{ $setting->key }}">
                                            {{ $setting->typedValue() ? 'Enabled' : 'Disabled' }}
                                        </label>
                                    </div>

                                @elseif ($setting->type === 'integer')
                                    @php
                                        $constraints = $integerConstraints[$setting->key] ?? ['min' => 1, 'max' => null];
                                    @endphp
                                    <input type="number"
                                           class="form-control form-control-sm @error($setting->key) is-invalid @enderror"
                                           id="s-{{ $setting->key }}"
                                           name="{{ $setting->key }}"
                                           value="{{ old($setting->key, $setting->value) }}"
                                           min="{{ $constraints['min'] }}"
                                           {{ $constraints['max'] !== null ? 'max="' . $constraints['max'] . '"' : '' }}
                                           required>
                                    @error($setting->key)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror

                                @elseif ($setting->type === 'select')
                                    <select class="form-select form-select-sm @error($setting->key) is-invalid @enderror"
                                            id="s-{{ $setting->key }}"
                                            name="{{ $setting->key }}">
                                        @foreach ($selectOptions[$setting->key] ?? [] as $val => $optLabel)
                                            <option value="{{ $val }}"
                                                    {{ (string) old($setting->key, $setting->value) === (string) $val ? 'selected' : '' }}>
                                                {{ $optLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error($setting->key)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror

                                @elseif (in_array($setting->key, $timeFields))
                                    <input type="text"
                                           class="form-control form-control-sm @error($setting->key) is-invalid @enderror"
                                           id="s-{{ $setting->key }}"
                                           name="{{ $setting->key }}"
                                           value="{{ old($setting->key, $setting->value) }}"
                                           pattern="^([01]\d|2[0-3]):[0-5]\d$"
                                           placeholder="HH:MM"
                                           required>
                                    @error($setting->key)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror

                                @elseif (in_array($setting->key, $urlFields))
                                    <input type="url"
                                           class="form-control form-control-sm @error($setting->key) is-invalid @enderror"
                                           id="s-{{ $setting->key }}"
                                           name="{{ $setting->key }}"
                                           value="{{ old($setting->key, $setting->value) }}"
                                           placeholder="https://mds.edugain.org/edugain-v2.xml"
                                           pattern="https://.+"
                                           required>
                                    @error($setting->key)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror

                                @else
                                    <input type="text"
                                           class="form-control form-control-sm @error($setting->key) is-invalid @enderror"
                                           id="s-{{ $setting->key }}"
                                           name="{{ $setting->key }}"
                                           value="{{ old($setting->key, $setting->value) }}">
                                    @error($setting->key)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @can('federation.edit')
        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center gap-2">
                <i class="bi bi-save"></i>
                Save Settings
            </button>
        </div>
    @endcan

</form>

@endsection
