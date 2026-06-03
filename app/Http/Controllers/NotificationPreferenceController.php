<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationType;
use App\Models\UserNotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationPreferenceController extends Controller
{
    public function index(): View
    {
        $types = NotificationType::where('is_active', true)->orderBy('label')->get();
        $prefs = UserNotificationPreference::where('user_id', Auth::id())
            ->get()
            ->keyBy('notification_type');

        return view('profile.notifications', compact('types', 'prefs'));
    }

    public function update(Request $request): RedirectResponse
    {
        $rows = [];

        foreach (NotificationType::where('is_active', true)->pluck('id') as $typeId) {
            $rows[] = [
                'user_id'           => Auth::id(),
                'notification_type' => $typeId,
                'via_ui'            => (bool) $request->boolean("via_ui_{$typeId}"),
                'via_email'         => (bool) $request->boolean("via_email_{$typeId}"),
            ];
        }

        UserNotificationPreference::upsert(
            $rows,
            ['user_id', 'notification_type'],
            ['via_ui', 'via_email']
        );

        return redirect()->back()->with('success', 'Notification preferences saved.');
    }
}
