<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controller quản lý vai trò trong hoạt động (Activity Roles)
 * Role: Advisor only
 */
class ActivityRoleController extends Controller
{
    /**
     * Lấy danh sách vai trò của một hoạt động
     * Role: Student, Advisor
     */
    public function index(Request $request, $activityId)
    {
        $activity = Activity::find($activityId);
        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động không tồn tại'
            ], 404);
        }

        $roles = ActivityRole::where('activity_id', $activityId)
            ->withCount([
                'registrations as active_registrations_count' => function ($query) {
                    $query->whereIn('status', ['registered', 'attended']);
                }
            ])
            ->get()
            ->map(function ($role) {
                $role->available_slots = $role->max_slots ? ($role->max_slots - $role->active_registrations_count) : null;
                return $role;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'activity' => $activity,
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
        $role = ActivityRole::with('activity')
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

        $role->available_slots = $role->max_slots ? ($role->max_slots - $role->active_registrations_count) : null;

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

        // Kiểm tra quyền
        if ($activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền thêm vai trò cho hoạt động này'
            ], 403);
        }

        // Validate
        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'points_awarded' => 'required|integer|min:0',
            'point_type' => 'required|in:ctxh,ren_luyen',
            'max_slots' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $role = ActivityRole::create([
            'activity_id' => $activityId,
            'role_name' => $request->role_name,
            'description' => $request->description,
            'requirements' => $request->requirements,
            'points_awarded' => $request->points_awarded,
            'point_type' => $request->point_type,
            'max_slots' => $request->max_slots
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thêm vai trò thành công',
            'data' => $role
        ], 201);
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

        // Kiểm tra quyền
        if ($role->activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền cập nhật vai trò này'
            ], 403);
        }

        // Validate
        $validator = Validator::make($request->all(), [
            'role_name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'points_awarded' => 'sometimes|required|integer|min:0',
            'point_type' => 'sometimes|required|in:ctxh,ren_luyen',
            'max_slots' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Kiểm tra nếu giảm max_slots thì không được nhỏ hơn số lượng đã đăng ký
        if ($request->has('max_slots')) {
            $registeredCount = $role->registrations()->whereIn('status', ['registered', 'attended'])->count();
            if ($request->max_slots < $registeredCount) {
                return response()->json([
                    'success' => false,
                    'message' => "Không thể giảm số lượng slot xuống dưới {$registeredCount} (số sinh viên đã đăng ký)"
                ], 400);
            }
        }

        $role->update($request->only([
            'role_name',
            'description',
            'requirements',
            'points_awarded',
            'point_type',
            'max_slots'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật vai trò thành công',
            'data' => $role
        ], 200);
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
            ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Vai trò không tồn tại'
            ], 404);
        }

        // Kiểm tra quyền
        if ($role->activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa vai trò này'
            ], 403);
        }

        // // Kiểm tra có sinh viên đã đăng ký chưa
        // $registeredCount = $role->registrations()->count();
        // if ($registeredCount > 0) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => "Không thể xóa vai trò đã có {$registeredCount} sinh viên đăng ký"
        //     ], 400);
        // }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa vai trò thành công'
        ], 200);
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

        // Kiểm tra quyền
        if ($role->activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem danh sách này'
            ], 403);
        }

        $registrations = $role->registrations()
            ->with('student:student_id,user_code,full_name,email,phone_number')
            ->get()
            ->map(function ($reg) use ($role) {
                return [
                    'registration_id' => $reg->registration_id,
                    'student' => $reg->student,
                    'status' => $reg->status,
                    'registration_time' => $reg->registration_time,
                    'points_awarded' => $role->points_awarded,
                    'point_type' => $role->point_type
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'total_registrations' => $registrations->count(),
                'registrations' => $registrations
            ]
        ], 200);
    }
}