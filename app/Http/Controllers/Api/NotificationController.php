<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /** GET /notifications  — paginated list for the authenticated user */
    public function index(Request $request)
    {
        $user     = Auth::user();
        $perPage  = min((int) $request->input('per_page', 20), 50);
        $unread   = $request->boolean('unread'); // ?unread=true → only unread

        $query = $user->notifications();
        if ($unread) $query->whereNull('read_at');

        $paginated   = $query->orderByDesc('created_at')->paginate($perPage);
        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'success'      => true,
            'data'         => $paginated->items(),
            'unread_count' => $unreadCount,
            'meta'         => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    /** POST /notifications/{id}/read */
    public function markRead(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /** POST /notifications/read-all */
    public function markAllRead()
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    /** DELETE /notifications/{id} */
    public function destroy(string $id)
    {
        Auth::user()->notifications()->findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    /** DELETE /notifications  — clear all */
    public function destroyAll()
    {
        Auth::user()->notifications()->delete();

        return response()->json(['success' => true]);
    }
}