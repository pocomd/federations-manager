@extends('layouts.app')

@section('title', 'Compliance Rules — ' . ($entity->getDisplayName() ?? $entity->entity_id) . ' — Federation Registry')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('entities.index') }}">Entities</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('entities.show', $entity) }}">{{ $entity->entity_id }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Compliance Rules</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-0">
                <i class="bi bi-shield-check me-2"></i>Compliance Rules
            </h1>
            <p class="text-muted small mb-0">
                Entity-level rule overrides (highest priority — supersede federation and global defaults).
            </p>
        </div>
    </div>

    <div class="alert alert-info small">
        <i class="bi bi-info-circle me-2"></i>
        Priority: <strong>Entity override</strong> → Federation override → Rule default.
        The <em>Federation baseline</em> column shows the current federation-level setting.
    </div>

    @php
        $grouped    = $rules->groupBy('group');
        $groupOrder = ['structural', 'certificate', 'refeds', 'xsd'];
        $groupLabels = [
            'structural'  => 'Structural (S01–S10)',
            'certificate' => 'Certificate (C01–C05)',
            'refeds'      => 'REFEDS / eduGAIN (R01–R15)',
            'xsd'         => 'XSD Schema (X01)',
        ];
    @endphp

    @foreach($groupOrder as $group)
        @if($grouped->has($group))
        <div class="card mb-4">
            <div class="card-header py-2 fw-semibold">{{ $groupLabels[$group] ?? ucfirst($group) }}</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:6rem">ID</th>
                            <th>Name</th>
                            <th style="width:10rem">Federation Baseline</th>
                            <th style="width:8rem" class="text-center">Entity Override</th>
                            <th style="width:10rem">Override Severity</th>
                            @can('entity.edit')
                            <th style="width:8rem" class="text-center pe-3">Actions</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grouped[$group]->sortBy('id') as $rule)
                        @php
                            $cfg    = $configs->get($rule->id);
                            $fedCfg = $federationConfigs->get($rule->id);
                        @endphp
                        <tr class="{{ $rule->active ? '' : 'table-secondary text-muted' }}">
                            <td class="ps-3"><code class="fw-bold">{{ $rule->id }}</code></td>
                            <td>
                                <div>{{ $rule->name }}</div>
                                @if(! $rule->active)
                                    <div class="text-muted small">Globally disabled</div>
                                @endif
                            </td>
                            <td>
                                @if($fedCfg)
                                    @if($fedCfg->enabled)
                                        <span class="badge bg-success">ON</span>
                                    @else
                                        <span class="badge bg-danger">OFF</span>
                                    @endif
                                    @if($fedCfg->severity)
                                        <span class="badge bg-{{ $fedCfg->severity === 'error' ? 'danger' : 'warning text-dark' }} ms-1">
                                            {{ ucfirst($fedCfg->severity) }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-muted small">Default</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($cfg)
                                    @if($cfg->enabled)
                                        <span class="badge bg-success">ON</span>
                                    @else
                                        <span class="badge bg-danger">OFF</span>
                                    @endif
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                @if($cfg && $cfg->severity)
                                    <span class="badge bg-{{ $cfg->severity === 'error' ? 'danger' : 'warning text-dark' }}">
                                        {{ ucfirst($cfg->severity) }}
                                    </span>
                                @else
                                    <span class="text-muted small">Inherited</span>
                                @endif
                            </td>
                            @can('entity.edit')
                            <td class="text-center pe-3">
                                <div>
                                    <button class="btn btn-outline-primary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#ruleModal"
                                            data-rule-id="{{ $rule->id }}"
                                            data-rule-name="{{ $rule->name }}"
                                            data-cfg-enabled="{{ $cfg ? ($cfg->enabled ? '1' : '0') : '1' }}"
                                            data-cfg-severity="{{ $cfg?->severity ?? '' }}"
                                            data-url="{{ route('entities.rules.update', [$entity, $rule]) }}"
                                            title="Override">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @if($cfg)
                                    <form method="POST"
                                          action="{{ route('entities.rules.destroy', [$entity, $rule]) }}"
                                          style="display:contents">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-outline-secondary btn-sm" title="Reset to inherited">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>
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

</div>

{{-- Edit modal --}}
<div class="modal fade" id="ruleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="ruleForm">
                @csrf @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>
                        Override: <span id="modalRuleName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Enabled</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="enabled" value="1" id="modalEnabled">
                            <label class="form-check-label" for="modalEnabled">Enable this rule for this entity</label>
                        </div>
                        <input type="hidden" name="enabled" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Severity Override</label>
                        <select class="form-select form-select-sm" name="severity">
                            <option value="">Inherited (use federation/rule setting)</option>
                            <option value="error">Error</option>
                            <option value="warning">Warning</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Override</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('ruleModal').addEventListener('show.bs.modal', function(event) {
    const btn     = event.relatedTarget;
    const form    = document.getElementById('ruleForm');
    form.action   = btn.dataset.url;
    document.getElementById('modalRuleName').textContent = btn.dataset.ruleName;
    document.getElementById('modalEnabled').checked = btn.dataset.cfgEnabled === '1';
    form.querySelector('select[name=severity]').value = btn.dataset.cfgSeverity || '';
});
</script>
@endsection
