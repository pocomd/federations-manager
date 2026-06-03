<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SystemPreferencesRequest;
use App\Models\AuditLog;
use App\Models\SystemPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SystemPreferencesController extends Controller
{
    public function index(): View
    {
        Gate::authorize('federation.create');

        $preferences = SystemPreference::all()
            ->when(! config('federation.i18n_enabled'), fn ($col) => $col->whereNotIn('key', ['supported_languages', 'default_language']))
            ->groupBy('category');

        return view('preferences.index', compact('preferences'));
    }

    public function update(SystemPreferencesRequest $request): RedirectResponse
    {
        Gate::authorize('federation.create');

        foreach ($request->validated() as $key => $value) {
            SystemPreference::set($key, $value);
        }

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'system_preferences_updated',
            'old_values' => null,
            'new_values' => $request->validated(),
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Preferences saved.');
    }
}
