<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\NotificationArchive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = AppNotification::where('user_id', Auth::id())
            ->latest('created_at')
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function archive(): View
    {
        $notifications = NotificationArchive::where('user_id', Auth::id())
            ->latest('created_at')
            ->paginate(20);

        return view('notifications.archive', compact('notifications'));
    }

    public function unread(): JsonResponse
    {
        $notifications = AppNotification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->latest('created_at')
            ->take(5)
            ->get(['id', 'type', 'title', 'body', 'action_url', 'created_at']);

        return response()->json($notifications);
    }

    public function markRead(AppNotification $notification): RedirectResponse
    {
        abort_if($notification->user_id !== Auth::id(), 403);

        $notification->update(['read_at' => now()]);

        return redirect()->back();
    }

    public function markAllRead(): RedirectResponse
    {
        AppNotification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }

    public function archiveNotification(AppNotification $notification): RedirectResponse
    {
        abort_if($notification->user_id !== Auth::id(), 403);

        DB::transaction(function () use ($notification) {
            NotificationArchive::create(array_merge($notification->toArray(), [
                'archived_at' => now(),
            ]));
            $notification->delete();
        });

        return redirect()->back()->with('success', 'Notification archived.');
    }
}
