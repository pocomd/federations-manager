<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\EntityRuleConfig;
use App\Models\FederationRuleConfig;
use App\Models\RuleDefinition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EntityRuleConfigController extends Controller
{
    public function index(Entity $entity): View
    {
        Gate::authorize('entity.view');

        $federation = $entity->federations()->first();

        $rules   = RuleDefinition::orderBy('id')->get();
        $configs = EntityRuleConfig::where('entity_id', $entity->id)
            ->get()
            ->keyBy('rule_id');

        $federationConfigs = $federation
            ? FederationRuleConfig::where('federation_id', $federation->id)->get()->keyBy('rule_id')
            : collect();

        return view('entities.rules', compact('entity', 'federation', 'rules', 'configs', 'federationConfigs'));
    }

    public function update(Request $request, Entity $entity, RuleDefinition $rule): RedirectResponse
    {
        Gate::authorize('entity.edit');

        $data = $request->validate([
            'enabled'  => ['required', 'boolean'],
            'severity' => ['nullable', Rule::in(['error', 'warning'])],
        ]);

        EntityRuleConfig::updateOrCreate(
            ['entity_id' => $entity->id, 'rule_id' => $rule->id],
            $data,
        );

        return back()->with('success', "Rule {$rule->id} config updated for this entity.");
    }

    public function destroy(Entity $entity, RuleDefinition $rule): RedirectResponse
    {
        Gate::authorize('entity.edit');

        EntityRuleConfig::where('entity_id', $entity->id)
            ->where('rule_id', $rule->id)
            ->delete();

        return back()->with('success', "Rule {$rule->id} config reset to defaults for this entity.");
    }
}
