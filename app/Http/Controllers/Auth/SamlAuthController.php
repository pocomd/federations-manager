<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemPreference;
use App\Models\User;
use App\Services\Auth\SamlServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * SamlAuthController
 *
 * Handles both SAML2 SSO (via SimpleSAMLphp) and local user/password login.
 *
 * SAML flow:
 *   1. GET  /saml/login    → login()  — redirect to SimpleSAMLphp SSO initiation
 *   2. POST /saml/acs      → acs()    — callback after SimpleSAMLphp auth; read SP attributes
 *   3. GET  /logout        → logout() — clear Laravel + SimpleSAMLphp sessions
 *
 * Local login flow:
 *   1. GET  /login         → showLogin()  — render dual-option login page
 *   2. POST /login         → localLogin() — Auth::attempt() + redirect
 */
class SamlAuthController extends Controller
{
    public function __construct(
        private readonly SamlServiceInterface $saml,
    ) {}

    /**
     * Show the login page with both SAML and local login options.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Redirect the browser to the SimpleSAMLphp SSO initiation URL.
     * SimpleSAMLphp handles the IdP redirect, assertion consumption,
     * and then redirects back to POST /saml/acs.
     */
    public function login(): RedirectResponse
    {
        return redirect()->away($this->saml->getLoginUrl());
    }

    /**
     * Assertion Consumer Service callback (called by SimpleSAMLphp after auth).
     *
     * At this point SimpleSAMLphp has already validated the SAMLResponse and
     * stored the SP attributes in its PHP session. We read those attributes,
     * find or create the corresponding User, and establish a Laravel session.
     */
    public function acs(Request $request): RedirectResponse
    {
        if (!$this->saml->isAuthenticated()) {
            Log::warning('SAML ACS called but SimpleSAMLphp session is not authenticated');
            return redirect()->route('login')
                ->withErrors(['saml' => 'SAML authentication failed. Please try again.']);
        }

        $attributes = $this->saml->getAttributes();
        $user       = $this->findOrCreateUser($attributes);

        $user->last_login_at = now();
        $user->save();

        Auth::login($user, remember: true);

        $request->session()->regenerate();
        session(['login_type' => 'saml']);

        Log::info('SAML login successful', [
            'user_id'  => $user->id,
            'email'    => $user->email,
            'new_user' => $user->wasRecentlyCreated,
        ]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Local email + password login (fallback when IdP is unavailable).
     */
    public function localLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            session(['login_type' => 'local']);
            Auth::user()->update(['last_login_at' => now()]);
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'These credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Log out from both the Laravel session and — for SAML users — the SimpleSAMLphp SP session.
     */
    public function logout(Request $request): RedirectResponse
    {
        $loginType = session('login_type', 'local');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($loginType === 'saml' && class_exists(\SimpleSAML\Auth\Simple::class)) {
            try {
                $ssp = new \SimpleSAML\Auth\Simple(
                    config('simplesamlphp.authsource', 'federation-sp')
                );
                $ssp->logout(route('login'));
            } catch (\Throwable) {
                // SSP unavailable — fall through to redirect below
            }
        }

        return redirect()->route('login')
            ->with('success', 'You have been logged out.');
    }

    /**
     * Find an existing user or create a new one (Guest role) from SAML attributes.
     *
     * Required attributes:
     *   mail                     — real email address; abort 422 if missing
     *   displayName OR givenName+sn — user display name; abort 422 if neither present
     *
     * Recommended:
     *   eduPersonPrincipalName   — stored as saml_id for persistent identity;
     *                              falls back to mail when ePPN not released
     *
     * Lookup order:
     *   1. saml_id match         — user previously linked via SAML
     *   2. email match           — existing local user; link saml_id on first SAML login
     *   3. neither               — create new user with default role
     */
    public function findOrCreateUser(array $attributes): User
    {
        $attrMap = config('simplesamlphp.attributes');

        // mail is required — ePPN is not an email address
        $email = $this->firstAttr($attributes, $attrMap['email']);
        if (! $email) {
            abort(422, 'SAML response did not include the required "mail" attribute.');
        }

        // display name is required — UI always shows a user name
        $displayName = $this->firstAttr($attributes, $attrMap['name'])
            ?? trim(
                ($this->firstAttr($attributes, $attrMap['given_name']) ?? '')
                . ' '
                . ($this->firstAttr($attributes, $attrMap['surname']) ?? '')
            ) ?: null;

        if (! $displayName) {
            abort(422, 'SAML response did not include a display name (displayName, or givenName + sn).');
        }

        // saml_id = ePPN when available, otherwise fall back to mail
        $samlId = $this->firstAttr($attributes, $attrMap['eppn']) ?? $email;

        // 1. look up by persistent saml_id
        $user = User::where('saml_id', $samlId)->first();

        // 2. fall back to email — existing local user logging in via SAML for the first time
        if (! $user) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update(['saml_id' => $samlId]);
                Log::info('SAML identity linked to existing user', ['user_id' => $user->id, 'saml_id' => $samlId]);
            }
        }

        // 3. new user — provision with default role
        if (! $user) {
            $user = User::create([
                'name'     => $displayName,
                'email'    => $email,
                'saml_id'  => $samlId,
                'password' => Hash::make(Str::random(32)),
            ]);

            $defaultRole = SystemPreference::get('default_saml_role', 'Guest');
            $user->assignRole($defaultRole);

            app(\App\Services\Notification\NotificationService::class)->dispatch(
                'user_registered',
                ['user_name' => $user->name, 'user_email' => $user->email]
            );

            Log::info('New SAML user created', ['email' => $email, 'saml_id' => $samlId]);
        }

        return $user;
    }

    /**
     * Extract the first value from a multi-valued SAML attribute array.
     *
     * @param array<string, list<string>> $attributes
     */
    private function firstAttr(array $attributes, string $name): ?string
    {
        $value = $attributes[$name][0] ?? null;

        return ($value !== null && $value !== '') ? $value : null;
    }
}
