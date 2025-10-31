<?php

namespace App\Http\Controllers;

use App\Models\NotificationRecipient;
use Illuminate\Http\Request;

class NotificationRecipientController extends Controller
{
    /**
     * Lấy danh sách thông báo chưa đọc (cho Student)
     * GET /api/student/unread-notifications
     */
    public function index(Request $request)
    {
        $role = $request->current_role;
        $userId = $request->current_user_id;

        if ($role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ sinh viên mới có thể xem thông báo chưa đọc'
            ], 403);
        }

        $unreadNotifications = NotificationRecipient::where('student_id', $userId)
            ->where('is_read', false)
            ->with(['notification.advisor', 'notification.attachments'])
            ->orderBy('notification_id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $unreadNotifications
        ]);
    }

    /**
     * Đánh dấu tất cả thông báo là đã đọc (cho Student)
     * POST /api/student/mark-all-notifications-read
     */
    public function markAllAsRead(Request $request)
    {
        $role = $request->current_role;
        $userId = $request->current_user_id;

        if ($role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ sinh viên mới có thể đánh dấu đã đọc'
            ], 403);
        }

        NotificationRecipient::where('student_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã đánh dấu tất cả thông báo là đã đọc'
        ]);
    }
}