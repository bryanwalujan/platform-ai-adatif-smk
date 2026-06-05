<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /notifications
     * Menggantikan data hardcoded di NotificationScreen Flutter.
     */
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,       // 'recommendation' | 'feedback' | 'reminder'
                'title'      => $n->title,
                'message'    => $n->message,
                'is_read'    => (bool) $n->is_read,
                'created_at' => $n->created_at?->diffForHumans(), // "2 jam lalu"
            ]);

        return response()->json([
            'notifications'  => $notifications,
            'unread_count'   => $notifications->where('is_read', false)->count(),
        ]);
    }

    /**
     * POST /notifications/{id}/read
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca']);
    }

    /**
     * POST /notifications/read-all
     * Tandai semua notifikasi sebagai sudah dibaca.
     */
    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi sudah dibaca']);
    }
}