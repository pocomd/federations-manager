<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Federation;
use App\Models\FederationRuleConfig;
use App\Models\RuleDefinition;
use App\Services\Metadata\RuleRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FederationRuleConfigController extends Controller
{
    public function index(Federation $federation): View
    {
        Gate::authorize('federation.edit');

        $rules   = RuleDefinition::orderBy('id')->get();
        $configs = FederationRuleConfig::where('federation_id', $federation->id)
            ->get()
            ->keyBy('rule_id');

        return view('federations.rules', compact('federation', 'rules', 'configs'));
    }

    public function update(Request $request, Federation $federation, RuleDefinition $rule): RedirectResponse
    {
        Gate::authorize('federation.edit');

        $data = $request->validate([
            'enabled'  => ['required', 'boolean'],
            'severity' => ['nullable', Rule::in(['error', 'warning'])],
        ]);

        FederationRuleConfig::updateOrCreate(
            ['federation_id' => $federation->id, 'rule_id' => $rule->id],
            $data,
        );

        return back()->with('success', "Rule {$rule->id} config updated for {$federation->name}.");
    }

    public function destroy(Federation $federation, RuleDefinition $rule): RedirectResponse
    {
        Gate::authorize('federation.edit');

        FederationRuleConfig::where('federation_id', $federation->id)
            ->where('rule_id', $rule->id)
            ->delete();

        return back()->with('success', "Rule {$rule->id} config reset to defaults for {$federation->name}.");
    }
}
