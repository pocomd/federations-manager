<div>
    <div class="card">
        <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
            <span class="fw-semibold small text-uppercase text-secondary" style="letter-spacing:.04em;">
                <i class="bi bi-person-lines-fill me-1"></i> Federation Contacts
                <span class="badge bg-secondary ms-1 fw-normal">{{ count($contacts) }}</span>
            </span>
            @can('federation.edit')
            <button type="button" class="btn btn-sm btn-outline-primary"
                    wire:click="$toggle('adding')">
                @if($adding)
                    <i class="bi bi-x me-1"></i>Cancel
                @else
                    <i class="bi bi-plus me-1"></i>Add Contact
                @endif
            </button>
            @endcan
        </div>

        {{-- Add contact form --}}
        @if($adding)
        <div class="card-body border-bottom bg-light py-3">
            <div class="row g-2">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Type <span class="text-danger">*</span></label>
                    <select wire:model="newContact.type" class="form-select form-select-sm @error('newContact.type') is-invalid @enderror">
                        <option value="technical">Technical</option>
                        <option value="administrative">Administrative</option>
                        <option value="security">Security</option>
                        <option value="support">Support</option>
                    </select>
                    @error('newContact.type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Given name</label>
                    <input type="text" wire:model="newContact.given_name"
                           class="form-control form-control-sm @error('newContact.given_name') is-invalid @enderror"
                           placeholder="Given name">
                    @error('newContact.given_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Surname</label>
                    <input type="text" wire:model="newContact.sur_name"
                           class="form-control form-control-sm @error('newContact.sur_name') is-invalid @enderror"
                           placeholder="Surname">
                    @error('newContact.sur_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" wire:model="newContact.email"
                           class="form-control form-control-sm @error('newContact.email') is-invalid @enderror"
                           placeholder="email@example.org">
                    @error('newContact.email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Phone</label>
                    <input type="text" wire:model="newContact.phone"
                           class="form-control form-control-sm @error('newContact.phone') is-invalid @enderror"
                           placeholder="+1 555 000 0000">
                    @error('newContact.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-primary btn-sm w-100" wire:click="addContact">
                        Save
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- Contacts table --}}
        @if(count($contacts) === 0 && !$adding)
        <div class="card-body text-muted small py-3">
            <i class="bi bi-info-circle me-1"></i>No contacts defined yet.
        </div>
        @elseif(count($contacts) > 0)
        <div class="card-body p-0">
            <table class="table table-sm table-hover align-middle mb-0" style="font-size:.85rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;width:110px;">Type</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Name</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Email</th>
                        <th class="fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Phone</th>
                        @can('federation.edit')
                        <th class="pe-3 text-end fw-semibold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach($contacts as $contact)
                        @if($editingId === $contact['id'])
                        {{-- Inline edit row --}}
                        <tr class="table-warning">
                            <td class="ps-3">
                                <select wire:model="editContact.type"
                                        class="form-select form-select-sm @error('editContact.type') is-invalid @enderror">
                                    <option value="technical">Technical</option>
                                    <option value="administrative">Administrative</option>
                                    <option value="security">Security</option>
                                    <option value="support">Support</option>
                                </select>
                                @error('editContact.type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <input type="text" wire:model="editContact.given_name"
                                           class="form-control form-control-sm @error('editContact.given_name') is-invalid @enderror"
                                           placeholder="Given name">
                                    <input type="text" wire:model="editContact.sur_name"
                                           class="form-control form-control-sm @error('editContact.sur_name') is-invalid @enderror"
                                           placeholder="Surname">
                                </div>
                                @error('editContact.given_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @error('editContact.sur_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="email" wire:model="editContact.email"
                                       class="form-control form-control-sm @error('editContact.email') is-invalid @enderror"
                                       placeholder="email@example.org">
                                @error('editContact.email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="text" wire:model="editContact.phone"
                                       class="form-control form-control-sm @error('editContact.phone') is-invalid @enderror"
                                       placeholder="+1 555 000 0000">
                                @error('editContact.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </td>
                            <td class="pe-3 text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <button type="button" class="btn btn-sm btn-primary"
                                            wire:click="saveEdit">Save</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            wire:click="cancelEdit">Cancel</button>
                                </div>
                            </td>
                        </tr>
                        @else
                        {{-- Display row --}}
                        <tr>
                            <td class="ps-3">
                                @php
                                    $badgeClass = match($contact['type']) {
                                        'technical'      => 'bg-info text-dark',
                                        'administrative' => 'bg-secondary',
                                        'security'       => 'bg-danger',
                                        'support'        => 'bg-success',
                                        default          => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ ucfirst($contact['type']) }}</span>
                            </td>
                            <td>
                                {{ trim($contact['given_name'] . ' ' . $contact['sur_name']) ?: '—' }}
                            </td>
                            <td>
                                <a href="mailto:{{ $contact['email'] }}" class="text-decoration-none">{{ $contact['email'] }}</a>
                            </td>
                            <td class="text-muted">{{ $contact['phone'] ?: '—' }}</td>
                            @can('federation.edit')
                            <td class="pe-3 text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            wire:click="startEdit('{{ $contact['id'] }}')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            wire:click="deleteContact('{{ $contact['id'] }}')"
                                            wire:confirm="Are you sure you want to delete this contact?">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                            @endcan
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
