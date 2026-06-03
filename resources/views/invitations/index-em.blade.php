@extends('layouts.app')

@section('title', 'My Invitations')

@section('content')

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">My Invitations</h1>
            <p class="text-muted small mb-0">Invitations you have sent to entity contacts</p>
        </div>
        @if(count($emEntitiesData) > 0)
        <button class="btn btn-primary btn-sm"
                data-bs-toggle="modal" data-bs-target="#newInvitationModal">
            <i class="bi bi-plus-lg me-1"></i> New Invitation
        </button>
        @endif
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
                                <th class="small">Entity</th>
                                <th class="small">Sent</th>
                                <th class="small">Expires</th>
                                <th class="small">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pending as $inv)
                            <tr>
                                <td class="small">{{ $inv->email }}</td>
                                <td class="small">{{ $inv->entity?->getDisplayName() ?? $inv->entity?->entity_id ?? '—' }}</td>
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
                                        <form method="POST" action="{{ route('invitations.resend', $inv) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary btn-sm" title="Resend">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        </form>
                                        {{-- Revoke --}}
                                        <form method="POST" action="{{ route('invitations.revoke', $inv) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Revoke"
                                                    onclick="return confirm('Revoke this invitation?')">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted small py-4">No pending invitations.</td>
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
                                <th class="small">Entity</th>
                                <th class="small">Sent</th>
                                <th class="small">Accepted</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accepted as $inv)
                            <tr>
                                <td class="small">{{ $inv->email }}</td>
                                <td class="small">{{ $inv->entity?->getDisplayName() ?? $inv->entity?->entity_id ?? '—' }}</td>
                                <td class="small text-muted">{{ $inv->created_at->format('Y-m-d') }}</td>
                                <td class="small text-muted">{{ $inv->accepted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted small py-4">No accepted invitations.</td>
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
                                <th class="small">Entity</th>
                                <th class="small">Sent</th>
                                <th class="small">Expired</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expired as $inv)
                            <tr>
                                <td class="small">{{ $inv->email }}</td>
                                <td class="small">{{ $inv->entity?->getDisplayName() ?? $inv->entity?->entity_id ?? '—' }}</td>
                                <td class="small text-muted">{{ $inv->created_at->format('Y-m-d') }}</td>
                                <td class="small text-muted">{{ $inv->expires_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted small py-4">No expired invitations.</td>
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
                                <th class="small">Entity</th>
                                <th class="small">Sent</th>
                                <th class="small">Revoked</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($revoked as $inv)
                            <tr>
                                <td class="small">{{ $inv->email }}</td>
                                <td class="small">{{ $inv->entity?->getDisplayName() ?? $inv->entity?->entity_id ?? '—' }}</td>
                                <td class="small text-muted">{{ $inv->created_at->format('Y-m-d') }}</td>
                                <td class="small text-muted">{{ $inv->revoked_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted small py-4">No revoked invitations.</td>
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
        <div class="modal-content"
             x-data="{
                 entities: {{ Js::from($emEntitiesData) }},
                 selectedEntityId: '',
                 contacts: [],
                 selectedEmail: '',
                 selectEntity(id) {
                     this.selectedEntityId = id;
                     this.selectedEmail = '';
                     const e = this.entities.find(e => e.id === id);
                     this.contacts = e ? e.contacts : [];
                 }
             }">
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
                        <label class="form-label small fw-semibold">Entity</label>
                        <select name="entity_id" class="form-select form-select-sm" required
                                @change="selectEntity($event.target.value)">
                            <option value="">— Select entity —</option>
                            @foreach($emEntitiesData as $e)
                            <option value="{{ $e['id'] }}">{{ $e['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Contact to invite</label>
                        <select name="email" class="form-select form-select-sm" required
                                x-model="selectedEmail"
                                :disabled="contacts.length === 0">
                            <option value="">— Select contact —</option>
                            <template x-for="c in contacts" :key="c.email">
                                <option :value="c.email" x-text="c.label"></option>
                            </template>
                        </select>
                        <div class="form-text small text-muted" x-show="selectedEntityId === ''">
                            Select an entity first to see its contacts.
                        </div>
                        <div class="form-text small text-muted" x-show="selectedEntityId !== '' && contacts.length === 0">
                            This entity has no contacts defined.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"
                            :disabled="selectedEmail === ''">Send Invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
