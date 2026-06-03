<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckManagerRevocation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Cache::pull("force_logout_{$request->user()->id}")) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->flash('warning', 'Your federation manager access has been revoked. Please log in again.');

            return redirect()->route('login');
        }

        return $next($request);
    }
}
