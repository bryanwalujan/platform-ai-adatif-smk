<?php
// app/Http/Controllers/Api/NotificationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected NotificationService $notifService;

    public function __construct(NotificationService $notifService)
    {
        $this->notifService = $notifService;
    }

    /**
     * GET /notifications
     */
    public function index(Request $request)
    {
        // Auto-generate notifikasi dari AI sebelum kirim ke Flutter
        $this->notifService->generateAdaptiveNotifications(
            $request->user()->id
        );

        $notifications = AppNotification::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'message'    => $n->message,
                'is_read'    => $n->is_read,
                'data'       => $n->data,
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $notifications->where('is_read', false)->count(),
        ]);
    }

    /**
     * POST /notifications/{id}/read
     */
    public function markAsRead(Request $request, $id)
    {
        AppNotification::where('user_id', $request->user()->id)
            ->findOrFail($id)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca']);
    }

    /**
     * POST /notifications/read-all
     */
    public function markAllAsRead(Request $request)
    {
        AppNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi sudah dibaca']);
    }
}