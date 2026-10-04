<?php

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id');

        if ((bool) $request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $notifications = $query->get();

        return ApiResponse::success(
            NotificationResource::collection($notifications)->resolve()
        );
    }

    public function markAsRead(Request $request, int $notification): JsonResponse
    {
        $item = Notification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($notification);

        if (! $item->is_read) {
            $item->markAsRead();
        }

        return ApiResponse::success([], 'Notifikasi telah dibaca');
    }

    public function readAll(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return ApiResponse::success([], 'Semua notifikasi telah dibaca');
    }
}
