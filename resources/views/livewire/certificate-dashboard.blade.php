{{--
    resources/views/livewire/certificate-dashboard.blade.php
    Certificate expiry dashboard — stat cards + sortable table.
    Severity: expired(<0d) / critical(≤14d) / warning(≤30d) / advisory(≤60d) / info(≤90d) / healthy(>90d)
--}}
<div>

    {{-- ── Stats metric row ────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">

        {{-- Expired --}}
        <div class="col-6 col-md-2">
            <div class="card h-100 text-center border-danger {{ $filterSeverity === 'expired' ? 'border-3' : '' }}"
                 wire:click="filterBySeverity('expired')"
                 style="cursor:pointer">
                <div class="card-body py-3">
                    <i class="bi bi-x-circle-fill fs-4 mb-1 text-danger"></i>
                    <div class="fs-3 fw-bold text-danger">{{ $this->summary['expired'] ?? 0 }}</div>
                    <div class="small text-muted">{{ __('app.cert_expired') }}</div>
                </div>
            </div>
        </div>

        {{-- Critical --}}
        <div class="col-6 col-md-2">
            <div class="card h-100 text-center border-danger {{ $filterSeverity === 'critical' ? 'border-3' : '' }}"
                 wire:click="filterBySeverity('critical')"
                 style="cursor:pointer">
                <div class="card-body py-3">
                    <i class="bi bi-exclamation-triangle-fill fs-4 mb-1 text-danger"></i>
                    <div class="fs-3 fw-bold text-danger">{{ $this->summary['critical'] ?? 0 }}</div>
                    <div class="small text-muted">{{ __('app.cert_critical') }}</div>
                </div>
            </div>
        </div>

        {{-- Warning --}}
        <div class="col-6 col-md-2">
            <div class="card h-100 text-center border-warning {{ $filterSeverity === 'warning' ? 'border-3' : '' }}"
                 wire:click="filterBySeverity('warning')"
                 style="cursor:pointer">
                <div class="card-body py-3">
                    <i class="bi bi-exclamation-circle fs-4 mb-1 text-warning"></i>
                    <div class="fs-3 fw-bold text-warning">{{ $this->summary['warning'] ?? 0 }}</div>
                    <div class="small text-muted">{{ __('app.cert_warning') }}</div>
                </div>
            </div>
        </div>

        {{-- Advisory --}}
        <div class="col-6 col-md-2">
            <div class="card h-100 text-center border-warning {{ $filterSeverity === 'advisory' ? 'border-3' : '' }}"
                 wire:click="filterBySeverity('advisory')"
                 style="cursor:pointer">
                <div class="card-body py-3">
                    <i class="bi bi-info-circle fs-4 mb-1 text-warning"></i>
                    <div class="fs-3 fw-bold text-warning">{{ $this->summary['advisory'] ?? 0 }}</div>
                    <div class="small text-muted">{{ __('app.cert_advisory') }}</div>
                </div>
            </div>
        </div>

        {{-- Info --}}
        <div class="col-6 col-md-2">
            <div class="card h-100 text-center border-info {{ $filterSeverity === 'info' ? 'border-3' : '' }}"
                 wire:click="filterBySeverity('info')"
                 style="cursor:pointer">
                <div class="card-body py-3">
                    <i class="bi bi-bell fs-4 mb-1 text-info"></i>
                    <div class="fs-3 fw-bold text-info">{{ $this->summary['info'] ?? 0 }}</div>
                    <div class="small text-muted">{{ __('app.cert_info') }}</div>
                </div>
            </div>
        </div>

        {{-- Healthy --}}
        <div class="col-6 col-md-2">
            <div class="card h-100 text-center border-success {{ $filterSeverity === 'healthy' ? 'border-3' : '' }}"
                 wire:click="filterBySeverity('healthy')"
                 style="cursor:pointer">
                <div class="card-body py-3">
                    <i class="bi bi-check-circle-fill fs-4 mb-1 text-success"></i>
                    <div class="fs-3 fw-bold text-success">{{ $this->summary['healthy'] ?? 0 }}</div>
                    <div class="small text-muted">{{ __('app.cert_healthy') }}</div>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Filters + actions bar ────────────────────────────────────────── --}}
    <div class="bg-light border rounded p-3 mb-3">
        <div class="row g-2 align-items-center">

                {{-- Severity filter --}}
                <div class="col-auto">
                    <select wire:model.live="filterSeverity" class="form-select form-select-sm">
                        <option value="">All severities</option>
                        <option value="expired">Expired</option>
                        <option value="critical">Critical</option>
                        <option value="warning">Warning</option>
                        <option value="advisory">Advisory</option>
                        <option value="info">Info</option>
                        <option value="healthy">Healthy</option>
                    </select>
                </div>

                {{-- Federation filter --}}
                <div class="col-auto">
                    <select wire:model.live="filterFederation" class="form-select form-select-sm">
                        <option value="">All federations</option>
                        @foreach($availableFederations as $fed)
                            <option value="{{ $fed['id'] }}">{{ $fed['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Type filter --}}
                <div class="col-auto">
                    <select wire:model.live="filterType" class="form-select form-select-sm">
                        <option value="">All types</option>
                        <option value="idp">IdP only</option>
                        <option value="sp">SP only</option>
                    </select>
                </div>

                {{-- Clear filters --}}
                @if($filterSeverity || $filterFederation || $filterType)
                <div class="col-auto">
                    <button class="btn btn-outline-secondary btn-sm"
                            wire:click="$set('filterSeverity', ''); $set('filterFederation', ''); $set('filterType', '')">
                        <i class="bi bi-x-circle me-1"></i> Clear
                    </button>
                </div>
                @endif

                {{-- Spacer --}}
                <div class="col"></div>

                {{-- Last notified timestamp --}}
                @if($lastNotified)
                <div class="col-auto">
                    <small class="text-muted">
                        <i class="bi bi-bell-fill text-success me-1"></i>
                        Sent {{ $lastNotified }}
                    </small>
                </div>
                @endif

                {{-- Send Notifications button (Admin only) --}}
                @can('metadata.generate')
                <div class="col-auto">
                    <button class="btn btn-warning btn-sm"
                            wire:click="sendNotifications"
                            wire:loading.attr="disabled"
                            wire:target="sendNotifications"
                            {{ $notifying ? 'disabled' : '' }}>
                        <span wire:loading.remove wire:target="sendNotifications">
                            <i class="bi bi-bell me-1"></i> Send Notifications
                        </span>
                        <span wire:loading wire:target="sendNotifications">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Sending…
                        </span>
                    </button>
                </div>
                @endcan

            </div>
    </div>

    {{-- ── Certificate table ────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
            <span class="card-header-label"><i class="bi bi-shield-lock me-1"></i> Certificates</span>
            <span wire:loading class="spinner-border spinner-border-sm text-secondary" role="status"></span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;cursor:pointer;" wire:click="sort('entity_id')">
                            Entity
                            @if($sortBy === 'entity_id')
                                <i class="bi bi-sort-{{ $sortDir === 'asc' ? 'up' : 'down' }}-alt ms-1"></i>
                            @endif
                        </th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Type</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Use</th>
                        <th class="d-none d-md-table-cell fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;max-width:200px;">Subject</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;cursor:pointer;" wire:click="sort('not_after')">
                            Expires
                            @if($sortBy === 'not_after')
                                <i class="bi bi-sort-{{ $sortDir === 'asc' ? 'up' : 'down' }}-alt ms-1"></i>
                            @endif
                        </th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;cursor:pointer;" wire:click="sort('not_after')">
                            Days
                            @if($sortBy === 'not_after' && $sortDir === 'asc')
                                <i class="bi bi-sort-up-alt ms-1"></i>
                            @endif
                        </th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Severity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->certificates as $cert)
                        @php
                            $severity  = $this->certSeverityForCert($cert);
                            $bootstrap = $this->severityBootstrap($severity);
                            $days      = $cert->not_after ? (int) now()->diffInDays($cert->not_after, false) : null;
                            $rowClass  = match($severity) {
                                'expired'  => 'table-danger',
                                'critical' => 'table-danger',
                                'warning'  => 'table-warning',
                                'advisory' => 'table-info',
                                'info'     => 'table-primary',
                                default    => '',
                            };
                        @endphp
                        <tr class="{{ $rowClass }}">
                            {{-- Entity --}}
                            <td class="ps-3 small">
                                @if($cert->entity)
                                    <a href="{{ route('entities.show', $cert->entity) }}"
                                       class="text-decoration-none font-monospace"
                                       style="max-width:260px;overflow:hidden;text-overflow:ellipsis;display:block;white-space:nowrap;">
                                        {{ $cert->entity->entity_id }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            {{-- Type --}}
                            <td>
                                @if($cert->entity?->type === 'idp')
                                    <span class="badge bg-info text-dark">IdP</span>
                                @elseif($cert->entity?->type === 'sp')
                                    <span class="badge bg-success">SP</span>
                                @else
                                    <span class="badge bg-secondary">—</span>
                                @endif
                            </td>

                            {{-- Use --}}
                            <td>
                                <span class="badge bg-secondary">{{ $cert->use ?? '—' }}</span>
                            </td>

                            {{-- Subject --}}
                            <td class="d-none d-md-table-cell small text-muted"
                                style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                title="{{ $cert->subject }}">
                                {{ $cert->subject ?? '—' }}
                            </td>

                            {{-- Expiry date --}}
                            <td class="small text-nowrap">
                                @if($cert->not_after)
                                    {{ $cert->not_after->format('d M Y') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            {{-- Days remaining --}}
                            <td class="small fw-semibold text-nowrap">
                                @if($days !== null)
                                    @if($days < 0)
                                        <span class="text-danger">{{ abs($days) }}d ago</span>
                                    @else
                                        <span class="text-{{ $bootstrap }}">{{ $days }}d</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            {{-- Severity badge --}}
                            <td>
                                <span class="badge bg-{{ $bootstrap }}
                                    {{ in_array($bootstrap, ['warning', 'info']) ? 'text-dark' : '' }}">
                                    {{ ucfirst($severity) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No certificates match the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->certificates->hasPages())
            <div class="card-footer">{{ $this->certificates->links() }}</div>
        @endif
    </div>

</div>
