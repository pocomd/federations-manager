<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RuleDefinition;
use App\Services\Metadata\RuleRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

class RuleDefinitionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('federation.create');

        $rules = RuleDefinition::orderBy('id')->get()->keyBy('id');

        return view('rules.index', compact('rules'));
    }

    public function toggle(RuleDefinition $rule, RuleRegistry $registry, Request $request): RedirectResponse
    {
        Gate::authorize('federation.create');

        $wasActive = $rule->active;
        $rule->update(['active' => ! $wasActive]);
        $registry->flush();

        $state = $rule->active ? 'enabled' : 'disabled';

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'rule_toggled',
            'old_values' => ['active' => $wasActive, 'rule_id' => $rule->id, 'name' => $rule->name],
            'new_values' => ['active' => $rule->active, 'rule_id' => $rule->id, 'state' => $state],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return back()->with('success', "Rule {$rule->id} ({$rule->name}) {$state}.");
    }

    public function sync(RuleRegistry $registry): RedirectResponse
    {
        Gate::authorize('federation.create');

        Artisan::call('rules:sync');
        $registry->flush();

        return redirect()->route('rules.index')->with('success', 'Rules synced from code successfully.');
    }
}
