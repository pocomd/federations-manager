<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\MailTemplate;
use App\Services\Mail\MailTemplateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class FederationEntitySuspendModal extends Component
{
    public bool    $show               = false;
    public ?string $entityId           = null;
    public string  $federationId       = '';
    public bool    $notifyOwner        = true;
    public array   $notifyContactTypes = ['technical'];

    /** @listens  open-federation-suspend */
    #[On('open-federation-suspend')]
    public function open(string $entityId, string $federationId): void
    {
        $this->entityId           = $entityId;
        $this->federationId       = $federationId;
        $this->notifyOwner        = true;
        $this->notifyContactTypes = ['technical'];
        $this->show               = true;
    }

    public function close(): void
    {
        $this->show     = false;
        $this->entityId = null;
    }

    #[Computed]
    public function entity(): ?Entity
    {
        return $this->entityId ? Entity::find($this->entityId) : null;
    }

    #[Computed]
    public function federation(): ?Federation
    {
        return $this->federationId ? Federation::find($this->federationId) : null;
    }

    /**
     * Suspend the entity globally (sets entity.status = suspended, not just in this federation).
     * Optionally sends a notification via MailTemplateService if an active entity_suspended
     * template exists and notifyOwner is true.
     *
     * @authorizes  entity.edit (Gate::check — soft error)
     * @dispatches  MailTemplateService::entity_suspended (conditional — only if notifyOwner and template active)
     * @emits       entity-suspended-in-federation
     */
    public function confirm(): void
    {
        if (! Gate::check('entity.edit')) {
            $this->dispatch('notify', type: 'error', message: 'Unauthorized.');
            return;
        }

        if ($this->notifyOwner && empty($this->notifyContactTypes)) {
            $this->dispatch('notify', type: 'error', message: __('app.suspend_select_contact_type'));
            return;
        }

        $entity = Entity::find($this->entityId);
        if (! $entity) {
            $this->dispatch('notify', type: 'error', message: 'Entity not found.');
            return;
        }

        $entity->update(['status' => 'suspended']);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $entity->id,
            'action'     => 'entity_suspended',
            'old_values' => ['status' => 'active'],
            'new_values' => ['status' => 'suspended', 'membership_action' => 'none'],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        if ($this->notifyOwner) {
            $this->sendNotification($entity);
        }

        Log::info('Entity suspended via FederationEntitySuspendModal', [
            'entity_id'     => $entity->entity_id,
            'federation_id' => $this->federationId,
            'suspended_by'  => Auth::id(),
        ]);

        $this->show     = false;
        $this->entityId = null;

        $this->dispatch('notify', type: 'success', message: __('app.entity_suspended_success'));
        $this->dispatch('entity-suspended-in-federation');
    }

    private function sendNotification(Entity $entity): void
    {
        $template = MailTemplate::where('group', 'entity_suspended')
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return;
        }

        $service  = app(MailTemplateService::class);
        $rendered = $service->render($template, [
            'entity'     => $entity,
            'federation' => $this->federation,
            'contact'    => null,
        ]);

        $service->sendToEntity(
            $entity,
            $rendered['subject'],
            $rendered['body'],
            $this->notifyContactTypes,
            $this->federation,
            Auth::user()
        );
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.federation-entity-suspend-modal');
    }
}
