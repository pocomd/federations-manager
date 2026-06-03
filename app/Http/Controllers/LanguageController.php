<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LanguageController extends Controller
{
    public function switch(Request $request, string $lang): RedirectResponse
    {
        abort_unless(config('federation.i18n_enabled'), 404);

        try {
            $supported = array_map('trim', explode(',',
                \App\Models\SystemPreference::get('supported_languages', 'en,ro')
            ));
        } catch (\Exception) {
            $supported = ['en', 'ro'];
        }

        if (! in_array($lang, $supported, true)) {
            abort(404);
        }

        session(['app_locale' => $lang]);

        if (Auth::check()) {
            Auth::user()->update(['preferred_locale' => $lang]);
        }

        return redirect()->back(302, [], route('dashboard'));
    }
}
