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
 * FederationDeactivateModal
 *
 * 6-step wizard for deactivating a federation (step 3 skipped when no pending entities):
 *   Step 1 — Impact: overview of active/pending entity counts
 *   Step 2 — Active Entities: choose leave / disable / move
 *   Step 3 — Pending Entities: choose keep / reject  (skipped when none)
 *   Step 4 — Jobs: confirms auto-generation will stop (informational)
 *   Step 5 — Notify: toggle notification to entity contacts
 *   Step 6 — Confirm: summary and execute
 *
 * Never name a method validate() — conflicts with Livewire base class.
 */
class FederationDeactivateModal extends Component
{
    public bool    $show               = false;
    public ?string $federationId       = null;
    public int     $step               = 1;
    public string  $entityAction       = 'leave'; // 'leave' | 'disable' | 'move'
    public ?string $targetFederationId = null;
    public string  $pendingAction      = 'keep';  // 'keep' | 'reject'
    public bool  $notifyOwners        = true;
    public array $notifyContactTypes  = ['technical'];

    public bool   $showPreview      = false;
    public string $previewSubject   = '';
    public string $previewBody      = '';
    public ?string $previewTemplateId = null;


    /** @listens  open-deactivate-modal */
    #[On('open-deactivate-modal')]
    public function open(string $federationId): void
    {
        $this->federationId       = $federationId;
        $this->step               = 1;
        $this->entityAction       = 'leave';
        $this->targetFederationId = null;
        $this->pendingAction      = 'keep';
        $this->notifyOwners        = true;
        $this->notifyContactTypes  = ['technical'];
        $this->show               = true;
    }

    public function close(): void
    {
        $this->show         = false;
        $this->federationId = null;
    }

    public function previewTemplate(): void
    {
        $federation = Federation::find($this->federationId);
        $template   = MailTemplate::where('group', 'federation_deactivated')->first();

        if (!$federation) {
            $this->dispatch('notify', type: 'error', message: 'Federation not found.');
            return;
        }

        if (!$template) {
            $this->dispatch('notify', type: 'warning', message: __('app.mail_template_not_found'));
            return;
        }

        $sampleEntity = $this->activeEntities->first();

        $rendered = app(MailTemplateService::class)->render($template, [
            'entity'     => $sampleEntity,
            'federation' => $federation,
            'contact'    => null,
        ]);

        $this->previewSubject    = $rendered['subject'];
        $this->previewBody       = $rendered['body'];
        $this->previewTemplateId = $template->id;
        $this->showPreview       = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
    }


    #[Computed]
    public function federation(): ?Federation
    {
        if ($this->federationId === null) {
            return null;
        }
        return Federation::find($this->federationId);
    }

    #[Computed]
    public function activeEntities(): Collection
    {
        if ($this->federationId === null) {
            return collect();
        }
        $federation = Federation::find($this->federationId);
        if (!$federation) {
            return collect();
        }
        return $federation->entities()
            ->wherePivot('status', 'active')
            ->with(['uiInfo'])
            ->get();
    }

    #[Computed]
    public function pendingEntities(): Collection
    {
        if ($this->federationId === null) {
            return collect();
        }
        $federation = Federation::find($this->federationId);
        if (!$federation) {
            return collect();
        }
        return $federation->entities()
            ->wherePivot('status', 'pending')
            ->get();
    }

    #[Computed]
    public function availableTargetFederations(): Collection
    {
        return Federation::where('status', 'active')
            ->where('id', '!=', $this->federationId)
            ->orderBy('name')
            ->get();
    }


    /** Automatically skips step 3 (pending entities) when there are none. */
    public function nextStep(): void
    {
        if ($this->step === 2 && $this->entityAction === 'move' && !$this->targetFederationId) {
            $this->dispatch('notify', type: 'error', message: __('app.deactivate_select_federation'));
            return;
        }

        $nextStep = $this->step + 1;

        // Skip step 3 (pending entities) when there are none
        if ($nextStep === 3 && $this->pendingEntities->isEmpty()) {
            $nextStep = 4;
        }

        if ($nextStep === 6 && $this->notifyOwners && empty($this->notifyContactTypes)) {
            $this->dispatch('notify', type: 'error', message: __('app.suspend_select_contact_type'));
            return;
        }

        $this->step = $nextStep;
    }

    public function prevStep(): void
    {
        $prevStep = $this->step - 1;

        // Skip step 3 (pending entities) when there are none
        if ($prevStep === 3 && $this->pendingEntities->isEmpty()) {
            $prevStep = 2;
        }

        if ($prevStep >= 1) {
            $this->step = $prevStep;
        }
    }


    /**
     * Deactivate the federation and handle entity memberships per wizard selections.
     * Active-entity collections are pre-cached before the transaction alters pivot rows.
     * Notifications are sent outside the transaction to avoid rollback on mail failure.
     *
     * @authorizes  federation.edit (Gate::check — soft error)
     * @dispatches  MailTemplateService::federation_deactivated (conditional — active entities only, if template active)
     * @emits       federation-deactivated
     */
    public function executeDeactivate(): void
    {
        if (!Gate::check('federation.edit')) {
            $this->dispatch('notify', type: 'error', message: 'Unauthorized.');
            return;
        }

        $federation = Federation::find($this->federationId);
        if (!$federation) {
            $this->dispatch('notify', type: 'error', message: 'Federation not found.');
            return;
        }

        // Pre-cache collections before transaction alters pivot rows
        $activeEntities  = $this->activeEntities;
        $pendingEntities = $this->pendingEntities;

        DB::transaction(function () use ($federation, $activeEntities, $pendingEntities): void {
            // 1. Handle active entities
            if ($this->entityAction === 'disable') {
                foreach ($activeEntities as $entity) {
                    $federation->entities()->updateExistingPivot($entity->id, ['status' => 'suspended']);
                }
            } elseif ($this->entityAction === 'move' && $this->targetFederationId) {
                $target = Federation::find($this->targetFederationId);
                foreach ($activeEntities as $entity) {
                    $federation->entities()->detach($entity->id);
                    if ($target) {
                        $target->entities()->attach($entity->id, ['status' => 'pending']);
                    }
                }
            }
            // 'leave' — no pivot changes for active entities

            // 2. Handle pending entities
            if ($this->pendingAction === 'reject') {
                foreach ($pendingEntities as $entity) {
                    $federation->entities()->updateExistingPivot($entity->id, ['status' => 'rejected']);
                }
            }

            // 3. Deactivate the federation
            $federation->update(['status' => 'inactive']);

            // 4. Clear metadata cache
            Cache::forget("federation_metadata:{$federation->id}");

            // 5. Audit log
            AuditLog::create([
                'user_id'    => Auth::id(),
                'entity_id'  => null,
                'action'     => 'federation_deactivated',
                'old_values' => ['status' => 'active'],
                'new_values' => [
                    'status'        => 'inactive',
                    'entity_action' => $this->entityAction,
                    'pending_action' => $this->pendingAction,
                    'target_federation' => $this->targetFederationId,
                    'federation_id' => $federation->id,
                    'name'          => $federation->name,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);
        });

        Log::info('Federation deactivated via FederationDeactivateModal', [
            'federation_id' => $federation->id,
            'entity_action' => $this->entityAction,
            'pending_action' => $this->pendingAction,
            'deactivated_by' => Auth::id(),
        ]);

        // 6. Notify entity contacts (outside transaction)
        if ($this->notifyOwners && $activeEntities->isNotEmpty()) {
            $this->sendDeactivationNotifications($activeEntities, $federation);
        }

        $this->show = false;
        $this->dispatch('notify', type: 'success', message: __('app.federation_deactivated_success'));
        $this->dispatch('federation-deactivated');
    }

    private function sendDeactivationNotifications(Collection $entities, Federation $federation): void
    {
        $service  = app(MailTemplateService::class);
        $template = $service->resolveTemplate('federation_deactivated', 'en', $federation);

        if (!$template) {
            return;
        }

        foreach ($entities as $entity) {
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
        return view('livewire.federation-deactivate-modal');
    }
}
