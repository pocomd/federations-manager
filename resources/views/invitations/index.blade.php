@extends('layouts.app')

@section('title', 'Invitations')

@section('content')

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">Invitations</h1>
            <p class="text-muted small mb-0">Manage user invitations</p>
        </div>
        <button class="btn btn-primary btn-sm"
                data-bs-toggle="modal" data-bs-target="#newInvitationModal">
            <i class="bi bi-plus-lg me-1"></i> New Invitation
        </button>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3" id="invitationTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active small" id="pending-tab" data-bs-toggle="tab"
                    data-bs-target="#pending" type="button" role="tab">
                Pending
                @if($pending->isNotEmpty())
                    <span class="badge bg-secondary ms-1">{{ $pending->count() }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link small" id="accepted-tab" data-bs-toggle="tab"
                    data-bs-target="#accepted" type="button" role="tab">
                Accepted
                @if($accepted->isNotEmpty())
                    <span class="badge bg-success ms-1">{{ $accepted->count() }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link small" id="expired-tab" data-bs-toggle="tab"
                    data-bs-target="#expired" type="button" role="tab">
                Expired
                @if($expired->isNotEmpty())
                    <span class="badge bg-warning text-dark ms-1">{{ $expired->count() }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link small" id="revoked-tab" data-bs-toggle="tab"
                    data-bs-target="#revoked" type="button" role="tab">
                Revoked
                @if($revoked->isNotEmpty())
                    <span class="badge bg-danger ms-1">{{ $revoked->count() }}</span>
                @endif
            </button>
        </li>
    </ul>

    <div class="tab-content" id="invitationTabsContent">

        {{-- Pending --}}
        <div class="tab-pane fade show active" id="pending" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small">Email</th>
                                <th class="small">Federation</th>
                                <th class="small">Entity</th>
                                <th class="small">Invited By</th>
                                <th class="small">Sent</th>
                                <th class="small">Expires</th>
                                <th class="small">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pending as $inv)
                            <tr>
                                <td class="small">{{ $inv->email }}</td>
                                <td class="small">{{ $inv->federation?->name ?? '—' }}</td>
                                <td class="small">{{ $inv->entity?->display_name ?? '—' }}</td>
                                <td class="small">{{ $inv->invitedBy?->name ?? '—' }}</td>
                                <td class="small text-muted">{{ $inv->created_at->format('Y-m-d') }}</td>
                                <td class="small text-muted">{{ $inv->expires_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        {{-- Copy URL --}}
                                        <div x-data="{
                                            copied: false,
                                            doCopy(url) {
                                                const el = document.createElement('textarea');
                                                el.value = url;
                                                el.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
                                                document.body.appendChild(el);
                                                el.select();
                                                document.execCommand('copy');
                                                document.body.removeChild(el);
                                                this.copied = true;
                                                setTimeout(() => this.copied = false, 2000);
                                            }
                                        }">
                                            <button type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    title="Copy invitation URL"
                                                    @click="doCopy('{{ route('register.invitation.show', $inv->token) }}')">
                                                <i class="bi" :class="copied ? 'bi-check' : 'bi-clipboard'"></i>
                                            </button>
                                        </div>
                                        {{-- Resend --}}
                                        <form method="POST"
                                              action="{{ route('invitations.resend', $inv) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary btn-sm" title="Resend">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        </form>
                                        {{-- Revoke --}}
                                        <form method="POST"
                                              action="{{ route('invitations.revoke', $inv) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Revoke"
                                                    onclick="return confirm('Revoke this invitation?')">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                        {{-- Reissue --}}
                                        <form method="POST"
                                              action="{{ route('invitations.reissue', $inv) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-warning btn-sm" title="Reissue">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted small py-4">No pending invitations.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Accepted --}}
        <div class="tab-pane fade" id="accepted" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small">Email</th>
                                <th class="small">Federation</th>
                                <th class="small">Entity</th>
                                <th class="small">Invited By</th>
                                <th class="small">Sent</th>
                                <th class="small">Accepted</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accepted as $inv)
                            <tr>
                                <td class="small">{{ $inv->email }}</td>
                                <td class="small">{{ $inv->federation?->name ?? '—' }}</td>
                                <td class="small">{{ $inv->entity?->display_name ?? '—' }}</td>
                                <td class="small">{{ $inv->invitedBy?->name ?? '—' }}</td>
                                <td class="small text-muted">{{ $inv->created_at->format('Y-m-d') }}</td>
                                <td class="small text-muted">{{ $inv->accepted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted small py-4">No accepted invitations.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Expired --}}
        <div class="tab-pane fade" id="expired" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small">Email</th>
                                <th class="small">Federation</th>
                                <th class="small">Entity</th>
                                <th class="small">Invited By</th>
                                <th class="small">Sent</th>
                                <th class="small">Expired</th>
                                <th class="small">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expired as $inv)
                            <tr>
                                <td class="small">{{ $inv->email }}</td>
                                <td class="small">{{ $inv->federation?->name ?? '—' }}</td>
                                <td class="small">{{ $inv->entity?->display_name ?? '—' }}</td>
                                <td class="small">{{ $inv->invitedBy?->name ?? '—' }}</td>
                                <td class="small text-muted">{{ $inv->created_at->format('Y-m-d') }}</td>
                                <td class="small text-muted">{{ $inv->expires_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('invitations.reissue', $inv) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-warning btn-sm">
                                            <i class="bi bi-arrow-repeat me-1"></i>Reissue
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted small py-4">No expired invitations.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Revoked --}}
        <div class="tab-pane fade" id="revoked" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small">Email</th>
                                <th class="small">Federation</th>
                                <th class="small">Entity</th>
                                <th class="small">Invited By</th>
                                <th class="small">Revoked By</th>
                                <th class="small">Revoked</th>
                                <th class="small">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($revoked as $inv)
                            <tr x-data="{ showReissue: false }">
                                <td class="small">{{ $inv->email }}</td>
                                <td class="small">{{ $inv->federation?->name ?? '—' }}</td>
                                <td class="small">{{ $inv->entity?->display_name ?? '—' }}</td>
                                <td class="small">{{ $inv->invitedBy?->name ?? '—' }}</td>
                                <td class="small">{{ $inv->revokedBy?->name ?? '—' }}</td>
                                <td class="small text-muted">{{ $inv->revoked_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>
                                    <button type="button" class="btn btn-outline-warning btn-sm"
                                            @click="showReissue = !showReissue">
                                        <i class="bi bi-arrow-repeat me-1"></i>Reissue
                                    </button>
                                    <div x-show="showReissue" class="mt-2" x-cloak>
                                        <form method="POST" action="{{ route('invitations.reissue', $inv) }}">
                                            @csrf
                                            <div class="mb-2">
                                                <textarea name="reissue_comment"
                                                          class="form-control form-control-sm"
                                                          rows="2"
                                                          placeholder="Reason for reissuing (required)"
                                                          required></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-warning btn-sm">Confirm Reissue</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    @click="showReissue = false">Cancel</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted small py-4">No revoked invitations.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- New Invitation Modal --}}
<div class="modal fade" id="newInvitationModal" tabindex="-1" aria-labelledby="newInvitationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('invitations.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title h6 fw-semibold" id="newInvitationModalLabel">New Invitation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($errors->any())
                    <div class="alert alert-danger small p-2">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control form-control-sm"
                               value="{{ old('email') }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Federation</label>
                        <select name="federation_id" id="federationSelect"
                                class="form-select form-select-sm" required>
                            <option value="">— Select federation —</option>
                            @foreach($federations as $fed)
                            <option value="{{ $fed->id }}"
                                    {{ old('federation_id') === $fed->id ? 'selected' : '' }}>
                                {{ $fed->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Entity <span class="text-muted fw-normal">(optional)</span></label>
                        <select name="entity_id" class="form-select form-select-sm">
                            <option value="">— None —</option>
                            @foreach(\App\Models\Entity::orderBy('entity_id')->get() as $ent)
                            <option value="{{ $ent->id }}"
                                    {{ old('entity_id') === $ent->id ? 'selected' : '' }}>
                                {{ $ent->display_name ?? $ent->entity_id }}
                            </option>
                            @endforeach
                        </select>
                        <div class="form-text small text-muted">If selected, the user will be added as co-manager of this entity after registration.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Send Invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
