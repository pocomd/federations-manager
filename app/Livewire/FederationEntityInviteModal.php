<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Entity;
use App\Models\Federation;
use App\Models\FederationEntityInvitation;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class FederationEntityInviteModal extends Component
{
    public string $federationId;
    public string $entityType  = 'idp';
    public string $search      = '';
    public array  $selectedIds = [];
    public string $note        = '';
    public bool   $showModal   = false;

    public function mount(string $federationId): void
    {
        $this->federationId = $federationId;
    }

    /**
     * Open the invite modal for a specific entity type (idp|sp). Resets all state.
     *
     * @listens  open-invite-for
     */
    #[On('open-invite-for')]
    public function openFor(string $type): void
    {
        $this->entityType  = $type;
        $this->search      = '';
        $this->selectedIds = [];
        $this->note        = '';
        $this->showModal   = true;
        $this->resetValidation();
    }

    public function close(): void
    {
        $this->showModal = false;
    }

    public function toggle(string $id): void
    {
        if (in_array($id, $this->selectedIds, true)) {
            $this->selectedIds = array_values(array_filter($this->selectedIds, fn ($i) => $i !== $id));
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function deselect(string $id): void
    {
        $this->selectedIds = array_values(array_filter($this->selectedIds, fn ($i) => $i !== $id));
    }

    /**
     * Entities eligible to invite: not suspended, not already an active/pending member,
     * and no pending invitation from this federation. Results capped at 30.
     */
    #[Computed]
    public function candidates(): Collection
    {
        return Entity::query()
            ->where('type', $this->entityType)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'suspended')
            ->whereDoesntHave('federations', fn ($q) =>
                $q->whereIn('entity_federation.status', ['active', 'pending']))
            ->whereDoesntHave('federationInvitations', fn ($q) =>
                $q->where('federation_id', $this->federationId)->where('status', 'pending'))
            ->when($this->search, function ($q) {
                $like = '%' . $this->search . '%';
                $q->where(function ($q2) use ($like) {
                    $q2->where('entity_id', 'like', $like)
                       ->orWhereHas('uiInfo', fn ($u) => $u
                           ->where('field', 'display_name')
                           ->where('value', 'like', $like));
                });
            })
            ->with('uiInfo')
            ->orderBy('entity_id')
            ->limit(30)
            ->get();
    }

    #[Computed]
    public function selectedEntities(): Collection
    {
        if (empty($this->selectedIds)) {
            return new Collection();
        }

        return Entity::whereIn('id', $this->selectedIds)->with('uiInfo')->get();
    }

    /**
     * Create FederationEntityInvitation records and notify each entity's technical contacts.
     * Silently skips entities already a member (active/pending) or already invited.
     *
     * @authorizes  FederationPolicy::update
     * @dispatches  NotificationService::federation_entity_invitation (per entity sent)
     * @emits       invitations-sent (only if at least one invitation was created)
     */
    public function send(): void
    {
        $federation = Federation::findOrFail($this->federationId);
        Gate::authorize('update', $federation);

        $this->validate([
            'selectedIds'   => ['required', 'array', 'min:1'],
            'selectedIds.*' => ['uuid', 'exists:entities,id'],
            'note'          => ['nullable', 'string', 'max:500'],
        ]);

        $entities = Entity::whereIn('id', $this->selectedIds)->get()->keyBy('id');
        $sent     = 0;
        $skipped  = 0;

        foreach ($this->selectedIds as $entityId) {
            $entity = $entities[$entityId] ?? null;
            if (! $entity) {
                continue;
            }

            if ($federation->entities()->where('entities.id', $entity->id)
                ->whereIn('entity_federation.status', ['active', 'pending'])->exists()) {
                $skipped++;
                continue;
            }

            if (FederationEntityInvitation::where('federation_id', $federation->id)
                ->where('entity_id', $entity->id)
                ->where('status', 'pending')
                ->exists()) {
                $skipped++;
                continue;
            }

            FederationEntityInvitation::create([
                'id'            => (string) Str::uuid(),
                'federation_id' => $federation->id,
                'entity_id'     => $entity->id,
                'invited_by'    => Auth::id(),
                'status'        => 'pending',
                'note'          => $this->note ?: null,
            ]);

            app(NotificationService::class)->dispatch(
                'federation_entity_invitation',
                [
                    'entity_name'     => $entity->entity_id,
                    'federation_name' => $federation->name,
                    'action_url'      => route('entities.show', $entity),
                ],
                $entity,
                $federation
            );

            $sent++;
        }

        $this->showModal   = false;
        $this->selectedIds = [];
        $this->search      = '';
        $this->note        = '';

        $message = $sent . ' invitation' . ($sent !== 1 ? 's' : '') . ' sent';
        if ($skipped > 0) {
            $message .= ', ' . $skipped . ' skipped (already a member or invited)';
        }

        $this->dispatch('notify', type: 'success', message: $message);

        if ($sent > 0) {
            $this->dispatch('invitations-sent');
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.federation-entity-invite-modal');
    }
}
