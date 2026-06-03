<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AttributeDefinition;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\EntityRequestedAttribute;
use App\Services\Entity\EntityMetadataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EntityRequestedAttributesController extends Controller
{
    public function index(Request $entity_request, Entity $entity): View
    {
        Gate::authorize('entity.view');

        $entity->load('entityRequestedAttributes.attributeDefinition');

        $addedIds = $entity->entityRequestedAttributes->pluck('attribute_definition_id');

        $schema = $entity_request->get('schema', '');

        $available = AttributeDefinition::active()
            ->whereNotIn('id', $addedIds)
            ->when($schema, fn($q) => $q->where('schema', $schema))
            ->orderBy('schema')
            ->orderBy('full_name')
            ->get();

        $counts = AttributeDefinition::active()
            ->whereNotIn('id', $addedIds)
            ->selectRaw('schema, count(*) as count')
            ->groupBy('schema')
            ->pluck('count', 'schema');

        $total = AttributeDefinition::active()->whereNotIn('id', $addedIds)->count();

        return view('entities.requested-attributes', compact('entity', 'available', 'counts', 'total', 'schema'));
    }

    public function store(Request $request, Entity $entity): RedirectResponse
    {
        Gate::authorize('entity.edit');

        $validated = $request->validate([
            'attribute_definition_id' => ['required', 'uuid', 'exists:attribute_definitions,id'],
            'is_required'             => ['boolean'],
            'reason'                  => ['nullable', 'string', 'max:500'],
        ]);

        $validated['is_required'] = (bool) ($validated['is_required'] ?? false);

        $existing = EntityRequestedAttribute::where('entity_id', $entity->id)
            ->where('attribute_definition_id', $validated['attribute_definition_id'])
            ->first();

        if ($existing) {
            return back()->with('error', 'This attribute is already added to the entity.');
        }

        EntityRequestedAttribute::create([
            'entity_id'                => $entity->id,
            'attribute_definition_id'  => $validated['attribute_definition_id'],
            'is_required'              => $validated['is_required'],
            'reason'                   => $validated['reason'] ?? null,
        ]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'entity_id'  => $entity->id,
            'action'     => 'requested_attribute_added',
            'new_values' => ['attribute_definition_id' => $validated['attribute_definition_id']],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        Cache::forget(EntityMetadataService::xmlCacheKey($entity));

        return back()->with('success', 'Attribute added.');
    }

    public function destroy(Entity $entity, EntityRequestedAttribute $attribute): RedirectResponse
    {
        Gate::authorize('entity.edit');

        $attribute->delete();

        AuditLog::create([
            'user_id'    => request()->user()?->id,
            'entity_id'  => $entity->id,
            'action'     => 'requested_attribute_removed',
            'old_values' => ['attribute_definition_id' => $attribute->attribute_definition_id],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        Cache::forget(EntityMetadataService::xmlCacheKey($entity));

        return back()->with('success', 'Attribute removed.');
    }
}
