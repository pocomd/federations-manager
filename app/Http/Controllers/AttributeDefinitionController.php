<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AttributeDefinition;
use App\Models\EntityRequestedAttribute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AttributeDefinitionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('entity.view');

        $query = AttributeDefinition::query();

        if ($request->filled('schema')) {
            $query->where('schema', $request->schema);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('saml2_oid', 'like', '%' . $request->search . '%')
                  ->orWhere('full_name', 'like', '%' . $request->search . '%');
            });
        }

        $attributes = $query->orderBy('schema')->orderBy('name')
                            ->paginate(25)->withQueryString();

        $counts = AttributeDefinition::selectRaw('schema, count(*) as count')
                      ->groupBy('schema')->pluck('count', 'schema');
        $total  = AttributeDefinition::count();

        return view('attributes.index', compact('attributes', 'counts', 'total'));
    }

    public function create(): View
    {
        Gate::authorize('entity.edit');

        return view('attributes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('entity.edit');

        $validated = $request->validate($this->rules());

        $validated['is_required'] = (bool) ($validated['is_required'] ?? false);
        $validated['is_active']   = (bool) ($validated['is_active'] ?? true);

        AttributeDefinition::create($validated);

        return redirect()->route('attributes.index')
            ->with('success', 'Attribute definition created.');
    }

    public function edit(AttributeDefinition $attribute): View
    {
        Gate::authorize('entity.edit');

        return view('attributes.edit', compact('attribute'));
    }

    public function update(Request $request, AttributeDefinition $attribute): RedirectResponse
    {
        Gate::authorize('entity.edit');

        $validated = $request->validate($this->rules($attribute->id));

        $validated['is_required'] = (bool) ($validated['is_required'] ?? false);
        $validated['is_active']   = (bool) ($validated['is_active'] ?? false);

        $attribute->update($validated);

        return redirect()->route('attributes.index')
            ->with('success', 'Attribute definition updated.');
    }

    public function destroy(AttributeDefinition $attribute): RedirectResponse
    {
        Gate::authorize('entity.edit');

        $inUse = EntityRequestedAttribute::where('attribute_definition_id', $attribute->id)->exists();

        if ($inUse) {
            return redirect()->route('attributes.index')
                ->with('error', 'Attribute is in use by entities and cannot be deleted.');
        }

        $attribute->update(['is_active' => false]);

        return redirect()->route('attributes.index')
            ->with('success', 'Attribute definition deactivated.');
    }

    private function rules(?string $ignoreId = null): array
    {
        return [
            'name'        => ['required', 'string', 'max:100', 'alpha_dash',
                              'unique:attribute_definitions,name' . ($ignoreId ? ',' . $ignoreId : '')],
            'full_name'   => ['required', 'string', 'max:255'],
            'saml2_oid'   => ['nullable', 'string', 'max:255',
                              'regex:/^urn:oid:[\d\.]+$/'],
            'saml1_urn'   => ['nullable', 'string', 'max:255',
                              'regex:/^urn:mace:.+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_required' => ['boolean'],
            'is_active'   => ['boolean'],
        ];
    }
}
