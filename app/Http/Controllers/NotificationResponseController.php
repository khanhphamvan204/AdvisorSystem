<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationResponse;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class NotificationResponseController extends Controller
{
    /**
     * Sinh viên tạo phản hồi thông báo
     * Route: POST /api/notifications/{notificationId}/responses
     */
    public function store(Request $request, $notificationId)
    {
        $user = JWTAuth::user();

        if ($user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ sinh viên mới có quyền phản hồi thông báo'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:10|max:5000'
        ], [
            'content.required' => 'Nội dung phản hồi là bắt buộc',
            'content.min' => 'Nội dung phản hồi phải có ít nhất 10 ký tự',
            'content.max' => 'Nội dung phản hồi không được vượt quá 5000 ký tự'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Kiểm tra thông báo có tồn tại không
        $notification = Notification::find($notificationId);
        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông báo'
            ], 404);
        }

        // Kiểm tra sinh viên có quyền truy cập thông báo này không
        $student = Student::where('user_id', $user->user_id)->first();
        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin sinh viên'
            ], 404);
        }

        $hasAccess = $notification->classes->contains('class_id', $student->class_id);

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền phản hồi thông báo này'
            ], 403);
        }

        // Kiểm tra đã phản hồi chưa
        $existingResponse = NotificationResponse::where('notification_id', $notificationId)
            ->where('student_id', $user->user_id)
            ->first();

        if ($existingResponse) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã phản hồi thông báo này rồi'
            ], 400);
        }

        $response = NotificationResponse::create([
            'notification_id' => $notificationId,
            'student_id' => $user->user_id,
            'content' => $request->content,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gửi phản hồi thành công',
            'data' => $response->load('student.user')
        ], 201);
    }

    /**
     * Cố vấn xem danh sách phản hồi của một thông báo
     * Route: GET /api/notifications/{notificationId}/responses
     */
    public function index(Request $request, $notificationId)
    {
        $user = JWTAuth::user();

        if ($user->role !== 'advisor') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ cố vấn học tập mới có quyền xem phản hồi'
            ], 403);
        }

        $notification = Notification::find($notificationId);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông báo'
            ], 404);
        }

        if ($notification->advisor_id !== $user->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền xem phản hồi của thông báo này'
            ], 403);
        }

        // Lọc theo status nếu có
        $query = NotificationResponse::where('notification_id', $notificationId)
            ->with(['student.user', 'student.class', 'advisorUser']); // Giả sử Model có các quan hệ này

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $responses = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $responses
        ]);
    }

    /**
     * Cố vấn trả lời/cập nhật phản hồi của sinh viên
     * Route: PUT /api/notification-responses/{responseId}
     */
    public function update(Request $request, $responseId)
    {
        $user = JWTAuth::user();

        if ($user->role !== 'advisor') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ cố vấn học tập mới có quyền trả lời phản hồi'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'advisor_response' => 'required|string|min:10|max:5000',
            'status' => 'sometimes|in:pending,resolved'
        ], [
            'advisor_response.required' => 'Nội dung trả lời là bắt buộc',
            'advisor_response.min' => 'Nội dung trả lời phải có ít nhất 10 ký tự',
            'advisor_response.max' => 'Nội dung trả lời không được vượt quá 5000 ký tự',
            'status.in' => 'Trạng thái không hợp lệ'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $response = NotificationResponse::find($responseId);

        if (!$response) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy phản hồi'
            ], 404);
        }

        // Kiểm tra quyền (phải là CVHT của thông báo đó)
        if ($response->notification->advisor_id !== $user->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền trả lời phản hồi này'
            ], 403);
        }

        $response->update([
            'advisor_response' => $request->advisor_response,
            'advisor_id' => $user->user_id,
            'response_at' => now(),
            'status' => $request->status ?? $response->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trả lời phản hồi thành công',
            'data' => $response->load(['student.user', 'advisorUser'])
        ]);
    }

}