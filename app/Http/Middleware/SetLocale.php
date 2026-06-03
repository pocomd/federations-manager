<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SystemPreference;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('federation.i18n_enabled')) {
            App::setLocale('en');
            return $next($request);
        }

        try {
            $supported     = array_map('trim', explode(',', SystemPreference::get('supported_languages', 'en,ro')));
            $defaultLocale = SystemPreference::get('default_language', 'en');
        } catch (\Exception $e) {
            $supported     = ['en', 'ro'];
            $defaultLocale = 'en';
        }

        if (session()->has('app_locale')) {
            $locale = session('app_locale');
        } elseif (Auth::check() && Auth::user()->preferred_locale) {
            $locale = Auth::user()->preferred_locale;
        } else {
            $locale = $defaultLocale;
        }

        if (! in_array($locale, $supported, true)) {
            $locale = 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
