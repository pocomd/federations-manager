<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;

/**
 * UserController
 *
 * Manages federation operator accounts.
 * Users are created automatically via SAML2 login — no create/store here.
 * Only Admin-level users may change roles or suspend accounts.
 *
 * Permissions used: user.view, user.edit
 */
class UserController extends Controller
{
    private const ALLOWED_ROLES = ['Admin', 'Federation Manager', 'Entity Manager', 'Guest'];

    public function index(Request $request): View
    {
        Gate::authorize('user.view');

        $query = User::with('roles')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($role = $request->input('role')) {
            $query->role($role);
        }

        $users = $query->paginate(25)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function show(User $user): View
    {
        Gate::authorize('user.view');

        $user->load('roles', 'auditLogs');

        $recentLogs = $user->auditLogs()
            ->with('entity')
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('users.show', compact('user', 'recentLogs'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('user.edit');

        $user->load('roles');

        return view('users.edit', [
            'user'          => $user,
            'allowedRoles'  => self::ALLOWED_ROLES,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('user.edit');

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($validated);

        return redirect()->route('users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    /**
     * Assign a single Spatie role to the user.
     *
     * Double-guarded: requires the user.edit gate AND the Admin role — the gate alone is insufficient.
     * Syncs both the Spatie roles table and the plain users.role column.
     *
     * @authorizes  user.edit gate + Admin role check
     * @notify      AppNotification::user_role_changed (to the affected user)
     */
    public function changeRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('user.edit');

        // Additional Admin-only guard — only Admin may assign roles
        if (!Auth::user()?->hasRole('Admin')) {
            abort(403, 'Only admins may change roles.');
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in(self::ALLOWED_ROLES)],
        ]);

        $oldRole = $user->roles->first()?->name ?? 'Guest';

        $user->syncRoles([$validated['role']]);

        // Keep the plain role column in sync
        $user->update(['role' => strtolower($validated['role'])]);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'user_role_changed',
            'old_values' => ['role' => $oldRole, 'target_user_id' => $user->id],
            'new_values' => ['role' => $validated['role'], 'target_user_id' => $user->id],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        AppNotification::create([
            'user_id'      => $user->id,
            'type'         => 'user_role_changed',
            'title'        => 'Your role has been changed',
            'body'         => "Your account role has been updated from {$oldRole} to {$validated['role']} by an administrator.",
            'subject_type' => 'user',
            'subject_id'   => $user->id,
            'action_url'   => route('dashboard'),
        ]);

        return redirect()->route('users.show', $user)
            ->with('success', "Role changed to {$validated['role']}.");
    }

    /**
     * Toggle user status between active and suspended. Prevents self-suspension.
     *
     * The same method handles both directions — new status is the opposite of current.
     * A suspended user's login is blocked in the EnsureAuthenticated middleware.
     *
     * @notify  AppNotification::user_suspended or user_reinstated (to the affected user)
     */
    public function suspend(User $user): RedirectResponse
    {
        Gate::authorize('user.edit');

        // Prevent self-suspension
        if ($user->id === Auth::id()) {
            return redirect()->route('users.show', $user)
                ->with('error', 'You cannot suspend your own account.');
        }

        $oldStatus = $user->status;
        $newStatus = $oldStatus === 'suspended' ? 'active' : 'suspended';
        $user->update(['status' => $newStatus]);

        $label  = $newStatus === 'suspended' ? 'suspended' : 'reinstated';
        $action = $newStatus === 'suspended' ? 'user_suspended' : 'user_reinstated';

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => $action,
            'old_values' => ['status' => $oldStatus, 'target_user_id' => $user->id],
            'new_values' => ['status' => $newStatus, 'target_user_id' => $user->id],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        if ($newStatus === 'suspended') {
            AppNotification::create([
                'user_id'      => $user->id,
                'type'         => 'user_suspended',
                'title'        => 'Your account has been suspended',
                'body'         => 'Your account has been suspended by an administrator. You will not be able to log in until the account is reinstated. Contact your administrator for further information.',
                'subject_type' => 'user',
                'subject_id'   => $user->id,
                'action_url'   => null,
            ]);
        } else {
            AppNotification::create([
                'user_id'      => $user->id,
                'type'         => 'user_reinstated',
                'title'        => 'Your account has been reinstated',
                'body'         => 'Your account has been reinstated and you can now log in again.',
                'subject_type' => 'user',
                'subject_id'   => $user->id,
                'action_url'   => route('dashboard'),
            ]);
        }

        return redirect()->route('users.show', $user)
            ->with('success', "User account {$label}.");
    }
}
