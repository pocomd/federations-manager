<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\InvalidInvitationException;
use App\Http\Controllers\Controller;
use App\Models\EntityManager;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Auth\InvitationService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class InvitationRegistrationController extends Controller
{
    /**
     * Show the registration form for an invitation token.
     *
     * Renders an error view (not a redirect) if the token is invalid or expired.
     *
     * @authorizes  none (public endpoint, guarded by token validity)
     */
    public function show(string $token): View
    {
        try {
            $invitation = app(InvitationService::class)->validateToken($token);
        } catch (InvalidInvitationException $e) {
            $invitation = Invitation::where('token', $token)->with(['federation', 'invitedBy'])->first();
            return view('auth.invitation-error', [
                'reason'     => $e->getMessage(),
                'invitation' => $invitation,
            ]);
        }

        return view('auth.register', compact('invitation'));
    }

    /**
     * Create a new user account from an invitation link within a DB transaction.
     *
     * If the invitation is for a specific entity: assigns Entity Manager role and creates EntityManager record.
     * If federation-only: assigns Guest role.
     * Marks the invitation as accepted and logs the user in immediately.
     *
     * @authorizes  none (public endpoint, guarded by token validity)
     * @dispatches  NotificationService::user_registered
     */
    public function register(Request $request, string $token): RedirectResponse|View
    {
        try {
            $invitation = app(InvitationService::class)->validateToken($token);
        } catch (InvalidInvitationException $e) {
            $invitation = Invitation::where('token', $token)->with(['federation', 'invitedBy'])->first();
            return view('auth.invitation-error', [
                'reason'     => $e->getMessage(),
                'invitation' => $invitation,
            ]);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        DB::transaction(function () use ($invitation, $validated) {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $invitation->email,
                'password' => Hash::make($validated['password']),
            ]);

            if ($invitation->entity_id) {
                $user->assignRole('Entity Manager');

                EntityManager::create([
                    'entity_id' => $invitation->entity_id,
                    'user_id'   => $user->id,
                    'role'      => 'manager',
                    'added_by'  => $invitation->invited_by,
                    'added_at'  => now(),
                ]);
            } else {
                $user->assignRole('Guest');
            }

            $invitation->update(['accepted_at' => now()]);

            app(NotificationService::class)->dispatch('user_registered', [
                'user_name'  => $user->name,
                'user_email' => $user->email,
            ]);

            Auth::login($user);
        });

        return redirect()->route('dashboard')
            ->with('success', 'Welcome! Your account has been created.');
    }
}
