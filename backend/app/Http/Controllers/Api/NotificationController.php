<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class NotificationController extends Controller
{
    protected NotificationPreferenceService $preferenceService;

    public function __construct(NotificationPreferenceService $preferenceService)
    {
        $this->preferenceService = $preferenceService;
    }

    /**
     * Daftar notifikasi terpaginasi milik user yang sedang login.
     * GET /api/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 15), 50);

        $notifications = $user->notifications()
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    /**
     * Jumlah notifikasi yang belum dibaca (ringan & cepat).
     * GET /api/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca (dibatasi oleh Policy).
     * PATCH /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = DatabaseNotification::findOrFail($id);

        // Otorisasi: User A dilarang menandai notifikasi milik User B
        Gate::authorize('update', $notification);

        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Notifikasi ditandai sebagai sudah dibaca.',
            'notification' => $notification,
        ]);
    }

    /**
     * Tandai SEMUA notifikasi milik user sebagai sudah dibaca.
     * POST /api/notifications/mark-all-read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Semua notifikasi berhasil ditandai sebagai sudah dibaca.',
        ]);
    }

    /**
     * Mengambil daftar preferensi notifikasi user beserta metadata kritis.
     * GET /api/notification-preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $preferences = $this->preferenceService->getPreferences($request->user());

        return response()->json([
            'preferences' => $preferences,
        ]);
    }

    /**
     * Memperbarui preferensi notifikasi user.
     * PUT /api/notification-preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*' => 'boolean',
        ]);

        try {
            $this->preferenceService->updatePreferences($request->user(), $validated['preferences']);

            return response()->json([
                'message' => 'Preferensi notifikasi berhasil disimpan.',
                'preferences' => $this->preferenceService->getPreferences($request->user()),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
