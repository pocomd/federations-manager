<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\AttributeDefinition;
use App\Models\AuditLog;
use App\Models\Federation;
use App\Models\FederationRequiredAttribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

class FederationAttributes extends Component
{
    public string $federationId;

    public string $selectedAttributeId = '';
    public string $isRequired          = '1';
    public string $notes               = '';

    public function mount(string $federationId): void
    {
        $this->federationId = $federationId;
    }

    #[Computed]
    public function federation(): Federation
    {
        return Federation::findOrFail($this->federationId);
    }

    #[Computed]
    public function requiredAttributes(): Collection
    {
        return $this->federation
            ->requiredAttributes()
            ->with('attributeDefinition')
            ->get();
    }

    #[Computed]
    public function availableAttributes(): Collection
    {
        $addedIds = $this->requiredAttributes->pluck('attribute_definition_id')->all();

        return AttributeDefinition::active()
            ->whereNotIn('id', $addedIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Add an attribute requirement to the federation. Idempotency guard prevents duplicates.
     * Busts the #[Computed] cache for requiredAttributes and availableAttributes via unset().
     *
     * @authorizes  federation.edit
     * @emits       attributes-count-changed (new count — used by parent tab badge)
     */
    public function addAttribute(): void
    {
        Gate::authorize('federation.edit');

        $data = $this->validate([
            'selectedAttributeId' => ['required', 'uuid', 'exists:attribute_definitions,id'],
            'isRequired'          => ['required', 'boolean'],
            'notes'               => ['nullable', 'string', 'max:500'],
        ]);

        if ($this->federation->requiredAttributes()
            ->where('attribute_definition_id', $this->selectedAttributeId)
            ->exists()) {
            $this->dispatch('notify', type: 'error', message: 'That attribute is already listed for this federation.');
            return;
        }

        $ra = FederationRequiredAttribute::create([
            'federation_id'           => $this->federationId,
            'attribute_definition_id' => $this->selectedAttributeId,
            'is_required'             => (bool) $this->isRequired,
            'notes'                   => $this->notes ?: null,
        ]);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'federation_attribute_added',
            'model_type' => FederationRequiredAttribute::class,
            'model_id'   => $ra->id,
            'changes'    => json_encode(['federation_id' => $this->federationId]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        $this->selectedAttributeId = '';
        $this->isRequired          = '1';
        $this->notes               = '';

        unset($this->requiredAttributes, $this->availableAttributes);

        $this->dispatch('notify', type: 'success', message: 'Attribute requirement added.');
        $this->dispatch('attributes-count-changed', count: $this->requiredAttributes->count());
    }

    /**
     * Remove an attribute requirement. Busts the #[Computed] cache via unset().
     *
     * @authorizes  federation.edit
     * @emits       attributes-count-changed
     */
    public function removeAttribute(string $id): void
    {
        Gate::authorize('federation.edit');

        $ra = FederationRequiredAttribute::findOrFail($id);

        $ra->delete();

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'federation_attribute_removed',
            'model_type' => FederationRequiredAttribute::class,
            'model_id'   => $id,
            'changes'    => json_encode(['federation_id' => $this->federationId]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        unset($this->requiredAttributes, $this->availableAttributes);

        $this->dispatch('notify', type: 'success', message: 'Attribute requirement removed.');
        $this->dispatch('attributes-count-changed', count: $this->requiredAttributes->count());
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.federation-attributes');
    }
}
