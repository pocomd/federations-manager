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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * EntityReactivateModal
 *
 * 3-step wizard for reactivating a suspended entity:
 *   Step 1 — Impact: current suspension state, existing membership, cert health
 *   Step 2 — Federation: re-request approval from existing fed, or apply to a new one
 *   Step 3 — Confirm: summary + optional notify + execute
 *
 * Entity status becomes 'active' immediately; the federation membership is set to
 * 'pending' so the federation admin must re-approve before the entity appears in
 * any metadata feed.
 */
class EntityReactivateModal extends Component
{
    public bool    $show              = false;
    public ?string $entityId          = null;
    public int     $step              = 1;

    public string  $membershipOption  = 'reapply'; // 'reapply' | 'new' | 'none'
    public ?string $targetFederationId = null;

    public bool  $notifyOwner        = false;
    public array $notifyContactTypes = ['technical'];


    /** @listens  open-reactivate-modal */
    #[On('open-reactivate-modal')]
    public function open(string $entityId): void
    {
        $this->entityId           = $entityId;
        $this->step               = 1;
        $this->membershipOption   = 'reapply';
        $this->targetFederationId = null;
        $this->notifyOwner        = false;
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
        return $this->entityId
            ? Entity::with('certificates')->find($this->entityId)
            : null;
    }

    /** Most relevant non-active pivot row (suspended > pending > rejected). */
    #[Computed]
    public function existingMembership(): ?object
    {
        if (!$this->entityId) {
            return null;
        }

        return DB::table('entity_federation')
            ->where('entity_id', $this->entityId)
            ->whereIn('status', ['suspended', 'pending', 'rejected'])
            ->orderByRaw("FIELD(status, 'suspended', 'pending', 'rejected')")
            ->first();
    }

    #[Computed]
    public function existingFederation(): ?Federation
    {
        $m = $this->existingMembership;
        return $m ? Federation::find($m->federation_id) : null;
    }

    #[Computed]
    public function availableFederations(): Collection
    {
        $excludeId = $this->existingFederation?->id;
        return Federation::where('status', 'active')
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('name')
            ->get();
    }


    public function nextStep(): void
    {
        if ($this->step === 2
            && $this->membershipOption === 'new'
            && !$this->targetFederationId
        ) {
            $this->dispatch('notify', type: 'error', message: __('app.reactivate_select_federation'));
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
     * Reactivate the entity. Entity status becomes 'active' immediately, but federation
     * membership is set to 'pending' — the federation admin must re-approve before the
     * entity appears in any metadata feed.
     *
     * @authorizes  entity.edit (Gate::check — soft error)
     * @dispatches  MailTemplateService::entity_reactivated (conditional — if notifyOwner and template active)
     * @emits       entity-reactivated
     */
    public function executeReactivate(): void
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

        $membership        = $this->existingMembership;
        $existingFederation = $this->existingFederation;

        DB::transaction(function () use ($entity, $membership, $existingFederation): void {
            $entity->update(['status' => 'active']);

            if ($this->membershipOption === 'reapply' && $membership && in_array($membership->status, ['suspended', 'rejected'])) {
                DB::table('entity_federation')
                    ->where('entity_id', $entity->id)
                    ->where('federation_id', $membership->federation_id)
                    ->update(['status' => 'pending', 'updated_at' => now()]);
            } elseif ($this->membershipOption === 'new' && $this->targetFederationId) {
                // Remove any existing non-active row for this entity first
                if ($membership) {
                    DB::table('entity_federation')
                        ->where('entity_id', $entity->id)
                        ->where('federation_id', $membership->federation_id)
                        ->delete();
                }
                $entity->federations()->attach($this->targetFederationId, [
                    'status'     => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            // 'pending' existing row or 'none' → no pivot change needed

            AuditLog::create([
                'user_id'    => Auth::id(),
                'entity_id'  => $entity->id,
                'action'     => 'entity_reactivated',
                'old_values' => ['status' => 'suspended'],
                'new_values' => [
                    'status'            => 'active',
                    'membership_option' => $this->membershipOption,
                    'federation_id'     => $this->membershipOption === 'new'
                        ? $this->targetFederationId
                        : $membership?->federation_id,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);
        });

        Log::info('Entity reactivated via EntityReactivateModal', [
            'entity_id'         => $entity->entity_id,
            'membership_option' => $this->membershipOption,
            'reactivated_by'    => Auth::id(),
        ]);

        if ($this->notifyOwner) {
            $this->sendReactivationNotification($entity, $existingFederation);
        }

        $this->show = false;
        $this->dispatch('notify', type: 'success', message: __('app.entity_reactivated_success'));
        $this->dispatch('entity-reactivated');
    }

    private function sendReactivationNotification(Entity $entity, ?Federation $federation): void
    {
        $template = MailTemplate::where('group', 'entity_reactivated')
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return;
        }

        $service  = app(MailTemplateService::class);
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

    public function render(): View
    {
        return view('livewire.entity-reactivate-modal');
    }
}
