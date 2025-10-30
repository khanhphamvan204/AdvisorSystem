<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\NotificationResponse;
use App\Models\NotificationAttachment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\Rule;
use App\Models\ClassModel;

class NotificationController extends Controller
{
    // Hàm index() (Không thay đổi)
    public function index(Request $request)
    {
        $user = JWTAuth::user();

        if ($user->role === 'advisor') {
            $notifications = Notification::where('advisor_id', $user->user_id)
                ->with(['classes', 'attachments'])
                ->withCount('responses')
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json(['success' => true, 'data' => $notifications]);
        }

        if ($user->role === 'student') {
            $student = Student::where('user_id', $user->user_id)->first();
            if (!$student) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin sinh viên'], 404);
            }
            $notifications = Notification::whereHas('classes', function ($query) use ($student) {
                $query->where('classes.class_id', $student->class_id);
            })
                ->with(['advisor.user', 'attachments'])
                ->withCount([
                    'responses' => function ($query) use ($user) {
                        $query->where('student_id', $user->user_id);
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();
            $notificationIds = $notifications->pluck('notification_id');
            $readStatus = NotificationRecipient::where('student_id', $user->user_id)
                ->whereIn('notification_id', $notificationIds)
                ->pluck('is_read', 'notification_id');
            $notifications->transform(function ($notification) use ($readStatus) {
                $notification->setAttribute('is_read', $readStatus->get($notification->notification_id, false));
                return $notification;
            });
            return response()->json(['success' => true, 'data' => $notifications]);
        }
        return response()->json(['success' => false, 'message' => 'Không có quyền truy cập'], 403);
    }

    public function store(Request $request)
    {
        $user = JWTAuth::user();

        if ($user->role !== 'advisor') {
            return response()->json(['success' => false, 'message' => 'Chỉ cố vấn học tập mới có quyền tạo thông báo'], 403);
        }

        $managedClassIds = ClassModel::where('advisor_id', $user->user_id)->pluck('class_id')->all();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'summary' => 'required|string',
            'link' => 'nullable|url|max:2083',
            'type' => 'required|string|max:50',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => ['required', 'integer', 'exists:Classes,class_id', Rule::in($managedClassIds)],
            'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240'
        ], [
            'title.required' => 'Tiêu đề thông báo là bắt buộc',
            'summary.required' => 'Nội dung thông báo là bắt buộc',
            'class_ids.required' => 'Phải chọn ít nhất một lớp',
            'class_ids.*.exists' => 'Một trong các lớp được chọn không tồn tại.',
            'class_ids.*.in' => 'Bạn không có quyền gửi thông báo cho một hoặc nhiều lớp đã chọn.'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $notification = Notification::create([
                'advisor_id' => $user->user_id,
                'title' => $request->title,
                'summary' => $request->summary,
                'link' => $request->link,
                'type' => $request->type
            ]);
            $notification->classes()->attach($request->class_ids);
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
            $studentIds = Student::whereIn('class_id', $request->class_ids)->pluck('user_id');
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
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Tạo thông báo thành công',
                'data' => $notification->load(['classes', 'attachments'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $user = JWTAuth::user();
        $notification = Notification::with(['advisor.user', 'classes', 'attachments'])->find($id);
        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông báo'], 404);
        }
        if ($user->role === 'advisor') {
            if ($notification->advisor_id !== $user->user_id) {
                return response()->json(['success' => false, 'message' => 'Không có quyền xem thông báo này'], 403);
            }
            $notification->load(['responses.student.user']);
            $notification->total_recipients = NotificationRecipient::where('notification_id', $id)->count();
            $notification->total_read = NotificationRecipient::where('notification_id', $id)->where('is_read', true)->count();
            $notification->total_responses = $notification->responses->count();
        } elseif ($user->role === 'student') {
            $student = Student::where('user_id', $user->user_id)->first();
            if (!$student) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin sinh viên'], 404);
            }
            $hasAccess = $notification->classes->contains('class_id', $student->class_id);
            if (!$hasAccess) {
                return response()->json(['success' => false, 'message' => 'Không có quyền xem thông báo này'], 403);
            }
            NotificationRecipient::updateOrCreate(
                ['notification_id' => $id, 'student_id' => $user->user_id],
                ['is_read' => true, 'read_at' => now()]
            );
            $notification->my_response = NotificationResponse::where('notification_id', $id)
                ->where('student_id', $user->user_id)
                ->with('advisorUser')
                ->first();
        }
        return response()->json(['success' => true, 'data' => $notification]);
    }

    /**
     * Cập nhật thông báo (chỉ Advisor)
     */
    public function update(Request $request, $id)
    {
        $user = JWTAuth::user();

        if ($user->role !== 'advisor') {
            return response()->json(['success' => false, 'message' => 'Chỉ cố vấn học tập mới có quyền cập nhật thông báo'], 403);
        }

        $notification = Notification::find($id);
        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông báo'], 404);
        }
        if ($notification->advisor_id !== $user->user_id) {
            return response()->json(['success' => false, 'message' => 'Không có quyền cập nhật thông báo này'], 403);
        }

        $managedClassIds = ClassModel::where('advisor_id', $user->user_id)->pluck('class_id')->all();

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'summary' => 'sometimes|required|string',
            'link' => 'nullable|url|max:2083',
            'type' => 'sometimes|required|string|max:50',
            'class_ids' => 'sometimes|required|array|min:1',
            'class_ids.*' => ['required', 'integer', 'exists:Classes,class_id', Rule::in($managedClassIds)],

            // Validation cho file đính kèm mới
            'attachments_to_add' => 'sometimes|array',
            'attachments_to_add.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',

            // Validation cho các file cần xóa
            'attachment_ids_to_delete' => 'sometimes|array',
            'attachment_ids_to_delete.*' => [
                'required',
                'integer',
                // Đảm bảo ID file này tồn tại VÀ nó thuộc về chính thông báo này
                Rule::exists('Notification_Attachments', 'attachment_id')->where('notification_id', $id)
            ]
        ], [
            // Messages lỗi
            'class_ids.*.in' => 'Bạn không có quyền gửi thông báo cho một hoặc nhiều lớp đã chọn.',
            'attachments_to_add.*.mimes' => 'File đính kèm mới không đúng định dạng.',
            'attachment_ids_to_delete.*.exists' => 'Một trong các file bạn muốn xóa không hợp lệ hoặc không thuộc về thông báo này.'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Cập nhật thông tin cơ bản
            $updateData = [];
            if ($request->has('title'))
                $updateData['title'] = $request->title;
            if ($request->has('summary'))
                $updateData['summary'] = $request->summary;
            if ($request->has('link'))
                $updateData['link'] = $request->link;
            if ($request->has('type'))
                $updateData['type'] = $request->type;
            if (!empty($updateData)) {
                $notification->update($updateData);
            }

            // 2. Cập nhật danh sách lớp
            if ($request->has('class_ids')) {
                $notification->classes()->sync($request->class_ids);
                NotificationRecipient::where('notification_id', $id)->delete();
                $studentIds = Student::whereIn('class_id', $request->class_ids)->pluck('user_id');
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

            // --- BẮT ĐẦU LOGIC XỬ LÝ FILE ---

            // 3. Xử lý xóa file đính kèm
            if ($request->has('attachment_ids_to_delete')) {
                $attachmentIds = $request->attachment_ids_to_delete;

                // Lấy thông tin file để xóa khỏi Storage
                $filesToDelete = NotificationAttachment::whereIn('attachment_id', $attachmentIds)->get();
                foreach ($filesToDelete as $file) {
                    Storage::disk('public')->delete($file->file_path);
                }

                // Xóa khỏi CSDL
                NotificationAttachment::whereIn('attachment_id', $attachmentIds)->delete();
            }

            // 4. Xử lý thêm file đính kèm mới
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
                // Tải lại 'attachments' để lấy danh sách file mới nhất
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

    // Hàm destroy()
    public function destroy($id)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'advisor') {
            return response()->json(['success' => false, 'message' => 'Chỉ cố vấn học tập mới có quyền xóa thông báo'], 403);
        }
        $notification = Notification::find($id);
        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông báo'], 404);
        }
        if ($notification->advisor_id !== $user->user_id) {
            return response()->json(['success' => false, 'message' => 'Không có quyền xóa thông báo này'], 403);
        }
        foreach ($notification->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }
        $notification->delete();
        return response()->json(['success' => true, 'message' => 'Xóa thông báo thành công']);
    }

    // Hàm statistics()
    public function statistics()
    {
        $user = JWTAuth::user();
        if ($user->role !== 'advisor') {
            return response()->json(['success' => false, 'message' => 'Chỉ cố vấn học tập mới có quyền xem thống kê'], 403);
        }
        $totalNotifications = Notification::where('advisor_id', $user->user_id)->count();
        $totalRecipients = NotificationRecipient::whereHas('notification', function ($query) use ($user) {
            $query->where('advisor_id', $user->user_id);
        })->count();
        $totalRead = NotificationRecipient::whereHas('notification', function ($query) use ($user) {
            $query->where('advisor_id', $user->user_id);
        })->where('is_read', true)->count();
        $totalResponses = NotificationResponse::whereHas('notification', function ($query) use ($user) {
            $query->where('advisor_id', $user->user_id);
        })->count();
        $pendingResponses = NotificationResponse::whereHas('notification', function ($query) use ($user) {
            $query->where('advisor_id', $user->user_id);
        })->where('status', 'pending')->count();
        $byType = Notification::where('advisor_id', $user->user_id)
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
}