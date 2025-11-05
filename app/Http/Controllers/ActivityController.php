<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityRole;
// === THÊM 2 MODEL NÀY ĐỂ KIỂM TRA QUYỀN ===
use App\Models\Student;
use App\Models\ClassModel;
// ===
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Controller quản lý CRUD hoạt động (Activities)
 */
class ActivityController extends Controller
{
    /**
     * Lấy danh sách hoạt động (ĐÃ SỬA: Lọc theo CVHT)
     * Role: Student, Advisor
     */
    public function index(Request $request)
    {
        $currentRole = $request->current_role;
        $currentUserId = $request->current_user_id;

        $query = Activity::with(['advisor:advisor_id,full_name', 'organizerUnit:unit_id,unit_name']);

        if ($currentRole === 'advisor') {
            // Advisor chỉ thấy hoạt động do chính mình tạo
            $query->where('advisor_id', $currentUserId);

        } elseif ($currentRole === 'student') {
            // Student chỉ thấy hoạt động của CVHT của mình
            $student = Student::with('class:class_id,advisor_id')->find($currentUserId);

            if (!$student || !$student->class || !$student->class->advisor_id) {
                return response()->json(['success' => true, 'data' => []]); // Trả về mảng rỗng nếu SV không có lớp/CVHT
            }

            $advisorId = $student->class->advisor_id;
            $query->where('advisor_id', $advisorId);
        }

        $query->orderBy('start_time', 'desc');

        // Filter theo thời gian
        if ($request->has('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('end_time', '<=', $request->to_date);
        }

        // Phân trang
        $perPage = $request->get('per_page', 15);
        $activities = $query->get();

        return response()->json([
            'success' => true,
            'data' => $activities
        ], 200);
    }

    /**
     * Xem chi tiết hoạt động (ĐÃ SỬA: Lọc theo CVHT)
     * Role: Student, Advisor
     */
    public function show(Request $request, $activityId)
    {
        $currentRole = $request->current_role;
        $currentUserId = $request->current_user_id;

        $query = Activity::with([
            'advisor:advisor_id,full_name,email,phone_number',
            'organizerUnit:unit_id,unit_name',
            'roles' => function ($q) {
                $q->withCount('registrations');
            }
        ]);
        if ($currentRole === 'advisor') {
            // Advisor chỉ thấy hoạt động do chính mình tạo
            $query->where('advisor_id', $currentUserId);

        } elseif ($currentRole === 'student') {
            // Student chỉ thấy hoạt động của CVHT của mình
            $student = Student::with('class:class_id,advisor_id')->find($currentUserId);

            if (!$student || !$student->class || !$student->class->advisor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hoạt động không tồn tại hoặc bạn không có quyền xem'
                ], 404);
            }

            $advisorId = $student->class->advisor_id;
            $query->where('advisor_id', $advisorId);
        }

        $activity = $query->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                // Trả về 404 (Không tìm thấy) thay vì 403 (Cấm) để bảo mật
                'message' => 'Hoạt động không tồn tại hoặc bạn không có quyền xem'
            ], 404);
        }

        // Nếu là sinh viên, kiểm tra trạng thái đăng ký
        if ($currentRole === 'student') {
            $activity->roles->each(function ($role) use ($currentUserId) {
                $registration = \App\Models\ActivityRegistration::where('activity_role_id', $role->activity_role_id)
                    ->where('student_id', $currentUserId)
                    ->first();

                $role->student_registration_status = $registration ? $registration->status : null;
                // Tính số chỗ còn lại
                if (isset($role->max_slots)) {
                    $role->available_slots = $role->max_slots - $role->registrations_count;
                } else {
                    $role->available_slots = null;
                }
            });
        }

        return response()->json([
            'success' => true,
            'data' => $activity
        ], 200);
    }

    /**
     * Tạo hoạt động mới
     * Role: Advisor only
     */
    public function store(Request $request)
    {
        $currentUserId = $request->current_user_id;

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'general_description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'organizer_unit_id' => 'nullable|integer|exists:Units,unit_id',
            'status' => 'nullable|string|in:upcoming,ongoing,completed,cancelled',
            'roles' => 'required|array|min:1',
            'roles.*.role_name' => 'required|string|max:100',
            'roles.*.description' => 'nullable|string',
            'roles.*.requirements' => 'nullable|string',
            'roles.*.points_awarded' => 'required|integer|min:0',
            'roles.*.point_type' => 'required|in:ctxh,ren_luyen',
            'roles.*.max_slots' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $activity = Activity::create([
                'advisor_id' => $currentUserId, // Hoạt động được gán cho CVHT tạo ra
                'organizer_unit_id' => $request->organizer_unit_id,
                'title' => $request->title,
                'general_description' => $request->general_description,
                'location' => $request->location,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'status' => $request->status ?? 'upcoming'
            ]);

            foreach ($request->roles as $roleData) {
                ActivityRole::create([
                    'activity_id' => $activity->activity_id,
                    'role_name' => $roleData['role_name'],
                    'description' => $roleData['description'] ?? null,
                    'requirements' => $roleData['requirements'] ?? null,
                    'points_awarded' => $roleData['points_awarded'],
                    'point_type' => $roleData['point_type'],
                    'max_slots' => $roleData['max_slots'] ?? null
                ]);
            }

            DB::commit();
            $activity->load('roles');

            return response()->json([
                'success' => true,
                'message' => 'Tạo hoạt động thành công',
                'data' => $activity
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo hoạt động: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật hoạt động
     * Role: Advisor only (chỉ người tạo)
     */
    public function update(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        $activity = Activity::find($activityId);
        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động không tồn tại'
            ], 404);
        }

        // Kiểm tra quyền
        if ($activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền cập nhật hoạt động này'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'general_description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'start_time' => 'sometimes|required|date',
            'end_time' => 'sometimes|required|date|after:start_time',
            'status' => 'nullable|string|in:upcoming,ongoing,completed,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $activity->update($request->only([
            'title',
            'general_description',
            'location',
            'start_time',
            'end_time',
            'status'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật hoạt động thành công',
            'data' => $activity
        ], 200);
    }

    /**
     * Xóa hoạt động
     * Role: Advisor only (chỉ người tạo)
     */
    public function destroy(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        $activity = Activity::find($activityId);
        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động không tồn tại'
            ], 404);
        }

        // Kiểm tra quyền (Đã an toàn)
        if ($activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa hoạt động này'
            ], 403);
        }

        if ($activity->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa hoạt động đã hoàn thành'
            ], 400);
        }

        $activity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa hoạt động thành công'
        ], 200);
    }

    /**
     * Xem danh sách sinh viên đã đăng ký hoạt động
     * Role: Advisor only (chỉ người tạo)
     */
    public function getRegistrations(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        $activity = Activity::find($activityId);
        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động không tồn tại'
            ], 404);
        }

        if ($activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem danh sách này'
            ], 403);
        }

        $registrations = \App\Models\ActivityRegistration::whereHas('role', function ($q) use ($activityId) {
            $q->where('activity_id', $activityId);
        })
            ->with(['student:student_id,user_code,full_name,email,phone_number', 'role'])
            ->get()
            ->map(function ($reg) {
                return [
                    'registration_id' => $reg->registration_id,
                    'student' => $reg->student,
                    'role_name' => $reg->role->role_name,
                    'points_awarded' => $reg->role->points_awarded,
                    'point_type' => $reg->role->point_type,
                    'status' => $reg->status,
                    'registration_time' => $reg->registration_time
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'activity' => $activity,
                'total_registrations' => $registrations->count(),
                'registrations' => $registrations
            ]
        ], 200);
    }

    /**
     * Cập nhật trạng thái tham gia (điểm danh)
     * Role: Advisor only (chỉ người tạo)
     */
    public function updateAttendance(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        // (Validator của bạn giữ nguyên)
        $validator = Validator::make($request->all(), [
            'attendances' => 'required|array|min:1',
            'attendances.*.registration_id' => 'required|integer|exists:Activity_Registrations,registration_id',
            'attendances.*.status' => 'required|in:attended,absent'
        ], [
            'attendances.required' => 'Danh sách điểm danh là bắt buộc',
            'attendances.*.registration_id.required' => 'ID đăng ký là bắt buộc',
            'attendances.*.status.required' => 'Trạng thái điểm danh là bắt buộc',
            'attendances.*.status.in' => 'Trạng thái điểm danh chỉ được là "attended" (có mặt) hoặc "absent" (vắng mặt)'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $activity = Activity::find($activityId);
        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hoạt động này trong hệ thống'
            ], 404);
        }

        // Kiểm tra quyền
        if ($activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền điểm danh cho hoạt động này...'
            ], 403);
        }

        if ($activity->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Không thể điểm danh vì hoạt động đã bị hủy'], 400);
        }

        if ($activity->status === 'upcoming') {
            return response()->json(['success' => false, 'message' => 'Không thể điểm danh vì hoạt động chưa diễn ra...'], 400);
        }

        DB::beginTransaction();
        try {
            $updated = [];
            $skipped = [];

            foreach ($request->attendances as $attendance) {
                $registration = \App\Models\ActivityRegistration::with(['role', 'student'])
                    ->find($attendance['registration_id']);

                if (!$registration) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => "Không tìm thấy đăng ký với ID {$attendance['registration_id']}..."], 400);
                }

                if ($registration->role->activity_id != $activityId) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => "Đăng ký ID {$attendance['registration_id']} không thuộc hoạt động này..."], 400);
                }

                if (in_array($registration->status, ['registered', 'attended', 'absent'])) {
                    $oldStatus = $registration->status;
                    $registration->update(['status' => $attendance['status']]);

                    $statusText = [
                        'registered' => 'đã đăng ký',
                        'attended' => 'có mặt',
                        'absent' => 'vắng mặt'
                    ];

                    $updated[] = [
                        'registration_id' => $registration->registration_id,
                        'student_name' => $registration->student->full_name,
                        'student_code' => $registration->student->user_code,
                        'role_name' => $registration->role->role_name,
                        'old_status' => $oldStatus,
                        'old_status_text' => $statusText[$oldStatus] ?? $oldStatus,
                        'new_status' => $attendance['status'],
                        'new_status_text' => $statusText[$attendance['status']] ?? $attendance['status']
                    ];
                } else {
                    $skipped[] = [
                        'registration_id' => $registration->registration_id,
                        'student_name' => $registration->student->full_name,
                        'student_code' => $registration->student->user_code,
                        'current_status' => $registration->status,
                        'reason' => $registration->status === 'cancelled'
                            ? 'Sinh viên đã hủy đăng ký, không thể điểm danh'
                            : 'Trạng thái không hợp lệ để điểm danh'
                    ];
                }
            }

            DB::commit();

            $message = 'Cập nhật điểm danh thành công';
            if (count($updated) > 0 && count($skipped) > 0) {
                $message = 'Đã điểm danh thành công cho ' . count($updated) . ' sinh viên, bỏ qua ' . count($skipped) . ' sinh viên';
            } elseif (count($updated) === 0 && count($skipped) > 0) {
                $message = 'Không có sinh viên nào được điểm danh. Tất cả ' . count($skipped) . ' sinh viên đều bị bỏ qua';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'total_updated' => count($updated),
                    'total_skipped' => count($skipped),
                    'updated' => $updated,
                    'skipped' => $skipped
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật điểm danh: ' . $e->getMessage()
            ], 500);
        }
    }
}