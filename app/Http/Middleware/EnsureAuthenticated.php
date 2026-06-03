<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\SamlServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureAuthenticated
 *
 * Grants access if EITHER a Laravel session OR a SimpleSAMLphp SP session
 * is active for the current request.
 *
 * When a live SimpleSAMLphp session is detected but no Laravel session exists,
 * the user is automatically promoted: attributes are read and Auth::login() is
 * called so the rest of the request stack uses standard Laravel auth.
 *
 * Redirect target on failure: route('login')
 */
class EnsureAuthenticated
{
    public function __construct(
        private readonly SamlServiceInterface $saml,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Fast path: standard Laravel auth (covers local login and previous SAML logins)
        if (Auth::check()) {
            return $next($request);
        }

        // Check for an active SimpleSAMLphp session (no Laravel session yet)
        try {
            if ($this->saml->isAuthenticated()) {
                // Promote the SSP session to a Laravel session
                /** @var \App\Http\Controllers\Auth\SamlAuthController $controller */
                $controller = app(\App\Http\Controllers\Auth\SamlAuthController::class);
                $user = $controller->findOrCreateUser($this->saml->getAttributes());
                Auth::login($user, remember: true);
                $request->session()->regenerate();

                return $next($request);
            }
        } catch (\Throwable) {
            // SimpleSAMLphp not installed or session unreadable — fall through to redirect
        }

        // JSON / API clients receive 401; browser requests get redirected to login
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->route('login');
    }
}
