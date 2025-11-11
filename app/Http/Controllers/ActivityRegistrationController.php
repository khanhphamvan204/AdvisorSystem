<?php

namespace App\Http\Controllers;

use App\Models\ActivityRole;
use App\Models\ActivityRegistration;
use App\Models\CancellationRequest;
use App\Models\Student;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Controller quản lý đăng ký hoạt động của sinh viên
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

        // Validate input
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

        // Load role + activity + classes (chỉ cần class_id, class_name)
        $role = ActivityRole::with('activity.classes:class_id,class_name')
            ->find($request->activity_role_id);

        // KIỂM TRA CẢ ROLE VÀ ACTIVITY TỒN TẠI
        if (!$role || !$role->activity) {
            return response()->json([
                'success' => false,
                'message' => 'Vai trò hoặc hoạt động không tồn tại'
            ], 404);
        }

        $activity = $role->activity;

        // Lấy thông tin sinh viên
        $student = Student::find($studentId);
        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin sinh viên'
            ], 404);
        }

        // Kiểm tra quyền truy cập lớp
        if (!$activity->classes->contains('class_id', $student->class_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động này không dành cho lớp của bạn'
            ], 403);
        }

        // Kiểm tra trạng thái hoạt động
        if (in_array($activity->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động đã kết thúc hoặc bị hủy'
            ], 400);
        }

        // Không cho đăng ký nếu đã bắt đầu
        if (Carbon::parse($activity->start_time)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể đăng ký hoạt động đã bắt đầu'
            ], 400);
        }

        // Kiểm tra đã đăng ký vai trò khác trong cùng hoạt động chưa
        $existingRegistration = ActivityRegistration::where('student_id', $studentId)
            ->whereHas('role', function ($q) use ($activity) {
                $q->where('activity_id', $activity->activity_id);
            })
            ->whereIn('status', ['registered', 'attended'])
            ->first();

        if ($existingRegistration) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã đăng ký một vai trò khác trong hoạt động này rồi'
            ], 400);
        }

        // Kiểm tra slot còn trống
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
        DB::beginTransaction();
        try {
            $registration = ActivityRegistration::create([
                'activity_role_id' => $request->activity_role_id,
                'student_id' => $studentId,
                'status' => 'registered',
                'registration_time' => Carbon::now()
            ]);
            $registration = $registration->fresh();

            // Load dữ liệu cần trả về (chỉ chọn cột cần thiết)
            $registration->load([
                'role:activity_role_id,role_name,points_awarded,point_type,activity_id',
                'role.activity:activity_id,title,start_time,location'
            ]);

            DB::commit();

            // Ghi log
            Log::info('Sinh viên đăng ký hoạt động', [
                'registration_id' => $registration->registration_id,
                'student_id' => $studentId,
                'activity_id' => $activity->activity_id
            ]);

            // Trả về dữ liệu an toàn với optional()
            return response()->json([
                'success' => true,
                'message' => 'Đăng ký thành công',
                'data' => [
                    'registration_id' => $registration->registration_id,
                    'activity_title' => optional($registration->role->activity)->title,
                    'role_name' => $registration->role->role_name,
                    'points_awarded' => $registration->role->points_awarded,
                    'point_type' => $registration->role->point_type,
                    'activity_start_time' => optional($registration->role->activity)->start_time,
                    'activity_location' => optional($registration->role->activity)->location,
                    'status' => $registration->status,
                    'registration_time' => $registration->registration_time
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Lỗi khi đăng ký hoạt động', [
                'error' => $e->getMessage(),
                'student_id' => $studentId,
                'activity_role_id' => $request->activity_role_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi đăng ký: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách hoạt động mà sinh viên đã đăng ký
     * Role: Student only
     */
    public function myRegistrations(Request $request)
    {
        $studentId = $request->current_user_id;

        $query = ActivityRegistration::where('student_id', $studentId)
            ->with([
                'role:activity_role_id,activity_id,role_name,points_awarded,point_type',
                // THÊM advisor_id VÀO ĐÂY
                'role.activity:activity_id,advisor_id,title,start_time,end_time,location,status',
                'role.activity.advisor:advisor_id,full_name'
            ]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('activity_status')) {
            $query->whereHas('role.activity', function ($q) use ($request) {
                $q->where('status', $request->activity_status);
            });
        }

        $registrations = $query->orderBy('registration_time', 'desc')
            ->get()
            ->filter(fn($reg) => $reg->role && $reg->role->activity)
            ->map(function ($reg) {
                $advisor = $reg->role->activity->advisor;

                return [
                    'registration_id' => $reg->registration_id,
                    'activity_id' => $reg->role->activity->activity_id,
                    'activity_title' => $reg->role->activity->title,
                    'activity_status' => $reg->role->activity->status,
                    'role_name' => $reg->role->role_name,
                    'points_awarded' => $reg->role->points_awarded,
                    'point_type' => $reg->role->point_type,
                    'activity_start_time' => $reg->role->activity->start_time,
                    'activity_end_time' => $reg->role->activity->end_time,
                    'activity_location' => $reg->role->activity->location,
                    'registration_status' => $reg->status,
                    'registration_time' => $reg->registration_time,
                    'advisor_name' => $advisor?->full_name ?? 'N/A',
                    'can_cancel' => $reg->status === 'registered' &&
                        !in_array($reg->role->activity->status, ['completed', 'cancelled'])
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $registrations->count(),
                'summary' => [
                    'registered' => $registrations->where('registration_status', 'registered')->count(),
                    'attended' => $registrations->where('registration_status', 'attended')->count(),
                    'absent' => $registrations->where('registration_status', 'absent')->count(),
                    'cancelled' => $registrations->where('registration_status', 'cancelled')->count()
                ],
                'registrations' => $registrations
            ]
        ], 200);
    }
    /**
     * Hủy đăng ký hoạt động (tạo yêu cầu hủy)
     * Role: Student only
     */
    public function cancelRegistration(Request $request)
    {
        $studentId = $request->current_user_id;

        $validator = Validator::make($request->all(), [
            'registration_id' => 'required|integer|exists:Activity_Registrations,registration_id',
            'reason' => 'required|string|min:10|max:500' // Yêu cầu lý do tối thiểu 10 ký tự
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $registration = ActivityRegistration::with([
            'role.activity:activity_id,title,status,start_time',
            'role:activity_role_id,activity_id,role_name'
        ])
            ->find($request->registration_id);

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đăng ký'
            ], 404);
        }

        if ($registration->student_id != $studentId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền hủy đăng ký này'
            ], 403);
        }

        if ($registration->status !== 'registered') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể hủy đăng ký ở trạng thái "đã đăng ký". Trạng thái hiện tại: ' . $registration->status
            ], 400);
        }

        if (!$registration->role || !$registration->role->activity) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu hoạt động không hợp lệ'
            ], 400);
        }

        $activity = $registration->role->activity;

        if (in_array($activity->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể hủy đăng ký hoạt động đã kết thúc hoặc bị hủy'
            ], 400);
        }

        // Không cho phép hủy nếu hoạt động đã bắt đầu
        if (Carbon::parse($activity->start_time)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể hủy đăng ký hoạt động đã bắt đầu'
            ], 400);
        }

        //Kiểm tra đã có yêu cầu hủy pending chưa
        $existingRequest = CancellationRequest::where('registration_id', $request->registration_id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Yêu cầu hủy đang chờ xử lý, vui lòng chờ CVHT phê duyệt'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $cancellationRequest = CancellationRequest::create([
                'registration_id' => $request->registration_id,
                'reason' => $request->reason,
                'status' => 'pending'
            ]);

            DB::commit();

            Log::info('Sinh viên yêu cầu hủy hoạt động', [
                'registration_id' => $registration->registration_id,
                'student_id' => $studentId,
                'activity_id' => $activity->activity_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gửi yêu cầu hủy thành công. Vui lòng chờ CVHT phê duyệt',
                'data' => [
                    'request_id' => $cancellationRequest->request_id,
                    'registration_id' => $cancellationRequest->registration_id,
                    'activity_title' => $activity->title,
                    'role_name' => $registration->role->role_name,
                    'reason' => $cancellationRequest->reason,
                    'status' => $cancellationRequest->status,
                    'requested_at' => $cancellationRequest->requested_at
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi tạo yêu cầu hủy', [
                'error' => $e->getMessage(),
                'registration_id' => $request->registration_id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo yêu cầu hủy: ' . $e->getMessage()
            ], 500);
        }
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
                'registration.role:activity_role_id,activity_id,role_name',
                // THÊM advisor_id VÀO ĐÂY
                'registration.role.activity:activity_id,advisor_id,title,status,start_time',
                'registration.role.activity.advisor:advisor_id,full_name'
            ])
            ->orderBy('requested_at', 'desc')
            ->get()
            ->filter(function ($req) {
                return $req->registration &&
                    $req->registration->role &&
                    $req->registration->role->activity;
            })
            ->map(function ($req) {
                $advisor = $req->registration->role->activity->advisor;

                return [
                    'request_id' => $req->request_id,
                    'registration_id' => $req->registration_id,
                    'activity_id' => $req->registration->role->activity->activity_id,
                    'activity_title' => $req->registration->role->activity->title,
                    'activity_status' => $req->registration->role->activity->status,
                    'activity_start_time' => $req->registration->role->activity->start_time,
                    'role_name' => $req->registration->role->role_name,
                    'reason' => $req->reason,
                    'request_status' => $req->status,
                    'requested_at' => $req->requested_at,
                    'advisor_name' => $advisor?->full_name ?? 'N/A'
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $requests->count(),
                'summary' => [
                    'pending' => $requests->where('request_status', 'pending')->count(),
                    'approved' => $requests->where('request_status', 'approved')->count(),
                    'rejected' => $requests->where('request_status', 'rejected')->count()
                ],
                'requests' => $requests
            ]
        ], 200);
    }

    /**
     * Duyệt/từ chối yêu cầu hủy đăng ký
     * Role: Advisor only (CVHT của sinh viên)
     */
    public function approveCancellation(Request $request, $activityId, $requestId)
    {
        $currentUserId = $request->current_user_id;

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

        // BƯỚC 1: KIỂM TRA TRỰC TIẾP TỪ DB 
        $requestCheck = DB::table('Cancellation_Requests')
            ->where('request_id', $requestId)
            ->select('request_id', 'registration_id', 'status')
            ->first();

        if (!$requestCheck) {
            return response()->json([
                'success' => false,
                'message' => 'Yêu cầu hủy không tồn tại'
            ], 404);
        }

        if ($requestCheck->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Yêu cầu hủy đã được xử lý trước đó (Status: ' . $requestCheck->status . ')'
            ], 400);
        }

        // BƯỚC 2: BÂY GIỜ MỚI LOAD MODEL ĐỂ CẬP NHẬT
        $cancellationRequest = CancellationRequest::with([
            'registration.student:student_id,full_name,user_code,class_id',
            'registration.student.class:class_id,class_name,advisor_id',
            'registration.role.activity:activity_id,advisor_id,title,status'
        ])->find($requestId);

        if (!$cancellationRequest) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy'], 404);
        }

        // Kiểm tra relationship có load đầy đủ không
        if (!$cancellationRequest->registration || !$cancellationRequest->registration->student) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu đăng ký hoặc sinh viên không tồn tại'
            ], 404);
        }

        $student = $cancellationRequest->registration->student;

        if (!$student->class) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin lớp của sinh viên'
            ], 404);
        }

        if ($student->class->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền duyệt yêu cầu này'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $cancellationRequest->update([
                'status' => $request->status
            ]);

            if ($request->status === 'approved') {
                $cancellationRequest->registration->update([
                    'status' => 'cancelled'
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $request->status === 'approved' ? 'Duyệt thành công' : 'Từ chối thành công',
                'data' => [
                    'request_id' => $cancellationRequest->request_id,
                    'student_name' => $student->full_name,
                    'activity_title' => $cancellationRequest->registration->role->activity->title ?? 'N/A',
                    'request_status' => $cancellationRequest->status,
                    'registration_status' => $cancellationRequest->registration->status
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách yêu cầu hủy cho CVHT
     * Role: Advisor only (CVHT xem yêu cầu của sinh viên trong lớp mình)
     */
    public function getCancellationRequestsForAdvisor(Request $request)
    {
        $currentUserId = $request->current_user_id;

        // Filter theo status
        $status = $request->get('status'); // pending, approved, rejected

        $query = CancellationRequest::whereHas('registration.student.class', function ($q) use ($currentUserId) {
            // Chỉ lấy yêu cầu của sinh viên trong lớp mình
            $q->where('advisor_id', $currentUserId);
        })
            ->with([
                'registration.student:student_id,user_code,full_name,class_id',
                'registration.student.class:class_id,class_name',
                'registration.role:activity_role_id,activity_id,role_name',
                'registration.role.activity:activity_id,title,status,start_time'
            ]);

        if ($status) {
            $query->where('status', $status);
        }

        $requests = $query->orderBy('requested_at', 'desc')
            ->get()
            ->filter(function ($req) {
                return $req->registration &&
                    $req->registration->student &&
                    $req->registration->role &&
                    $req->registration->role->activity;
            })
            ->map(function ($req) {
                return [
                    'request_id' => $req->request_id,
                    'registration_id' => $req->registration_id,
                    'student' => [
                        'student_id' => $req->registration->student->student_id,
                        'user_code' => $req->registration->student->user_code,
                        'full_name' => $req->registration->student->full_name,
                        'class_name' => $req->registration->student->class->class_name ?? null
                    ],
                    'activity' => [
                        'activity_id' => $req->registration->role->activity->activity_id,
                        'title' => $req->registration->role->activity->title,
                        'status' => $req->registration->role->activity->status,
                        'start_time' => $req->registration->role->activity->start_time
                    ],
                    'role_name' => $req->registration->role->role_name,
                    'reason' => $req->reason,
                    'request_status' => $req->status,
                    'requested_at' => $req->requested_at
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $requests->count(),
                'summary' => [
                    'pending' => $requests->where('request_status', 'pending')->count(),
                    'approved' => $requests->where('request_status', 'approved')->count(),
                    'rejected' => $requests->where('request_status', 'rejected')->count()
                ],
                'requests' => $requests
            ]
        ], 200);
    }

    /**
     * Lấy danh sách yêu cầu hủy của hoạt động (cho người tạo hoạt động)
     * Role: Advisor only (chỉ người TẠO hoạt động)
     */
    public function getCancellationRequests(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        $activity = Activity::find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động không tồn tại'
            ], 404);
        }

        // Kiểm tra quyền (chỉ người tạo HĐ mới thấy list này)
        if ($activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem danh sách này'
            ], 403);
        }

        // Filter theo status
        $status = $request->get('status');

        $query = CancellationRequest::whereHas('registration.role', function ($q) use ($activityId) {
            $q->where('activity_id', $activityId);
        })
            ->with([
                'registration.student:student_id,user_code,full_name,class_id',
                'registration.student.class:class_id,class_name,advisor_id',
                'registration.student.class.advisor:advisor_id,full_name',
                'registration.role:activity_role_id,role_name,activity_id'
            ]);

        if ($status) {
            $query->where('status', $status);
        }

        $requests = $query->orderBy('requested_at', 'desc')
            ->get()
            ->map(function ($req) {
                return [
                    'request_id' => $req->request_id,
                    'registration_id' => $req->registration_id,
                    'student' => [
                        'student_id' => $req->registration->student->student_id,
                        'user_code' => $req->registration->student->user_code,
                        'full_name' => $req->registration->student->full_name,
                        'class_name' => $req->registration->student->class->class_name ?? null,
                        'advisor_name' => $req->registration->student->class->advisor->full_name ?? 'N/A'
                    ],
                    'role_name' => $req->registration->role->role_name,
                    'reason' => $req->reason,
                    'request_status' => $req->status,
                    'requested_at' => $req->requested_at
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'activity' => [
                    'activity_id' => $activity->activity_id,
                    'title' => $activity->title,
                    'status' => $activity->status
                ],
                'summary' => [
                    'total_requests' => $requests->count(),
                    'pending' => $requests->where('request_status', 'pending')->count(),
                    'approved' => $requests->where('request_status', 'approved')->count(),
                    'rejected' => $requests->where('request_status', 'rejected')->count()
                ],
                'requests' => $requests
            ]
        ], 200);
    }
    /**
     * Sinh viên lấy danh sách hoạt động đã tham gia kèm vai trò
     * 
     */
    public function getMyParticipatedActivities(Request $request)
    {
        $currentUserId = $request->current_user_id;
        $currentRole = $request->current_role;

        // Chỉ cho phép sinh viên
        if ($currentRole !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ sinh viên mới có thể truy cập endpoint này'
            ], 403);
        }

        try {
            // Lấy tất cả các đăng ký hoạt động của sinh viên
            $registrations = ActivityRegistration::where('student_id', $currentUserId)
                ->with([
                    'role.activity' => function ($query) {
                        $query->with([
                            'advisor:advisor_id,full_name,email',
                            'organizerUnit:unit_id,unit_name',
                            'classes:class_id,class_name'
                        ]);
                    },
                    'role:activity_role_id,activity_id,role_name,description,requirements,points_awarded,point_type,max_slots'
                ])
                ->orderBy('registration_time', 'desc')
                ->get();

            // Format dữ liệu
            $activities = $registrations->map(function ($registration) {
                $activity = $registration->role->activity;

                return [
                    'registration_id' => $registration->registration_id,
                    'registration_time' => $registration->registration_time,
                    'registration_status' => $registration->status,
                    'activity' => [
                        'activity_id' => $activity->activity_id,
                        'title' => $activity->title,
                        'general_description' => $activity->general_description,
                        'location' => $activity->location,
                        'start_time' => $activity->start_time,
                        'end_time' => $activity->end_time,
                        'status' => $activity->status,
                        'advisor' => $activity->advisor,
                        'organizer_unit' => $activity->organizerUnit,
                    ],
                    'role' => [
                        'activity_role_id' => $registration->role->activity_role_id,
                        'role_name' => $registration->role->role_name,
                        'description' => $registration->role->description,
                        'requirements' => $registration->role->requirements,
                        'points_awarded' => $registration->role->points_awarded,
                        'point_type' => $registration->role->point_type,
                        'max_slots' => $registration->role->max_slots
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $activities,
                'total' => $activities->count()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Lỗi khi lấy danh sách hoạt động đã tham gia', [
                'error' => $e->getMessage(),
                'student_id' => $currentUserId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }
}