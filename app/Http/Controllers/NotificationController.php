<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $service)
    {
    }

    /**
     * Full list — paginated, with filter chips for read/unread.
     */
    public function index(Request $request)
    {
        $userId = (string) auth()->user()->_id;
        $filter = $request->query('filter', 'all'); // all | unread | read

        $query = Notification::where('user_id', $userId)->orderBy('created_at', 'desc');
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->paginate(25)->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter'        => $filter,
            'unreadCount'   => $this->service->unreadCount($userId),
            'totalCount'    => Notification::where('user_id', $userId)->count(),
        ]);
    }

    public function markRead(string $id, Request $request)
    {
        $userId = (string) auth()->user()->_id;
        $n = Notification::where('user_id', $userId)->find($id);
        if ($n) $n->markRead();

        // If the request expects JSON, respond JSON. Otherwise follow the
        // notification's link.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return $n && $n->link ? redirect($n->link) : back();
    }

    public function markAllRead()
    {
        $this->service->markAllRead((string) auth()->user()->_id);
        return back();
    }
}
