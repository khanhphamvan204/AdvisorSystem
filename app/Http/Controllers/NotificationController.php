<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\NotificationResponse;
use App\Models\NotificationAttachment;
use App\Models\Student;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\Rule;
use App\Services\EmailService;

class NotificationController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }
    /**
     * Lấy danh sách thông báo
     * GET /api/notifications
     */
    public function index(Request $request)
    {
        $role = $request->current_role;
        $userId = $request->current_user_id;

        if ($role === 'advisor') {
            // CVHT xem các thông báo mình đã tạo
            $notifications = Notification::where('advisor_id', $userId)
                ->with(['classes', 'attachments'])
                ->withCount('responses')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $notifications]);
        }

        if ($role === 'student') {
            // Sinh viên xem thông báo của lớp mình
            $student = Student::find($userId);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin sinh viên'
                ], 404);
            }

            $notifications = Notification::whereHas('classes', function ($query) use ($student) {
                $query->where('classes.class_id', $student->class_id);
            })
                ->with(['advisor', 'attachments'])
                ->withCount([
                    'responses' => function ($query) use ($userId) {
                        $query->where('student_id', $userId);
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // Lấy trạng thái đã đọc
            $notificationIds = $notifications->pluck('notification_id');
            $readStatus = NotificationRecipient::where('student_id', $userId)
                ->whereIn('notification_id', $notificationIds)
                ->pluck('is_read', 'notification_id');

            $notifications->transform(function ($notification) use ($readStatus) {
                $notification->is_read = $readStatus->get($notification->notification_id, false);
                return $notification;
            });

            return response()->json(['success' => true, 'data' => $notifications]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Không có quyền truy cập'
        ], 403);
    }

    /**
     * Tạo thông báo mới (chỉ Advisor)
     * POST /api/notifications
     */
    public function store(Request $request)
    {
        $role = $request->current_role;
        $userId = $request->current_user_id;

        if ($role !== 'advisor') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ cố vấn học tập mới có quyền tạo thông báo'
            ], 403);
        }

        // Lấy các lớp mà CVHT này quản lý
        $managedClassIds = ClassModel::where('advisor_id', $userId)
            ->pluck('class_id')
            ->all();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'summary' => 'required|string',
            'link' => 'nullable|url|max:2083',
            'type' => 'required|string|max:50',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => [
                'required',
                'integer',
                'exists:Classes,class_id',
                Rule::in($managedClassIds)
            ],
            'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240'
        ], [
            'title.required' => 'Tiêu đề thông báo là bắt buộc',
            'summary.required' => 'Nội dung thông báo là bắt buộc',
            'class_ids.required' => 'Phải chọn ít nhất một lớp',
            'class_ids.*.in' => 'Bạn chỉ có thể gửi thông báo cho các lớp mình quản lý'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Tạo thông báo
            $notification = Notification::create([
                'advisor_id' => $userId,
                'title' => $request->title,
                'summary' => $request->summary,
                'link' => $request->link,
                'type' => $request->type
            ]);

            // Gắn các lớp
            $notification->classes()->attach($request->class_ids);

            // Xử lý file đính kèm
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('notification_attachments', 'public');
                    NotificationAttachment::create([
                        'notification_id' => $notification->notification_id,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName()
                    ]);
                }
            }

            // Tạo bản ghi NotificationRecipient cho từng sinh viên trong các lớp
            $students = Student::whereIn('class_id', $request->class_ids)->get();

            $recipients = [];
            foreach ($students as $student) {
                $recipients[] = [
                    'notification_id' => $notification->notification_id,
                    'student_id' => $student->student_id,
                    'is_read' => false,
                    'read_at' => null
                ];
            }

            if (!empty($recipients)) {
                NotificationRecipient::insert($recipients);
            }

            // // Gửi email bất đồng bộ qua Queue (không chờ đợi)
            // // Điều này giúp API response nhanh hơn rất nhiều
            $this->emailService->queueBulkNotificationEmails($students, $notification);


            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo thông báo thành công',
                'data' => $notification->load(['classes', 'attachments'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem chi tiết thông báo
     * GET /api/notifications/{id}
     */
    public function show(Request $request, $id)
    {
        $role = $request->current_role;
        $userId = $request->current_user_id;

        $notification = Notification::with(['advisor', 'classes', 'attachments'])->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông báo'
            ], 404);
        }

        if ($role === 'advisor') {
            // CVHT chỉ xem được thông báo mình tạo
            if ($notification->advisor_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có quyền xem thông báo này'
                ], 403);
            }

            $notification->load(['responses.student']);
            $notification->total_recipients = NotificationRecipient::where('notification_id', $id)->count();
            $notification->total_read = NotificationRecipient::where('notification_id', $id)
                ->where('is_read', true)
                ->count();
            $notification->total_responses = $notification->responses->count();
        } elseif ($role === 'student') {
            // Sinh viên chỉ xem được thông báo của lớp mình
            $student = Student::find($userId);

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
                    'message' => 'Không có quyền xem thông báo này'
                ], 403);
            }

            // Đánh dấu đã đọc
            NotificationRecipient::updateOrCreate(
                ['notification_id' => $id, 'student_id' => $userId],
                ['is_read' => true, 'read_at' => now()]
            );

            // Lấy phản hồi của sinh viên này
            $notification->my_response = NotificationResponse::where('notification_id', $id)
                ->where('student_id', $userId)
                ->with('advisor')
                ->first();
        }

        return response()->json(['success' => true, 'data' => $notification]);
    }

    /**
     * Cập nhật thông báo (chỉ Advisor)
     * PUT /api/notifications/{id}
     */
    public function update(Request $request, $id)
    {
        $role = $request->current_role;
        $userId = $request->current_user_id;

        if ($role !== 'advisor') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ cố vấn học tập mới có quyền cập nhật thông báo'
            ], 403);
        }

        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông báo'
            ], 404);
        }

        if ($notification->advisor_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền cập nhật thông báo này'
            ], 403);
        }

        $managedClassIds = ClassModel::where('advisor_id', $userId)
            ->pluck('class_id')
            ->all();

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'summary' => 'sometimes|required|string',
            'link' => 'nullable|url|max:2083',
            'type' => 'sometimes|required|string|max:50',
            'class_ids' => 'sometimes|required|array|min:1',
            'class_ids.*' => [
                'required',
                'integer',
                'exists:Classes,class_id',
                Rule::in($managedClassIds)
            ],
            'attachments_to_add' => 'sometimes|array',
            'attachments_to_add.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
            'attachment_ids_to_delete' => 'sometimes|array',
            'attachment_ids_to_delete.*' => [
                'required',
                'integer',
                Rule::exists('Notification_Attachments', 'attachment_id')
                    ->where('notification_id', $id)
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Cập nhật thông tin cơ bản
            $updateData = $request->only(['title', 'summary', 'link', 'type']);
            if (!empty($updateData)) {
                $notification->update($updateData);
            }

            // Cập nhật danh sách lớp
            if ($request->has('class_ids')) {
                $notification->classes()->sync($request->class_ids);

                // Xóa và tạo lại NotificationRecipient
                NotificationRecipient::where('notification_id', $id)->delete();

                $studentIds = Student::whereIn('class_id', $request->class_ids)
                    ->pluck('student_id');

                $recipients = [];
                foreach ($studentIds as $studentId) {
                    $recipients[] = [
                        'notification_id' => $notification->notification_id,
                        'student_id' => $studentId,
                        'is_read' => false,
                        'read_at' => null
                    ];
                }

                if (!empty($recipients)) {
                    NotificationRecipient::insert($recipients);
                }
            }

            // Xóa file đính kèm
            if ($request->has('attachment_ids_to_delete')) {
                $filesToDelete = NotificationAttachment::whereIn(
                    'attachment_id',
                    $request->attachment_ids_to_delete
                )->get();

                foreach ($filesToDelete as $file) {
                    Storage::disk('public')->delete($file->file_path);
                }

                NotificationAttachment::whereIn(
                    'attachment_id',
                    $request->attachment_ids_to_delete
                )->delete();
            }

            // Thêm file đính kèm mới
            if ($request->hasFile('attachments_to_add')) {
                foreach ($request->file('attachments_to_add') as $file) {
                    $path = $file->store('notification_attachments', 'public');
                    NotificationAttachment::create([
                        'notification_id' => $notification->notification_id,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName()
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thông báo thành công',
                'data' => $notification->load(['classes', 'attachments'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa thông báo (chỉ Advisor)
     * DELETE /api/notifications/{id}
     */
    public function destroy(Request $request, $id)
    {
        $role = $request->current_role;
        $userId = $request->current_user_id;

        if ($role !== 'advisor') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ cố vấn học tập mới có quyền xóa thông báo'
            ], 403);
        }

        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông báo'
            ], 404);
        }

        if ($notification->advisor_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền xóa thông báo này'
            ], 403);
        }

        // Xóa file đính kèm
        foreach ($notification->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa thông báo thành công'
        ]);
    }

    /**
     * Thống kê thông báo (chỉ Advisor)
     * GET /api/notifications/statistics
     */
    public function statistics(Request $request)
    {
        $role = $request->current_role;
        $userId = $request->current_user_id;

        if ($role !== 'advisor') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ cố vấn học tập mới có quyền xem thống kê'
            ], 403);
        }

        $totalNotifications = Notification::where('advisor_id', $userId)->count();

        $totalRecipients = NotificationRecipient::whereHas('notification', function ($query) use ($userId) {
            $query->where('advisor_id', $userId);
        })->count();

        $totalRead = NotificationRecipient::whereHas('notification', function ($query) use ($userId) {
            $query->where('advisor_id', $userId);
        })->where('is_read', true)->count();

        $totalResponses = NotificationResponse::whereHas('notification', function ($query) use ($userId) {
            $query->where('advisor_id', $userId);
        })->count();

        $pendingResponses = NotificationResponse::whereHas('notification', function ($query) use ($userId) {
            $query->where('advisor_id', $userId);
        })->where('status', 'pending')->count();

        $byType = Notification::where('advisor_id', $userId)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_notifications' => $totalNotifications,
                'total_recipients' => $totalRecipients,
                'total_read' => $totalRead,
                'read_percentage' => $totalRecipients > 0 ? round(($totalRead / $totalRecipients) * 100, 2) : 0,
                'total_responses' => $totalResponses,
                'pending_responses' => $pendingResponses,
                'by_type' => $byType
            ]
        ]);
    }

    /**
     * Lấy thống kê số sinh viên đã đọc/chưa đọc một thông báo cụ thể (chỉ Advisor)
     * GET /api/notifications/{id}/read-statistics
     */
    public function getReadStatistics(Request $request, $id)
    {
        $role = $request->current_role;
        $userId = $request->current_user_id;

        if ($role !== 'advisor') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ cố vấn học tập mới có quyền xem thống kê'
            ], 403);
        }

        // Kiểm tra thông báo có tồn tại không
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông báo'
            ], 404);
        }

        // Kiểm tra quyền truy cập (chỉ xem được thông báo mình tạo)
        if ($notification->advisor_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền xem thống kê thông báo này'
            ], 403);
        }

        // Lấy tất cả recipient của thông báo này
        $recipients = NotificationRecipient::where('notification_id', $id)->get();

        // Phân loại sinh viên đã đọc và chưa đọc
        $readRecipients = $recipients->where('is_read', true);
        $unreadRecipients = $recipients->where('is_read', false);

        // Lấy thông tin sinh viên đã đọc
        $readStudentIds = $readRecipients->pluck('student_id')->toArray();
        $readStudents = Student::whereIn('student_id', $readStudentIds)
            ->select('student_id', 'full_name', 'email', 'class_id')
            ->with('class:class_id,class_name')
            ->get();

        // Lấy thông tin sinh viên chưa đọc
        $unreadStudentIds = $unreadRecipients->pluck('student_id')->toArray();
        $unreadStudents = Student::whereIn('student_id', $unreadStudentIds)
            ->select('student_id', 'full_name', 'email', 'class_id')
            ->with('class:class_id,class_name')
            ->get();

        // Tính toán thống kê
        $totalRecipients = $recipients->count();
        $totalRead = $readRecipients->count();
        $totalUnread = $unreadRecipients->count();
        $readPercentage = $totalRecipients > 0 ? round(($totalRead / $totalRecipients) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'notification_id' => $id,
                'notification_title' => $notification->title,
                'total_recipients' => $totalRecipients,
                'total_read' => $totalRead,
                'total_unread' => $totalUnread,
                'read_percentage' => $readPercentage,
                'read_students' => $readStudents,
                'unread_students' => $unreadStudents
            ]
        ]);
    }
}
