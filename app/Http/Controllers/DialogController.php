<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Student;
use App\Models\Advisor;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DialogController extends Controller
{
    /**
     * Lấy danh sách cuộc hội thoại
     * - Student: Xem hội thoại với cố vấn của mình
     * - Advisor: Xem hội thoại với sinh viên trong lớp mình phụ trách
     */
    public function getConversations(Request $request)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            if ($role === 'student') {
                // Lấy cố vấn của lớp sinh viên
                $student = Student::with('class.advisor')->find($userId);

                if (!$student || !$student->class || !$student->class->advisor) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Lớp của bạn chưa có cố vấn'
                    ], 404);
                }

                $advisor = $student->class->advisor;

                // Lấy tin nhắn cuối cùng
                $lastMessage = Message::where('student_id', $userId)
                    ->where('advisor_id', $advisor->advisor_id)
                    ->orderBy('sent_at', 'desc')
                    ->first();

                $conversations = [
                    [
                        'conversation_id' => $advisor->advisor_id,
                        'partner_id' => $advisor->advisor_id,
                        'partner_name' => $advisor->full_name,
                        'partner_avatar' => $advisor->avatar_url,
                        'partner_type' => 'advisor',
                        'last_message' => $lastMessage ? $lastMessage->content : null,
                        'last_message_time' => $lastMessage ? $lastMessage->sent_at : null,
                        'unread_count' => Message::where('student_id', $userId)
                            ->where('advisor_id', $advisor->advisor_id)
                            ->where('sender_type', 'advisor')
                            ->where('is_read', false)
                            ->count()
                    ]
                ];

                return response()->json([
                    'success' => true,
                    'data' => $conversations,
                    'message' => 'Lấy danh sách hội thoại thành công'
                ], 200);

            } elseif ($role === 'advisor') {
                // Lấy danh sách sinh viên trong các lớp mà advisor phụ trách
                $classes = ClassModel::where('advisor_id', $userId)
                    ->with('students')
                    ->get();

                $conversations = [];

                foreach ($classes as $class) {
                    foreach ($class->students as $student) {
                        // Lấy tin nhắn cuối cùng
                        $lastMessage = Message::where('student_id', $student->student_id)
                            ->where('advisor_id', $userId)
                            ->orderBy('sent_at', 'desc')
                            ->first();

                        // Đếm tin nhắn chưa đọc
                        $unreadCount = Message::where('student_id', $student->student_id)
                            ->where('advisor_id', $userId)
                            ->where('sender_type', 'student')
                            ->where('is_read', false)
                            ->count();

                        $conversations[] = [
                            'conversation_id' => $student->student_id,
                            'partner_id' => $student->student_id,
                            'partner_code' => $student->user_code,
                            'partner_name' => $student->full_name,
                            'partner_avatar' => $student->avatar_url,
                            'partner_type' => 'student',
                            'class_name' => $class->class_name,
                            'last_message' => $lastMessage ? $lastMessage->content : null,
                            'last_message_time' => $lastMessage ? $lastMessage->sent_at : null,
                            'unread_count' => $unreadCount
                        ];
                    }
                }

                // Sắp xếp theo thời gian tin nhắn cuối cùng
                usort($conversations, function ($a, $b) {
                    if (!$a['last_message_time'])
                        return 1;
                    if (!$b['last_message_time'])
                        return -1;
                    return $b['last_message_time'] <=> $a['last_message_time'];
                });

                return response()->json([
                    'success' => true,
                    'data' => $conversations,
                    'message' => 'Lấy danh sách hội thoại thành công'
                ], 200);

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Vai trò không hợp lệ'
                ], 403);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy lịch sử tin nhắn của một cuộc hội thoại
     * Student gửi: partner_id = advisor_id
     * Advisor gửi: partner_id = student_id
     */
    public function getMessages(Request $request)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $validator = Validator::make($request->all(), [
                'partner_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $partnerId = $request->partner_id;

            if ($role === 'student') {
                // Partner là advisor
                $advisorId = $partnerId;
                $studentId = $userId;

                // Kiểm tra advisor có phải là cố vấn của lớp không
                $student = Student::with('class')->find($userId);
                if (!$student || !$student->class || $student->class->advisor_id != $advisorId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể xem tin nhắn với cố vấn của lớp mình'
                    ], 403);
                }

            } elseif ($role === 'advisor') {
                // Partner là student
                $studentId = $partnerId;
                $advisorId = $userId;

                // Kiểm tra student có thuộc lớp mình phụ trách không
                $student = Student::with('class')->find($studentId);
                if (!$student || !$student->class || $student->class->advisor_id != $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể xem tin nhắn với sinh viên trong lớp mình phụ trách'
                    ], 403);
                }

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Vai trò không hợp lệ'
                ], 403);
            }

            // Lấy tin nhắn
            $messages = Message::where('student_id', $studentId)
                ->where('advisor_id', $advisorId)
                ->orderBy('sent_at', 'asc')
                ->get();

            // Debug: Kiểm tra TẤT CẢ tin nhắn
            $allMessages = Message::where('student_id', $studentId)
                ->where('advisor_id', $advisorId)
                ->get();

            // Đánh dấu tin nhắn đã đọc
            if ($role === 'student') {
                // Debug: Kiểm tra tin nhắn chưa đọc
                $unreadMessages = Message::where('student_id', $studentId)
                    ->where('advisor_id', $advisorId)
                    ->where('sender_type', 'advisor')
                    ->where('is_read', 0)
                    ->get();

                $updated = Message::where('student_id', $studentId)
                    ->where('sender_type', 'advisor')
                    ->where('is_read', 0)
                    ->update(['is_read' => 1]);
            } elseif ($role === 'advisor') {
                // Debug: Kiểm tra tin nhắn chưa đọc
                $unreadMessages = Message::where('student_id', $studentId)
                    ->where('advisor_id', $advisorId)
                    ->where('sender_type', 'student')
                    ->where('is_read', 0)
                    ->get();

                $updated = Message::where('student_id', $studentId)
                    ->where('advisor_id', $advisorId)
                    ->where('sender_type', 'student')
                    ->where('is_read', 0)
                    ->update(['is_read' => 1]);
            }

            return response()->json([
                'success' => true,
                'data' => $messages,
                'message' => 'Lấy tin nhắn thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gửi tin nhắn
     * Student gửi: partner_id = advisor_id
     * Advisor gửi: partner_id = student_id
     */
    public function sendMessage(Request $request)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $validator = Validator::make($request->all(), [
                'partner_id' => 'required|integer',
                'content' => 'required|string',
                'attachment_path' => 'nullable|string|max:255'
            ], [
                'partner_id.required' => 'Cần chọn người nhận tin nhắn',
                'content.required' => 'Nội dung tin nhắn không được để trống'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $partnerId = $request->partner_id;

            // Xác định người gửi và người nhận
            if ($role === 'student') {
                $studentId = $userId;
                $advisorId = $partnerId;
                $senderType = 'student';

                // Kiểm tra advisor có phải là cố vấn của lớp không
                $student = Student::with('class')->find($userId);
                if (!$student || !$student->class || $student->class->advisor_id != $advisorId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể nhắn tin với cố vấn của lớp mình'
                    ], 403);
                }

            } elseif ($role === 'advisor') {
                $studentId = $partnerId;
                $advisorId = $userId;
                $senderType = 'advisor';

                // Kiểm tra student có thuộc lớp mình phụ trách không
                $student = Student::with('class')->find($studentId);
                if (!$student || !$student->class || $student->class->advisor_id != $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể nhắn tin với sinh viên trong lớp mình phụ trách'
                    ], 403);
                }

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Vai trò không hợp lệ'
                ], 403);
            }

            // Tạo tin nhắn
            $message = Message::create([
                'student_id' => $studentId,
                'advisor_id' => $advisorId,
                'sender_type' => $senderType,
                'content' => $request->input('content'),
                'attachment_path' => $request->input('attachment_path'),
                'is_read' => false
            ]);

            return response()->json([
                'success' => true,
                'data' => $message,
                'message' => 'Gửi tin nhắn thành công'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Đánh dấu tin nhắn đã đọc
     */
    public function markAsRead(Request $request, $messageId)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $message = Message::find($messageId);

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy tin nhắn'
                ], 404);
            }

            // Kiểm tra quyền
            if ($role === 'student') {
                if ($message->student_id != $userId || $message->sender_type != 'advisor') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền đánh dấu tin nhắn này'
                    ], 403);
                }
            } elseif ($role === 'advisor') {
                if ($message->advisor_id != $userId || $message->sender_type != 'student') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền đánh dấu tin nhắn này'
                    ], 403);
                }
            }

            $message->is_read = true;
            $message->save();

            return response()->json([
                'success' => true,
                'message' => 'Đánh dấu đã đọc thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa tin nhắn
     */
    public function deleteMessage(Request $request, $messageId)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $message = Message::find($messageId);

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy tin nhắn'
                ], 404);
            }

            // Kiểm tra quyền: chỉ người gửi mới có thể xóa
            if ($role === 'student') {
                if ($message->student_id != $userId || $message->sender_type != 'student') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể xóa tin nhắn do mình gửi'
                    ], 403);
                }
            } elseif ($role === 'advisor') {
                if ($message->advisor_id != $userId || $message->sender_type != 'advisor') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể xóa tin nhắn do mình gửi'
                    ], 403);
                }
            }

            $message->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa tin nhắn thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy số lượng tin nhắn chưa đọc
     */
    public function getUnreadCount(Request $request)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            if ($role === 'student') {
                $student = Student::with('class')->find($userId);

                if (!$student || !$student->class || !$student->class->advisor) {
                    return response()->json([
                        'success' => true,
                        'data' => ['unread_count' => 0],
                        'message' => 'Lớp chưa có cố vấn'
                    ], 200);
                }

                $unreadCount = Message::where('student_id', $userId)
                    ->where('advisor_id', $student->class->advisor_id)
                    ->where('sender_type', 'advisor')
                    ->where('is_read', false)
                    ->count();

            } elseif ($role === 'advisor') {
                $unreadCount = Message::where('advisor_id', $userId)
                    ->where('sender_type', 'student')
                    ->where('is_read', false)
                    ->count();

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Vai trò không hợp lệ'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => ['unread_count' => $unreadCount],
                'message' => 'Lấy số tin nhắn chưa đọc thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tìm kiếm tin nhắn trong hội thoại
     */
    public function searchMessages(Request $request)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $validator = Validator::make($request->all(), [
                'partner_id' => 'required|integer',
                'keyword' => 'required|string|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $partnerId = $request->partner_id;

            if ($role === 'student') {
                $studentId = $userId;
                $advisorId = $partnerId;

                // Kiểm tra quyền
                $student = Student::with('class')->find($userId);
                if (!$student || !$student->class || $student->class->advisor_id != $advisorId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể tìm kiếm tin nhắn với cố vấn của lớp mình'
                    ], 403);
                }

            } elseif ($role === 'advisor') {
                $studentId = $partnerId;
                $advisorId = $userId;

                // Kiểm tra quyền
                $student = Student::with('class')->find($studentId);
                if (!$student || !$student->class || $student->class->advisor_id != $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể tìm kiếm tin nhắn với sinh viên trong lớp mình phụ trách'
                    ], 403);
                }

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Vai trò không hợp lệ'
                ], 403);
            }

            $messages = Message::where('student_id', $studentId)
                ->where('advisor_id', $advisorId)
                ->where('content', 'like', '%' . $request->input('keyword') . '%')
                ->orderBy('sent_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $messages,
                'message' => 'Tìm kiếm thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }
}