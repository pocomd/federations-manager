<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AttributeDefinition;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\EntityArp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ArpController extends Controller
{
    public function index(Request $request, Entity $entity): View
    {
        Gate::authorize('arp.view');

        if ($entity->type !== 'idp') {
            abort(403, 'ARP editor is only available for Identity Providers.');
        }

        $federationIds = $entity->federations()->pluck('federations.id');

        $schema = $request->get('schema', '');

        $sps = Entity::where('type', 'sp')
            ->whereHas('federations', function ($q) use ($federationIds) {
                $q->whereIn('federations.id', $federationIds);
            })
            ->with('entityRequestedAttributes.attributeDefinition', 'uiInfo')
            ->get();

        $arpRules = EntityArp::where('idp_entity_id', $entity->id)
            ->with(['attributeDefinition', 'sp'])
            ->get()
            ->groupBy('sp_entity_id');

        $currentSpIds = $sps->pluck('id');

        $orphanedRules = EntityArp::where('idp_entity_id', $entity->id)
            ->whereNotIn('sp_entity_id', $currentSpIds)
            ->with(['attributeDefinition', 'sp'])
            ->get()
            ->groupBy('sp_entity_id');

        $attrCounts = AttributeDefinition::active()
            ->selectRaw('schema, count(*) as count')
            ->groupBy('schema')
            ->pluck('count', 'schema');

        return view('entities.arp', compact('entity', 'sps', 'arpRules', 'orphanedRules', 'schema', 'attrCounts'));
    }

    public function store(Request $request, Entity $entity): RedirectResponse
    {
        Gate::authorize('arp.edit');

        if ($entity->type !== 'idp') {
            abort(403, 'ARP editor is only available for Identity Providers.');
        }

        $validated = $request->validate([
            'sp_entity_id'            => ['required', 'uuid', 'exists:entities,id'],
            'attribute_definition_id' => ['required', 'uuid', 'exists:attribute_definitions,id'],
            'is_permitted'            => ['boolean'],
            'notes'                   => ['nullable', 'string', 'max:500'],
        ]);

        $validated['is_permitted'] = (bool) ($validated['is_permitted'] ?? true);

        EntityArp::updateOrCreate(
            [
                'idp_entity_id'           => $entity->id,
                'sp_entity_id'            => $validated['sp_entity_id'],
                'attribute_definition_id' => $validated['attribute_definition_id'],
            ],
            [
                'is_permitted' => $validated['is_permitted'],
                'notes'        => $validated['notes'] ?? null,
            ]
        );

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'entity_id'  => $entity->id,
            'action'     => 'arp_updated',
            'new_values' => [
                'sp_entity_id'            => $validated['sp_entity_id'],
                'attribute_definition_id' => $validated['attribute_definition_id'],
                'is_permitted'            => $validated['is_permitted'],
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return back()->with('success', 'ARP rule saved.');
    }

    public function destroy(Entity $entity, EntityArp $arp): RedirectResponse
    {
        Gate::authorize('arp.edit');

        if ($arp->idp_entity_id !== $entity->id) {
            abort(403);
        }

        AuditLog::create([
            'user_id'    => request()->user()?->id,
            'entity_id'  => $entity->id,
            'action'     => 'arp_deleted',
            'old_values' => [
                'sp_entity_id'            => $arp->sp_entity_id,
                'attribute_definition_id' => $arp->attribute_definition_id,
                'is_permitted'            => $arp->is_permitted,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        $arp->delete();

        return back()->with('success', 'ARP rule removed.');
    }
}
