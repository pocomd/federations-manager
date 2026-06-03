<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Federation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class FederationJaggerCompat extends Component
{
    public Federation $federation;

    public bool $jaggerCompatEnabled;
    public string $jaggerFedName;

    public function mount(int|string $federationId): void
    {
        $this->federation          = Federation::findOrFail($federationId);
        $this->jaggerCompatEnabled = (bool) $this->federation->jagger_compat_enabled;
        $this->jaggerFedName       = $this->federation->jagger_fed_name ?? $this->federation->slug;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->federation);

        $validated = $this->validate([
            'jaggerCompatEnabled' => ['boolean'],
            'jaggerFedName'       => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_\-]+$/',
                Rule::unique('federations', 'jagger_fed_name')->ignore($this->federation->id),
            ],
        ]);

        $this->federation->update([
            'jagger_compat_enabled' => $this->jaggerCompatEnabled,
            'jagger_fed_name'       => $this->jaggerFedName ?: null,
        ]);

        $this->federation->refresh();

        $this->dispatch('notify', type: 'success', message: 'Jagger compatibility settings saved.');
    }

    public function render(): View
    {
        return view('livewire.federation-jagger-compat');
    }
}
