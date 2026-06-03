{{-- Managers tab partial — list current managers and assign new ones --}}

<div class="card mb-4">
    <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
            <i class="bi bi-person-badge me-1"></i> Federation Managers
        </span>
        <span class="badge bg-secondary">{{ $managerCount }}</span>
    </div>
    <div class="card-body p-0">
        @if($managers->isEmpty())
            <p class="text-muted small px-3 py-3 mb-0">No managers assigned to this federation.</p>
        @else
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Name</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Email</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Assigned At</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Assigned By</th>
                        @can('federation.create')
                        <th class="pe-3 text-end fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Action</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach($managers as $manager)
                    <tr>
                        <td class="ps-3">
                            {{ $manager->name }}
                            @can('federation.create')
                                @if($manager->hasRole('Admin'))
                                    <span class="badge bg-danger ms-1" title="This user has the Admin role">Admin</span>
                                @endif
                            @endcan
                        </td>
                        <td>{{ $manager->email }}</td>
                        <td class="text-muted small">
                            {{ $manager->pivot->assigned_at ? \Carbon\Carbon::parse($manager->pivot->assigned_at)->format('Y-m-d H:i') : '—' }}
                        </td>
                        <td class="text-muted small">
                            {{ $assignedByNames[$manager->pivot->assigned_by] ?? '—' }}
                        </td>
                        @can('federation.create')
                        <td class="pe-3 text-end">
                            <form method="POST"
                                  action="{{ route('federations.managers.remove', [$federation, $manager]) }}"
                                  x-data
                                  @submit.prevent="SwalDefault.fire({
                                      title: 'Remove {{ addslashes($manager->name) }} as manager?',
                                      text: 'They will be logged out immediately.',
                                      icon: 'warning',
                                      showCancelButton: true,
                                      confirmButtonText: 'Yes, remove',
                                      confirmButtonColor: '#dc3545',
                                  }).then(r => { if (r.isConfirmed) $el.submit() })">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-person-dash me-1"></i>Remove
                                </button>
                            </form>
                        </td>
                        @endcan
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

@can('federation.create')
@if($availableManagers->isNotEmpty())
<div class="card">
    <div class="card-header bg-transparent border-bottom py-2 px-3 fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
        <i class="bi bi-person-plus me-1"></i> Add Manager
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('federations.managers.add', $federation) }}" class="d-flex gap-2 align-items-end">
            @csrf
            <div class="flex-grow-1">
                <label for="manager_user_id" class="form-label small fw-semibold mb-1">Select Federation Manager</label>
                <select id="manager_user_id" name="user_id" class="form-select form-select-sm @error('user_id') is-invalid @enderror" required>
                    <option value="">— choose user —</option>
                    @foreach($availableManagers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                @error('user_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="bi bi-person-check me-1"></i>Assign
            </button>
        </form>
    </div>
</div>
@else
<div class="alert alert-info small mb-0">
    All users with the Federation Manager role are already assigned to this federation.
</div>
@endif
@endcan
