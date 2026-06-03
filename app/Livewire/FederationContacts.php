<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Federation;
use App\Models\FederationContact;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class FederationContacts extends Component
{
    public Federation $federation;

    public array $contacts = [];

    public bool $adding = false;

    public array $newContact = [
        'type'       => 'technical',
        'given_name' => '',
        'sur_name'   => '',
        'email'      => '',
        'phone'      => '',
    ];

    public ?string $editingId = null;

    public array $editContact = [];

    protected function newContactRules(): array
    {
        return [
            'newContact.type'       => ['required', 'in:technical,administrative,security,support'],
            'newContact.given_name' => ['nullable', 'string', 'max:255'],
            'newContact.sur_name'   => ['nullable', 'string', 'max:255'],
            'newContact.email'      => ['required', 'email', 'max:255'],
            'newContact.phone'      => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function editContactRules(): array
    {
        return [
            'editContact.type'       => ['required', 'in:technical,administrative,security,support'],
            'editContact.given_name' => ['nullable', 'string', 'max:255'],
            'editContact.sur_name'   => ['nullable', 'string', 'max:255'],
            'editContact.email'      => ['required', 'email', 'max:255'],
            'editContact.phone'      => ['nullable', 'string', 'max:50'],
        ];
    }

    public function mount(Federation $federation): void
    {
        $this->federation = $federation;
        $this->loadContacts();
    }

    /**
     * Create a new contact for this federation.
     *
     * @authorizes  federation.edit (Gate::check — soft error, no exception)
     * @emits       contacts-updated (with new count, triggers badge refresh in parent)
     */
    public function addContact(): void
    {
        if (!Gate::check('federation.edit')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to manage federation contacts.');
            return;
        }

        $this->validate($this->newContactRules());

        FederationContact::create([
            'federation_id' => $this->federation->id,
            'type'          => $this->newContact['type'],
            'given_name'    => $this->newContact['given_name'] ?: null,
            'sur_name'      => $this->newContact['sur_name'] ?: null,
            'email'         => $this->newContact['email'],
            'phone'         => $this->newContact['phone'] ?: null,
        ]);

        $this->newContact = [
            'type'       => 'technical',
            'given_name' => '',
            'sur_name'   => '',
            'email'      => '',
            'phone'      => '',
        ];
        $this->adding = false;
        $this->loadContacts();

        $this->dispatch('notify', type: 'success', message: 'Contact added successfully.');
    }

    /**
     * Load a contact into the edit form. Guards against cross-federation edits by verifying
     * the contact belongs to this component's federation before populating state.
     */
    public function startEdit(string $id): void
    {
        $contact = FederationContact::find($id);

        if (!$contact || $contact->federation_id !== $this->federation->id) {
            return;
        }

        $this->editingId = $id;
        $this->editContact = [
            'type'       => $contact->type,
            'given_name' => $contact->given_name ?? '',
            'sur_name'   => $contact->sur_name ?? '',
            'email'      => $contact->email,
            'phone'      => $contact->phone ?? '',
        ];
    }

    /**
     * Persist edits to the contact. Re-verifies ownership before saving.
     *
     * @authorizes  federation.edit (Gate::check — soft error)
     */
    public function saveEdit(): void
    {
        if (!Gate::check('federation.edit')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to manage federation contacts.');
            return;
        }

        $this->validate($this->editContactRules());

        $contact = FederationContact::find($this->editingId);

        if (!$contact || $contact->federation_id !== $this->federation->id) {
            $this->dispatch('notify', type: 'error', message: 'Contact not found.');
            return;
        }

        $contact->update([
            'type'       => $this->editContact['type'],
            'given_name' => $this->editContact['given_name'] ?: null,
            'sur_name'   => $this->editContact['sur_name'] ?: null,
            'email'      => $this->editContact['email'],
            'phone'      => $this->editContact['phone'] ?: null,
        ]);

        $this->editingId  = null;
        $this->editContact = [];
        $this->loadContacts();

        $this->dispatch('notify', type: 'success', message: 'Contact updated successfully.');
    }

    public function cancelEdit(): void
    {
        $this->editingId   = null;
        $this->editContact = [];
    }

    /**
     * Delete a contact. Verifies ownership before deleting.
     *
     * @authorizes  federation.edit (Gate::check — soft error)
     */
    public function deleteContact(string $id): void
    {
        if (!Gate::check('federation.edit')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to manage federation contacts.');
            return;
        }

        $contact = FederationContact::find($id);

        if (!$contact || $contact->federation_id !== $this->federation->id) {
            $this->dispatch('notify', type: 'error', message: 'Contact not found.');
            return;
        }

        $contact->delete();
        $this->loadContacts();

        $this->dispatch('notify', type: 'success', message: 'Contact deleted.');
    }

    /**
     * Hydrate the contacts array from DB and notify the parent of the new count.
     *
     * @emits  contacts-updated (count — used by parent tab badge)
     */
    public function loadContacts(): void
    {
        $this->contacts = $this->federation->contacts()
            ->orderBy('type')
            ->orderBy('sur_name')
            ->get()
            ->map(fn (FederationContact $c) => [
                'id'         => $c->id,
                'type'       => $c->type,
                'given_name' => $c->given_name ?? '',
                'sur_name'   => $c->sur_name ?? '',
                'email'      => $c->email,
                'phone'      => $c->phone ?? '',
            ])
            ->toArray();

        $this->dispatch('contacts-updated', count: count($this->contacts));
    }

    public function render(): View
    {
        return view('livewire.federation-contacts');
    }
}
