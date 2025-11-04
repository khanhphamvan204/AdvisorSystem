<?php

namespace App\Http\Controllers;

use App\Models\ActivityRole;
use App\Models\ActivityRegistration;
use App\Models\CancellationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controller quản lý đăng ký hoạt động của sinh viên
 * Role: Student
 */
class ActivityRegistrationController extends Controller
{
    /**
     * Sinh viên đăng ký tham gia hoạt động (role cụ thể)
     * Role: Student only
     */
    public function register(Request $request)
    {
        $studentId = $request->current_user_id;

        // Validate
        $validator = Validator::make($request->all(), [
            'activity_role_id' => 'required|integer|exists:Activity_Roles,activity_role_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $role = ActivityRole::with('activity')->find($request->activity_role_id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Vai trò hoạt động không tồn tại'
            ], 404);
        }

        // Kiểm tra hoạt động còn mở đăng ký không
        if ($role->activity->status === 'completed' || $role->activity->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động đã kết thúc hoặc bị hủy'
            ], 400);
        }

        // Kiểm tra đã đăng ký chưa
        $existingRegistration = ActivityRegistration::where('activity_role_id', $request->activity_role_id)
            ->where('student_id', $studentId)
            ->first();

        if ($existingRegistration) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã đăng ký vai trò này rồi'
            ], 400);
        }

        // Kiểm tra số lượng slot còn trống
        if ($role->max_slots) {
            $registeredCount = ActivityRegistration::where('activity_role_id', $request->activity_role_id)
                ->whereIn('status', ['registered', 'attended'])
                ->count();

            if ($registeredCount >= $role->max_slots) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vai trò này đã hết chỗ'
                ], 400);
            }
        }

        // Tạo đăng ký
        $registration = ActivityRegistration::create([
            'activity_role_id' => $request->activity_role_id,
            'student_id' => $studentId,
            'status' => 'registered'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công',
            'data' => $registration
        ], 201);
    }

    /**
     * Lấy danh sách hoạt động mà sinh viên đã đăng ký
     * Role: Student only
     */
    public function myRegistrations(Request $request)
    {
        $studentId = $request->current_user_id;

        $registrations = ActivityRegistration::where('student_id', $studentId)
            ->with([
                'role.activity' => function ($q) {
                    $q->with('advisor:advisor_id,full_name');
                }
            ])
            ->orderBy('registration_time', 'desc')
            ->get()
            ->map(function ($reg) {
                return [
                    'registration_id' => $reg->registration_id,
                    'activity_title' => $reg->role->activity->title,
                    'role_name' => $reg->role->role_name,
                    'points_awarded' => $reg->role->points_awarded,
                    'point_type' => $reg->role->point_type,
                    'activity_start_time' => $reg->role->activity->start_time,
                    'activity_location' => $reg->role->activity->location,
                    'status' => $reg->status,
                    'registration_time' => $reg->registration_time,
                    'advisor_name' => $reg->role->activity->advisor->full_name
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $registrations
        ], 200);
    }

    /**
     * Hủy đăng ký hoạt động (tạo yêu cầu hủy)
     * Role: Student only
     */
    public function cancelRegistration(Request $request)
    {
        $studentId = $request->current_user_id;

        // Validate
        $validator = Validator::make($request->all(), [
            'registration_id' => 'required|integer|exists:Activity_Registrations,registration_id',
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $registration = ActivityRegistration::with('role.activity')->find($request->registration_id);

        // Kiểm tra quyền
        if ($registration->student_id != $studentId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền hủy đăng ký này'
            ], 403);
        }

        // Kiểm tra trạng thái
        if ($registration->status !== 'registered') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể hủy đăng ký ở trạng thái "đã đăng ký"'
            ], 400);
        }

        // Kiểm tra trạng thái hoạt động
        $activity = $registration->role->activity;
        if ($activity->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Không thể hủy đăng ký hoạt động đã hoàn thành'
            ], 400);
        }

        if ($activity->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động đã bị hủy'
            ], 400);
        }

        // Kiểm tra đã có yêu cầu hủy chưa
        $existingRequest = CancellationRequest::where('registration_id', $request->registration_id)->first();
        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Yêu cầu hủy đã được gửi trước đó'
            ], 400);
        }

        // Tạo yêu cầu hủy
        $cancellationRequest = CancellationRequest::create([
            'registration_id' => $request->registration_id,
            'reason' => $request->reason,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gửi yêu cầu hủy thành công',
            'data' => $cancellationRequest
        ], 201);
    }

    /**
     * Lấy danh sách yêu cầu hủy của sinh viên
     * Role: Student only
     */
    public function myCancellationRequests(Request $request)
    {
        $studentId = $request->current_user_id;

        $requests = CancellationRequest::whereHas('registration', function ($q) use ($studentId) {
            $q->where('student_id', $studentId);
        })
            ->with([
                'registration.role.activity' => function ($q) {
                    $q->with('advisor:advisor_id,full_name');
                }
            ])
            ->orderBy('requested_at', 'desc')
            ->get()
            ->map(function ($req) {
                return [
                    'request_id' => $req->request_id,
                    'registration_id' => $req->registration_id,
                    'activity_title' => $req->registration->role->activity->title,
                    'role_name' => $req->registration->role->role_name,
                    'reason' => $req->reason,
                    'status' => $req->status,
                    'requested_at' => $req->requested_at,
                    'advisor_name' => $req->registration->role->activity->advisor->full_name
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $requests
        ], 200);
    }

    /**
     * Duyệt/từ chối yêu cầu hủy đăng ký
     * Role: Advisor only
     */
    public function approveCancellation(Request $request, $requestId)
    {
        $currentUserId = $request->current_user_id;

        // Validate
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $cancellationRequest = CancellationRequest::with('registration.role.activity')
            ->find($requestId);

        if (!$cancellationRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Yêu cầu hủy không tồn tại'
            ], 404);
        }

        // Kiểm tra quyền
        $activity = $cancellationRequest->registration->role->activity;
        if ($activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền duyệt yêu cầu này'
            ], 403);
        }

        // Cập nhật trạng thái
        $cancellationRequest->update(['status' => $request->status]);

        // Nếu approved, cập nhật trạng thái registration
        if ($request->status === 'approved') {
            $cancellationRequest->registration->update(['status' => 'cancelled']);
        }

        return response()->json([
            'success' => true,
            'message' => $request->status === 'approved' ? 'Đã duyệt yêu cầu hủy' : 'Đã từ chối yêu cầu hủy',
            'data' => $cancellationRequest
        ], 200);
    }

    /**
     * Lấy danh sách yêu cầu hủy của hoạt động
     * Role: Advisor only
     */
    public function getCancellationRequests(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        $activity = \App\Models\Activity::find($activityId);
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
                'message' => 'Bạn không có quyền xem danh sách này'
            ], 403);
        }

        // Lấy danh sách yêu cầu hủy
        $requests = CancellationRequest::whereHas('registration.role', function ($q) use ($activityId) {
            $q->where('activity_id', $activityId);
        })
            ->with([
                'registration.student:student_id,user_code,full_name,email,phone_number',
                'registration.role'
            ])
            ->orderBy('requested_at', 'desc')
            ->get()
            ->map(function ($req) {
                return [
                    'request_id' => $req->request_id,
                    'registration_id' => $req->registration_id,
                    'student' => $req->registration->student,
                    'role_name' => $req->registration->role->role_name,
                    'reason' => $req->reason,
                    'status' => $req->status,
                    'requested_at' => $req->requested_at
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'activity' => $activity,
                'total_requests' => $requests->count(),
                'requests' => $requests
            ]
        ], 200);
    }
}