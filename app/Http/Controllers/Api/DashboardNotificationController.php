<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardNotificationResource;
use App\Services\DashboardNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardNotificationController extends Controller
{
    protected DashboardNotificationService $service;

    public function __construct(DashboardNotificationService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /admin/notifications
     * Returns latest notifications + unread count.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 15);

        return response()->json([
            'unread_count' => $this->service->getUnreadCount(),
            'notifications' => DashboardNotificationResource::collection($this->service->getLatest($limit)),
        ]);
    }

    /**
     * GET /admin/notifications/unread-count
     * Returns only the unread count.
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->service->getUnreadCount(),
        ]);
    }

    /**
     * POST /admin/notifications/{id}/read
     * Marks a single notification as read.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $this->service->markAsRead($id);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * POST /admin/notifications/read-all
     * Marks all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $updated = $this->service->markAllAsRead();

        return response()->json([
            'success' => true,
            'message' => "{$updated} notifications marked as read.",
        ]);
    }
}
