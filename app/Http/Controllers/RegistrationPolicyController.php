<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Federation;
use App\Models\FederationRegistrationPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegistrationPolicyController extends Controller
{
    private const AVAILABLE_LANGS = [
        'en' => 'English',
        'ro' => 'Romanian',
        'de' => 'German',
        'fr' => 'French',
        'ru' => 'Russian',
        'pl' => 'Polish',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'et' => 'Estonian',
    ];

    public function index(Federation $federation): View
    {
        Gate::authorize('federation.edit');

        $policies = $federation->registrationPolicies()->orderBy('lang')->get();

        return view('federations.policies.index', compact('federation', 'policies'));
    }

    public function create(Federation $federation): View
    {
        Gate::authorize('federation.edit');

        $usedLangs  = $federation->registrationPolicies()->pluck('lang')->toArray();
        $availableLangs = array_filter(
            self::AVAILABLE_LANGS,
            fn ($code) => !in_array($code, $usedLangs, true),
            ARRAY_FILTER_USE_KEY
        );

        return view('federations.policies.create', compact('federation', 'availableLangs', 'usedLangs'));
    }

    public function store(Request $request, Federation $federation): RedirectResponse
    {
        Gate::authorize('federation.edit');

        $validated = $request->validate($this->rules($federation));
        $validated['enabled'] = (bool) ($validated['enabled'] ?? false);

        FederationRegistrationPolicy::create([
            'federation_id' => $federation->id,
            ...$validated,
        ]);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'registration_policy_created',
            'new_values' => $validated,
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect()
            ->route('federations.policies.index', $federation)
            ->with('success', 'Registration policy added.');
    }

    public function edit(Federation $federation, FederationRegistrationPolicy $policy): View
    {
        Gate::authorize('federation.edit');

        $usedLangs = $federation->registrationPolicies()
            ->where('id', '!=', $policy->id)
            ->pluck('lang')
            ->toArray();

        $availableLangs = array_filter(
            self::AVAILABLE_LANGS,
            fn ($code) => !in_array($code, $usedLangs, true),
            ARRAY_FILTER_USE_KEY
        );

        return view('federations.policies.edit', compact('federation', 'policy', 'availableLangs'));
    }

    public function update(Request $request, Federation $federation, FederationRegistrationPolicy $policy): RedirectResponse
    {
        Gate::authorize('federation.edit');

        $validated = $request->validate($this->rules($federation, $policy->id));
        $validated['enabled'] = (bool) ($validated['enabled'] ?? false);

        $policy->update($validated);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'registration_policy_updated',
            'new_values' => $validated,
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect()
            ->route('federations.policies.index', $federation)
            ->with('success', 'Registration policy updated.');
    }

    public function destroy(Federation $federation, FederationRegistrationPolicy $policy): RedirectResponse
    {
        Gate::authorize('federation.edit');

        $old = $policy->toArray();
        $policy->delete();

        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'registration_policy_deleted',
            'old_values' => $old,
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()
            ->route('federations.policies.index', $federation)
            ->with('success', 'Registration policy deleted.');
    }

    private function rules(Federation $federation, ?string $ignoreId = null): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'lang'         => [
                'required', 'string', 'max:10',
                Rule::unique('federation_registration_policies', 'lang')
                    ->where('federation_id', $federation->id)
                    ->ignore($ignoreId),
            ],
            'url'          => ['required', 'url', 'starts_with:https://', 'max:1024'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'enabled'      => ['boolean'],
        ];
    }
}
