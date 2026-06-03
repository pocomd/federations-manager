<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\FederationScopeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveFederationScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            app(FederationScopeService::class)->resolve(Auth::user());
        }

        return $next($request);
    }
}
