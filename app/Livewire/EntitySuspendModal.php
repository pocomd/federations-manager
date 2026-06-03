<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\MailTemplate;
use App\Services\Mail\MailTemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * EntitySuspendModal
 *
 * 4-step wizard for suspending an entity:
 *   Step 1 — Impact: shows active federation memberships and cert warnings
 *   Step 2 — Memberships: choose to disable memberships or move to another federation
 *   Step 3 — Notify: toggle notification to entity contacts
 *   Step 4 — Confirm: summary and execute
 *
 * Never name a method validate() — conflicts with Livewire base class.
 */
class EntitySuspendModal extends Component
{
    public bool    $show              = false;
    public ?string $entityId          = null;
    public int     $step              = 1;
    public string  $membershipAction  = 'disable'; // 'disable' | 'move'
    public ?string $targetFederationId = null;
    public bool  $notifyOwner        = true;
    public array $notifyContactTypes = ['technical'];

    public bool    $showPreview          = false;
    public string  $previewSubject       = '';
    public string  $previewBody          = '';
    public ?string $previewTemplateId    = null;
    public ?string $previewFederationNote = null;


    /** @listens  open-suspend-modal */
    #[On('open-suspend-modal')]
    public function open(string $entityId): void
    {
        $this->entityId           = $entityId;
        $this->step               = 1;
        $this->membershipAction   = 'disable';
        $this->targetFederationId = null;
        $this->notifyOwner        = true;
        $this->notifyContactTypes = ['technical'];
        $this->show               = true;
    }

    public function close(): void
    {
        $this->show     = false;
        $this->entityId = null;
    }

    public function previewTemplate(): void
    {
        $entity   = Entity::find($this->entityId);
        $template = MailTemplate::where('group', 'entity_suspended')->first();

        if (!$entity) {
            $this->dispatch('notify', type: 'error', message: 'Entity not found.');
            return;
        }

        if (!$template) {
            $this->dispatch('notify', type: 'warning', message: __('app.mail_template_not_found'));
            return;
        }

        $federations  = $this->activeFederations;
        $firstFed     = $federations->first();

        $rendered = app(MailTemplateService::class)->render($template, [
            'entity'     => $entity,
            'federation' => $firstFed,
            'contact'    => null,
        ]);

        $this->previewSubject        = $rendered['subject'];
        $this->previewBody           = $rendered['body'];
        $this->previewTemplateId     = $template->id;
        $this->previewFederationNote = $federations->count() > 1
            ? 'Previewing with federation "' . $firstFed?->name . '". This entity belongs to '
              . $federations->count() . ' federations — one notification will be sent per federation.'
            : null;
        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
    }


    #[Computed]
    public function entity(): ?Entity
    {
        if ($this->entityId === null) {
            return null;
        }
        return Entity::find($this->entityId);
    }

    #[Computed]
    public function activeFederations(): Collection
    {
        if ($this->entityId === null) {
            return collect();
        }
        $entity = Entity::find($this->entityId);
        if (!$entity) {
            return collect();
        }
        return $entity->federations()
            ->wherePivot('status', 'active')
            ->get();
    }

    #[Computed]
    public function availableTargetFederations(): Collection
    {
        if ($this->entityId === null) {
            return collect();
        }
        $currentIds = $this->activeFederations->pluck('id');
        return Federation::where('status', 'active')
            ->whereNotIn('id', $currentIds)
            ->orderBy('name')
            ->get();
    }


    public function nextStep(): void
    {
        if ($this->step === 2 && $this->membershipAction === 'move' && !$this->targetFederationId) {
            $this->dispatch('notify', type: 'error', message: __('app.suspend_select_federation'));
            return;
        }
        if ($this->step === 3 && $this->notifyOwner && empty($this->notifyContactTypes)) {
            $this->dispatch('notify', type: 'error', message: __('app.suspend_select_contact_type'));
            return;
        }
        $this->step++;
    }

    public function prevStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }


    /**
     * Suspend the entity and update federation memberships per wizard selection.
     * Active-federation collection is pre-cached before the transaction alters pivot rows.
     * Clears both the federation metadata cache AND the entity metadata validation cache.
     * Notifications are sent outside the transaction (one per federation for correct context).
     *
     * @authorizes  entity.edit (Gate::check — soft error)
     * @dispatches  MailTemplateService::entity_suspended (per active federation, conditional — if notifyOwner and template active)
     * @emits       entity-suspended
     */
    public function executeSuspend(): void
    {
        if (!Gate::check('entity.edit')) {
            $this->dispatch('notify', type: 'error', message: 'Unauthorized.');
            return;
        }

        $entity = Entity::find($this->entityId);
        if (!$entity) {
            $this->dispatch('notify', type: 'error', message: 'Entity not found.');
            return;
        }

        // Pre-cache active federations before the transaction alters pivot rows
        $activeFederations = $this->activeFederations;

        DB::transaction(function () use ($entity, $activeFederations): void {
            // 1. Suspend memberships or move entities
            if ($this->membershipAction === 'disable') {
                foreach ($activeFederations as $federation) {
                    $federation->entities()->updateExistingPivot($entity->id, ['status' => 'suspended']);
                    Cache::forget("federation_metadata:{$federation->id}");
                    Cache::forget("federation_edugain_metadata:{$federation->id}");
                }
            } elseif ($this->membershipAction === 'move' && $this->targetFederationId) {
                $targetFederation = Federation::find($this->targetFederationId);
                foreach ($activeFederations as $federation) {
                    $federation->entities()->detach($entity->id);
                    Cache::forget("federation_metadata:{$federation->id}");
                    Cache::forget("federation_edugain_metadata:{$federation->id}");
                }
                if ($targetFederation) {
                    $targetFederation->entities()->attach($entity->id, ['status' => 'pending']);
                }
            }

            // 2. Set entity status to suspended
            $entity->update(['status' => 'suspended']);

            // 3. Clear entity validation cache
            Cache::forget("metadata_validation:{$entity->id}");

            // 4. Audit log
            AuditLog::create([
                'user_id'    => Auth::id(),
                'entity_id'  => $entity->id,
                'action'     => 'entity_suspended',
                'old_values' => ['status' => 'active'],
                'new_values' => [
                    'status'            => 'suspended',
                    'membership_action' => $this->membershipAction,
                    'target_federation' => $this->targetFederationId,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);
        });

        Log::info('Entity suspended via EntitySuspendModal', [
            'entity_id'         => $entity->entity_id,
            'membership_action' => $this->membershipAction,
            'suspended_by'      => Auth::id(),
        ]);

        // 5. Notify contacts (outside transaction)
        if ($this->notifyOwner) {
            $this->sendSuspendNotification($entity, $activeFederations);
        }

        $this->show = false;
        $this->dispatch('notify', type: 'success', message: __('app.entity_suspended_success'));
        $this->dispatch('entity-suspended');
    }

    private function sendSuspendNotification(Entity $entity, Collection $activeFederations): void
    {
        $template = MailTemplate::where('group', 'entity_suspended')
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return;
        }

        $service = app(MailTemplateService::class);

        // Send one notification per federation so each message reflects the
        // correct federation context. When there is only one federation the
        // behaviour is identical to the previous single-send approach.
        foreach ($activeFederations as $federation) {
            $rendered = $service->render($template, [
                'entity'     => $entity,
                'federation' => $federation,
                'contact'    => null,
            ]);

            $service->sendToEntity(
                $entity,
                $rendered['subject'],
                $rendered['body'],
                $this->notifyContactTypes,
                $federation,
                Auth::user()
            );
        }
    }

    public function render(): View
    {
        return view('livewire.entity-suspend-modal');
    }
}
