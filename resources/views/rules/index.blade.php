@extends('layouts.app')

@section('title', 'Compliance Rules — Federation Registry')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">
                <i class="bi bi-shield-check me-2"></i>Compliance Rules
            </h1>
            <p class="text-muted small mb-0">
                Global metadata compliance rules — toggle active state across all federations.
            </p>
        </div>
        @can('federation.edit')
        <div class="d-flex align-items-center gap-3">
            <p class="text-muted small mb-0">{{ __('app.sync_rules_description') }}</p>
            <form method="POST" action="{{ route('rules.sync') }}">
                @csrf
                <button class="btn btn-outline-secondary btn-sm text-nowrap">
                    <i class="bi bi-arrow-repeat me-1"></i>{{ __('app.action_sync_rules') }}
                </button>
            </form>
        </div>
        @endcan
    </div>

    @php
        $grouped = $rules->groupBy('group');
        $groupOrder = ['structural', 'certificate', 'refeds', 'xsd'];
        $groupLabels = [
            'structural'  => 'Structural (S01–S10)',
            'certificate' => 'Certificate (C01–C05)',
            'refeds'      => 'REFEDS / eduGAIN (R01–R15)',
            'xsd'         => 'XSD Schema (X01)',
        ];
        $groupIcons = [
            'structural'  => 'bi-diagram-3',
            'certificate' => 'bi-patch-check',
            'refeds'      => 'bi-globe2',
            'xsd'         => 'bi-filetype-xml',
        ];
    @endphp

    @if($rules->isEmpty())
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            No rule definitions found. Run <code>php artisan rules:sync</code> to discover and register rule classes.
        </div>
    @else
        @foreach($groupOrder as $group)
            @if($grouped->has($group))
            <div class="card mb-4">
                <div class="card-header py-2 fw-semibold">
                    <i class="bi {{ $groupIcons[$group] ?? 'bi-check-circle' }} me-2"></i>
                    {{ $groupLabels[$group] ?? ucfirst($group) }}
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width:6rem">{{ __('app.label_rule_id') }}</th>
                                <th>{{ __('app.label_rule_name') }}</th>
                                <th style="width:7rem" class="text-center">{{ __('app.label_applies_to') }}</th>
                                <th style="width:7rem" class="text-center">{{ __('app.label_severity') }}</th>
                                <th style="width:6rem" class="text-center">{{ __('app.label_active') }}</th>
                                @can('federation.edit')
                                <th style="width:6rem" class="text-center pe-3">{{ __('app.label_toggle') }}</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($grouped[$group]->sortBy('id') as $rule)
                            <tr class="{{ $rule->active ? '' : 'table-secondary text-muted' }}">
                                <td class="ps-3">
                                    <code class="fw-bold">{{ $rule->id }}</code>
                                </td>
                                <td>
                                    <span>{{ $rule->name }}</span>
                                    <div class="text-muted small mt-1">{{ $rule->description }}</div>
                                </td>
                                <td class="text-center">
                                    @switch($rule->applies_to)
                                        @case('idp')
                                            <span class="badge bg-info text-dark" title="{{ __('app.applies_to_idp_desc') }}">IdP only</span>
                                            @break
                                        @case('sp')
                                            <span class="badge bg-primary" title="{{ __('app.applies_to_sp_desc') }}">SP only</span>
                                            @break
                                        @case('oidc')
                                            <span class="badge bg-warning text-dark">OIDC only</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary" title="{{ __('app.applies_to_both_desc') }}">Both</span>
                                    @endswitch
                                </td>
                                <td class="text-center">
                                    @if($rule->default_severity === 'error')
                                        <span class="badge bg-danger">Error</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Warning</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($rule->active)
                                        <span class="badge bg-success">ON</span>
                                    @else
                                        <span class="badge bg-secondary">OFF</span>
                                    @endif
                                </td>
                                @can('federation.edit')
                                <td class="text-center pe-3">
                                    <form method="POST" action="{{ route('rules.toggle', $rule) }}">
                                        @csrf
                                        <div class="form-check form-switch d-flex justify-content-center mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   {{ $rule->active ? 'checked' : '' }}
                                                   onchange="this.form.submit()">
                                        </div>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        @endforeach
    @endif

</div>
@endsection
