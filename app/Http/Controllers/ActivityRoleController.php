<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityRole;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Controller quản lý vai trò trong hoạt động (Activity Roles)
 */
class ActivityRoleController extends Controller
{
    /**
     * Lấy danh sách vai trò của một hoạt động
     * Role: Student, Advisor
     */
    public function index(Request $request, $activityId)
    {
        $currentRole = $request->current_role;
        $currentUserId = $request->current_user_id;

        // Validate role
        if (!in_array($currentRole, ['advisor', 'student'])) {
            return response()->json([
                'success' => false,
                'message' => 'Role không hợp lệ'
            ], 403);
        }

        // Validate role
        $activity = Activity::with('classes:class_id,class_name')->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động không tồn tại'
            ], 404);
        }

        // Kiểm tra quyền xem
        if ($currentRole === 'advisor') {
            if ($activity->advisor_id != $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem hoạt động này'
                ], 403);
            }
        } else { // student
            $student = Student::find($currentUserId);

            if (!$student || !$activity->classes->contains('class_id', $student->class_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hoạt động không dành cho lớp của bạn'
                ], 403);
            }
        }

        // Validate role
        $roles = ActivityRole::where('activity_id', $activityId)
            ->withCount([
                'registrations as active_registrations_count' => function ($query) {
                    $query->whereIn('status', ['registered', 'attended']);
                }
            ])
            ->orderBy('activity_role_id', 'asc')
            ->get()
            ->map(function ($role) {
                $role->available_slots = $role->max_slots
                    ? max(0, $role->max_slots - $role->active_registrations_count)
                    : null;
                return $role;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'activity' => [
                    'activity_id' => $activity->activity_id,
                    'title' => $activity->title,
                    'status' => $activity->status,
                    'start_time' => $activity->start_time,
                    'end_time' => $activity->end_time
                ],
                'total_roles' => $roles->count(),
                'roles' => $roles
            ]
        ], 200);
    }

    /**
     * Xem chi tiết một vai trò
     * Role: Student, Advisor
     */
    public function show(Request $request, $activityId, $roleId)
    {
        $currentRole = $request->current_role;
        $currentUserId = $request->current_user_id;

        // Validate role
        if (!in_array($currentRole, ['advisor', 'student'])) {
            return response()->json([
                'success' => false,
                'message' => 'Role không hợp lệ'
            ], 403);
        }

        // Query tối ưu
        $role = ActivityRole::with('activity.classes:class_id,class_name')
            ->where('activity_id', $activityId)
            ->where('activity_role_id', $roleId)
            ->withCount([
                'registrations as active_registrations_count' => function ($query) {
                    $query->whereIn('status', ['registered', 'attended']);
                }
            ])
            ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Vai trò không tồn tại'
            ], 404);
        }

        // Kiểm tra quyền xem
        if ($currentRole === 'advisor') {
            if ($role->activity->advisor_id != $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem vai trò này'
                ], 403);
            }
        } else { // student
            $student = Student::find($currentUserId);

            if (!$student || !$role->activity->classes->contains('class_id', $student->class_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hoạt động không dành cho lớp của bạn'
                ], 403);
            }
        }

        $role->available_slots = $role->max_slots
            ? max(0, $role->max_slots - $role->active_registrations_count)
            : null;

        return response()->json([
            'success' => true,
            'data' => $role
        ], 200);
    }

    /**
     * Thêm vai trò vào hoạt động
     * Role: Advisor only (chỉ người tạo hoạt động)
     */
    public function store(Request $request, $activityId)
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
                'message' => 'Bạn không có quyền thêm vai trò cho hoạt động này'
            ], 403);
        }

        // Không cho phép thêm vai trò cho hoạt động đã completed
        if ($activity->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Không thể thêm vai trò cho hoạt động đã hoàn thành'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'requirements' => 'nullable|string|max:1000',
            'points_awarded' => 'required|integer|min:0|max:100',
            'point_type' => 'required|in:ctxh,ren_luyen',
            'max_slots' => 'nullable|integer|min:1|max:1000'
        ], [
            'role_name.required' => 'Tên vai trò không được để trống',
            'role_name.max' => 'Tên vai trò không được vượt quá 100 ký tự',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự',
            'requirements.max' => 'Yêu cầu không được vượt quá 1000 ký tự',
            'points_awarded.required' => 'Điểm thưởng không được để trống',
            'points_awarded.integer' => 'Điểm thưởng phải là số nguyên',
            'points_awarded.min' => 'Điểm thưởng phải lớn hơn hoặc bằng 0',
            'points_awarded.max' => 'Điểm thưởng không được vượt quá 100',
            'point_type.required' => 'Loại điểm không được để trống',
            'point_type.in' => 'Loại điểm phải là "ctxh" hoặc "ren_luyen"',
            'max_slots.integer' => 'Số lượng slot phải là số nguyên',
            'max_slots.min' => 'Số lượng slot phải lớn hơn 0',
            'max_slots.max' => 'Số lượng slot không được vượt quá 1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Kiểm tra trùng tên vai trò trong cùng hoạt động
        $existingRole = ActivityRole::where('activity_id', $activityId)
            ->where('role_name', $request->role_name)
            ->first();

        if ($existingRole) {
            return response()->json([
                'success' => false,
                'message' => 'Vai trò với tên này đã tồn tại trong hoạt động'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $role = ActivityRole::create([
                'activity_id' => $activityId,
                'role_name' => $request->role_name,
                'description' => $request->description,
                'requirements' => $request->requirements,
                'points_awarded' => $request->points_awarded,
                'point_type' => $request->point_type,
                'max_slots' => $request->max_slots
            ]);

            DB::commit();

            Log::info('Vai trò hoạt động đã được tạo', [
                'role_id' => $role->activity_role_id,
                'activity_id' => $activityId,
                'advisor_id' => $currentUserId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thêm vai trò thành công',
                'data' => $role
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi tạo vai trò', [
                'error' => $e->getMessage(),
                'activity_id' => $activityId
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo vai trò: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật vai trò
     * Role: Advisor only (chỉ người tạo hoạt động)
     */
    public function update(Request $request, $activityId, $roleId)
    {
        $currentUserId = $request->current_user_id;

        $role = ActivityRole::with('activity')
            ->where('activity_id', $activityId)
            ->where('activity_role_id', $roleId)
            ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Vai trò không tồn tại'
            ], 404);
        }

        if ($role->activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền cập nhật vai trò này'
            ], 403);
        }

        // Không cho phép sửa vai trò của hoạt động đã completed
        if ($role->activity->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Không thể cập nhật vai trò của hoạt động đã hoàn thành'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'role_name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'requirements' => 'nullable|string|max:1000',
            'points_awarded' => 'sometimes|required|integer|min:0|max:100',
            'point_type' => 'sometimes|required|in:ctxh,ren_luyen',
            'max_slots' => 'nullable|integer|min:1|max:1000'
        ], [
            'role_name.required' => 'Tên vai trò không được để trống',
            'role_name.max' => 'Tên vai trò không được vượt quá 100 ký tự',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự',
            'requirements.max' => 'Yêu cầu không được vượt quá 1000 ký tự',
            'points_awarded.required' => 'Điểm thưởng không được để trống',
            'points_awarded.integer' => 'Điểm thưởng phải là số nguyên',
            'points_awarded.min' => 'Điểm thưởng phải lớn hơn hoặc bằng 0',
            'points_awarded.max' => 'Điểm thưởng không được vượt quá 100',
            'point_type.required' => 'Loại điểm không được để trống',
            'point_type.in' => 'Loại điểm phải là "ctxh" hoặc "ren_luyen"',
            'max_slots.integer' => 'Số lượng slot phải là số nguyên',
            'max_slots.min' => 'Số lượng slot phải lớn hơn 0',
            'max_slots.max' => 'Số lượng slot không được vượt quá 1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Kiểm tra trùng tên vai trò (nếu đổi tên)  
        if ($request->has('role_name') && $request->role_name !== $role->role_name) {
            $existingRole = ActivityRole::where('activity_id', $activityId)
                ->where('role_name', $request->role_name)
                ->where('activity_role_id', '!=', $roleId)
                ->first();

            if ($existingRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vai trò với tên này đã tồn tại trong hoạt động'
                ], 400);
            }
        }

        // Kiểm tra max_slots không được nhỏ hơn số đã đăng ký
        if ($request->has('max_slots')) {
            $registeredCount = $role->registrations()
                ->whereIn('status', ['registered', 'attended'])
                ->count();

            if ($request->max_slots < $registeredCount) {
                return response()->json([
                    'success' => false,
                    'message' => "Không thể giảm số lượng slot xuống dưới {$registeredCount} (số sinh viên đã đăng ký)"
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            $role->update($request->only([
                'role_name',
                'description',
                'requirements',
                'points_awarded',
                'point_type',
                'max_slots'
            ]));

            DB::commit();

            Log::info('Vai trò hoạt động đã được cập nhật', [
                'role_id' => $role->activity_role_id,
                'advisor_id' => $currentUserId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật vai trò thành công',
                'data' => $role
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi cập nhật vai trò', [
                'error' => $e->getMessage(),
                'role_id' => $roleId
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật vai trò: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa vai trò
     * Role: Advisor only (chỉ người tạo hoạt động)
     */
    public function destroy(Request $request, $activityId, $roleId)
    {
        $currentUserId = $request->current_user_id;

        $role = ActivityRole::with('activity')
            ->where('activity_id', $activityId)
            ->where('activity_role_id', $roleId)
            ->withCount('registrations')
            ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Vai trò không tồn tại'
            ], 404);
        }

        if ($role->activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa vai trò này'
            ], 403);
        }

        // Không cho phép xóa vai trò của hoạt động đã completed
        if ($role->activity->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa vai trò của hoạt động đã hoàn thành'
            ], 400);
        }

        // BẮT BUỘC: Không cho phép xóa nếu đã có sinh viên đăng ký
        if ($role->registrations_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Không thể xóa vai trò đã có {$role->registrations_count} sinh viên đăng ký"
            ], 400);
        }

        DB::beginTransaction();
        try {
            $role->delete();
            DB::commit();

            Log::info('Vai trò hoạt động đã bị xóa', [
                'role_id' => $roleId,
                'advisor_id' => $currentUserId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa vai trò thành công'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi xóa vai trò', [
                'error' => $e->getMessage(),
                'role_id' => $roleId
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa vai trò: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách sinh viên đã đăng ký một vai trò cụ thể
     * Role: Advisor only
     */
    public function getRegistrations(Request $request, $activityId, $roleId)
    {
        $currentUserId = $request->current_user_id;

        $role = ActivityRole::with('activity')
            ->where('activity_id', $activityId)
            ->where('activity_role_id', $roleId)
            ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Vai trò không tồn tại'
            ], 404);
        }

        if ($role->activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem danh sách này'
            ], 403);
        }

        // Query tối ưu với eager loading
        $registrations = $role->registrations()
            ->with([
                'student:student_id,user_code,full_name,email,phone_number,class_id',
                'student.class:class_id,class_name'
            ])
            ->orderBy('registration_time', 'desc')
            ->get()
            ->map(function ($reg) use ($role) {
                return [
                    'registration_id' => $reg->registration_id,
                    'student' => [
                        'student_id' => $reg->student->student_id,
                        'user_code' => $reg->student->user_code,
                        'full_name' => $reg->student->full_name,
                        'email' => $reg->student->email,
                        'phone_number' => $reg->student->phone_number,
                        'class_name' => $reg->student->class->class_name ?? null
                    ],
                    'status' => $reg->status,
                    'registration_time' => $reg->registration_time,
                    'points_awarded' => $role->points_awarded,
                    'point_type' => $role->point_type
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'role' => [
                    'activity_role_id' => $role->activity_role_id,
                    'role_name' => $role->role_name,
                    'points_awarded' => $role->points_awarded,
                    'point_type' => $role->point_type,
                    'max_slots' => $role->max_slots
                ],
                'summary' => [
                    'total_registrations' => $registrations->count(),
                    'by_status' => [
                        'registered' => $registrations->where('status', 'registered')->count(),
                        'attended' => $registrations->where('status', 'attended')->count(),
                        'absent' => $registrations->where('status', 'absent')->count(),
                        'cancelled' => $registrations->where('status', 'cancelled')->count()
                    ]
                ],
                'registrations' => $registrations
            ]
        ], 200);
    }
}
