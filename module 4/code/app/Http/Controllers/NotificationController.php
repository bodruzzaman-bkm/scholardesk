<?php

namespace App\Http\Controllers;

use App\Models\InAppNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->inAppNotifications()
            ->paginate(25);

        return view('notifications.index', compact('notifications'));
    }

    /** Polled by the bell in the top bar. */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $request->user()->unreadNotificationCount()]);
    }

    /** Mark one read, then continue to wherever it pointed. */
    public function read(Request $request, InAppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $this->notifications->markRead($notification);

        return redirect($notification->link ?: route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return back()->with('success', 'All notifications marked as read.');
    }
}
