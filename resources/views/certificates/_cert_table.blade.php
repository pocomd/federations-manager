<table class="table table-sm table-hover align-middle mb-0">
    <thead class="table-light">
        <tr>
            <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;min-width:200px">Entity</th>
            <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em">Type</th>
            <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em">Use</th>
            <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;max-width:180px">Subject</th>
            <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em">{{ $expiresLabel }}</th>
            <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em">{{ $daysLabel }}</th>
            <th class="fw-semibold text-secondary text-uppercase text-center pe-3" style="font-size:.7rem;letter-spacing:.05em">Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach($certs as $cert)
        <tr>
            <td class="ps-3">
                <div class="fw-semibold small">{{ $cert['entity_name'] ?? '—' }}</div>
                <div class="text-muted font-monospace" style="font-size:.7rem">{{ $cert['entity_id'] }}</div>
            </td>
            <td>
                <span class="badge bg-{{ ($cert['entity_type'] ?? '') === 'idp' ? 'primary' : 'success' }}">
                    {{ strtoupper($cert['entity_type'] ?? '?') }}
                </span>
            </td>
            <td><span class="badge bg-secondary">{{ $cert['use'] ?? '—' }}</span></td>
            <td class="small text-muted"
                style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                title="{{ $cert['subject'] ?? '' }}">
                {{ $cert['subject'] ?? '—' }}
            </td>
            <td class="small {{ $dateClass ?? '' }}">
                {{ $cert['not_after'] ? \Carbon\Carbon::parse($cert['not_after'])->format('Y-m-d') : '—' }}
            </td>
            <td>
                <span class="badge {{ $daysBadge }}">
                    {{ $daysPrefix }}{{ ($daysAbs ?? false) ? abs($cert['days_remaining']) : $cert['days_remaining'] }}{{ $daysSuffix }}
                </span>
            </td>
            <td class="text-center pe-3">
                <div class="d-flex justify-content-center gap-1">
                    @if(!empty($cert['entity_db_id']))
                    <a href="{{ route('entities.show', $cert['entity_db_id']) }}"
                       class="btn btn-sm btn-outline-primary"
                       title="View entity">
                        <i class="bi bi-eye"></i>
                    </a>
                    @endif
                    @if(!empty($cert['contact_email']))
                    <a href="mailto:{{ $cert['contact_email'] }}"
                       class="btn btn-sm btn-outline-secondary"
                       title="Email admin: {{ $cert['contact_email'] }}">
                        <i class="bi bi-envelope"></i>
                    </a>
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
