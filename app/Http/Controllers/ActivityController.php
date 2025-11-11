<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityRole;
use App\Models\Student;
use App\Models\ClassModel;
use App\Models\ActivityRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\Semester;
use App\Services\PointCalculationService;

class ActivityController extends Controller
{
    /**
     * Lấy danh sách hoạt động
     * Student: Chỉ thấy hoạt động được gán cho lớp mình
     * Advisor: Thấy hoạt động do mình tạo
     */
    public function index(Request $request)
    {
        $currentRole = $request->current_role;
        $currentUserId = $request->current_user_id;

        // Bắt buộc phải có role hợp lệ
        if (!in_array($currentRole, ['advisor', 'student'])) {
            return response()->json([
                'success' => false,
                'message' => 'Role không hợp lệ'
            ], 403);
        }

        $query = Activity::with([
            'advisor:advisor_id,full_name',
            'organizerUnit:unit_id,unit_name',
            'classes:class_id,class_name'
        ]);

        if ($currentRole === 'advisor') {
            $query->where('advisor_id', $currentUserId);
        } else { // student
            $student = Student::find($currentUserId);

            if (!$student) {
                return response()->json([
                    'success' => true,
                    'data' => ['data' => [], 'total' => 0]
                ]);
            }

            // Chỉ lấy hoạt động của lớp sinh viên
            $query->whereHas('classes', function ($q) use ($student) {
                $q->where('classes.class_id', $student->class_id);
            });
        }

        // Filter theo thời gian
        if ($request->has('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('end_time', '<=', $request->to_date);
        }

        // Filter theo status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $query->orderBy('start_time', 'desc');

        // $perPage = $request->get('per_page', 15);
        $activities = $query->get();

        return response()->json([
            'success' => true,
            'data' => $activities
        ], 200);
    }

    /**
     * Xem chi tiết hoạt động
     * Student: Chỉ thấy hoạt động của lớp mình
     * Advisor: Chỉ thấy hoạt động mình tạo
     */
    public function show(Request $request, $activityId)
    {
        $currentRole = $request->current_role;
        $currentUserId = $request->current_user_id;

        // Query một lần với tất cả relationships
        $activity = Activity::with([
            'advisor:advisor_id,full_name,email,phone_number',
            'organizerUnit:unit_id,unit_name',
            'classes:class_id,class_name',
            'roles' => function ($q) {
                $q->withCount('registrations');
            }
        ])->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động không tồn tại'
            ], 404);
        }

        // Kiểm tra quyền truy cập
        if ($currentRole === 'advisor') {
            if ($activity->advisor_id != $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem hoạt động này'
                ], 403);
            }
        } elseif ($currentRole === 'student') {
            $student = Student::find($currentUserId);

            if (!$student || !$activity->classes->contains('class_id', $student->class_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hoạt động này không dành cho lớp của bạn'
                ], 403);
            }

            // Thêm thông tin đăng ký của sinh viên (tối ưu: query một lần)
            $studentRegistrations = ActivityRegistration::whereHas('role', function ($q) use ($activityId) {
                $q->where('activity_id', $activityId);
            })
                ->where('student_id', $currentUserId)
                ->with('role:activity_role_id,role_name')
                ->get()
                ->keyBy('activity_role_id');

            $activity->roles->each(function ($role) use ($studentRegistrations) {
                $registration = $studentRegistrations->get($role->activity_role_id);

                $role->student_registration_status = $registration ? $registration->status : null;
                $role->student_registration_id = $registration ? $registration->registration_id : null;

                if (isset($role->max_slots)) {
                    $role->available_slots = max(0, $role->max_slots - $role->registrations_count);
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
     * Tạo hoạt động mới (Có gán lớp)
     * Role: Advisor only
     */
    public function store(Request $request)
    {
        $currentUserId = $request->current_user_id;

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'general_description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'start_time' => 'required|date|after:now', // Phải sau thời điểm hiện tại
            'end_time' => 'required|date|after:start_time',
            'organizer_unit_id' => 'nullable|integer|exists:Units,unit_id',
            'status' => 'nullable|string|in:upcoming,ongoing,completed,cancelled',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'integer|exists:Classes,class_id',
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

        // Kiểm tra quyền: Advisor chỉ được gán cho các lớp mình quản lý
        $advisorClasses = ClassModel::where('advisor_id', $currentUserId)
            ->pluck('class_id')
            ->toArray();

        $invalidClasses = array_diff($request->class_ids, $advisorClasses);

        if (!empty($invalidClasses)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ được gán hoạt động cho các lớp mình quản lý',
                'invalid_class_ids' => array_values($invalidClasses)
            ], 403);
        }

        DB::beginTransaction();
        try {
            $activity = Activity::create([
                'advisor_id' => $currentUserId,
                'organizer_unit_id' => $request->organizer_unit_id,
                'title' => $request->title,
                'general_description' => $request->general_description,
                'location' => $request->location,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'status' => $request->status ?? 'upcoming'
            ]);

            // Gán lớp cho hoạt động
            $activity->classes()->attach($request->class_ids);

            // Tạo các vai trò
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

            $activity->load(['roles', 'classes']);

            Log::info('Hoạt động đã được tạo', [
                'activity_id' => $activity->activity_id,
                'advisor_id' => $currentUserId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo hoạt động thành công',
                'data' => $activity
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi tạo hoạt động', [
                'error' => $e->getMessage(),
                'advisor_id' => $currentUserId
            ]);
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

        if ($activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền cập nhật hoạt động này'
            ], 403);
        }

        // // Không cho phép sửa hoạt động đã completed
        // if ($activity->status === 'completed') {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Không thể cập nhật hoạt động đã hoàn thành'
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'general_description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'start_time' => 'sometimes|required|date',
            'end_time' => 'sometimes|required|date|after:start_time',
            'organizer_unit_id' => 'nullable|integer|exists:Units,unit_id',
            'status' => 'nullable|string|in:upcoming,ongoing,completed,cancelled',
            'class_ids' => 'sometimes|array|min:1',
            'class_ids.*' => 'integer|exists:Classes,class_id'
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
            $activity->update($request->only([
                'title',
                'general_description',
                'location',
                'start_time',
                'end_time',
                'organizer_unit_id',
                'status'
            ]));

            if ($request->has('class_ids')) {
                // Kiểm tra quyền
                $advisorClasses = ClassModel::where('advisor_id', $currentUserId)
                    ->pluck('class_id')
                    ->toArray();

                $invalidClasses = array_diff($request->class_ids, $advisorClasses);

                if (!empty($invalidClasses)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ được gán hoạt động cho các lớp mình quản lý',
                        'invalid_class_ids' => array_values($invalidClasses)
                    ], 403);
                }

                $activity->classes()->sync($request->class_ids);
            }

            DB::commit();

            Log::info('Hoạt động đã được cập nhật', [
                'activity_id' => $activity->activity_id,
                'advisor_id' => $currentUserId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật hoạt động thành công',
                'data' => $activity->load('classes')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi cập nhật hoạt động', [
                'error' => $e->getMessage(),
                'activity_id' => $activityId
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật hoạt động: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa hoạt động
     * Role: Advisor only (chỉ người tạo)
     */
    public function destroy(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        // Lấy activity + tổng số đăng ký
        $activity = Activity::withCount('registrations')->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động không tồn tại'
            ], 404);
        }

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

        // Kiểm tra đăng ký
        if ($activity->registrations_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa hoạt động đã có sinh viên đăng ký',
                'total_registrations' => $activity->registrations_count
            ], 400);
        }

        DB::beginTransaction();
        try {
            $activity->delete();
            DB::commit();

            Log::info('Hoạt động đã bị xóa', [
                'activity_id' => $activityId,
                'advisor_id' => $currentUserId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa hoạt động thành công'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Lỗi khi xóa hoạt động', [
                'error' => $e->getMessage(),
                'activity_id' => $activityId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa hoạt động: ' . $e->getMessage()
            ], 500);
        }
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

        // Tối ưu query
        $registrations = ActivityRegistration::whereHas('role', function ($q) use ($activityId) {
            $q->where('activity_id', $activityId);
        })
            ->with([
                'student:student_id,user_code,full_name,email,phone_number,class_id',
                'student.class:class_id,class_name',
                'role:activity_role_id,activity_id,role_name,points_awarded,point_type'
            ])
            ->orderBy('registration_time', 'desc')
            ->get()
            ->map(function ($reg) {
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
                'activity' => [
                    'activity_id' => $activity->activity_id,
                    'title' => $activity->title,
                    'status' => $activity->status,
                    'start_time' => $activity->start_time,
                    'end_time' => $activity->end_time
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

    /**
     * Cập nhật trạng thái tham gia (điểm danh)
     * Role: Advisor only (chỉ người tạo)
     */
    public function updateAttendance(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        $validator = Validator::make($request->all(), [
            'attendances' => 'required|array|min:1',
            'attendances.*.registration_id' => 'required|integer|exists:Activity_Registrations,registration_id',
            'attendances.*.status' => 'required|in:attended,absent'
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
                'message' => 'Không tìm thấy hoạt động'
            ], 404);
        }

        if ($activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền điểm danh cho hoạt động này'
            ], 403);
        }

        if (in_array($activity->status, ['cancelled', 'upcoming'])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể điểm danh cho hoạt động chưa diễn ra hoặc đã bị hủy'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $updated = [];
            $skipped = [];

            // Lấy tất cả registrations một lần
            $registrationIds = collect($request->attendances)->pluck('registration_id');
            $registrations = ActivityRegistration::with(['role', 'student'])
                ->whereIn('registration_id', $registrationIds)
                ->get()
                ->keyBy('registration_id');

            foreach ($request->attendances as $attendance) {
                $registration = $registrations->get($attendance['registration_id']);

                if (!$registration) {
                    $skipped[] = [
                        'registration_id' => $attendance['registration_id'],
                        'reason' => 'Không tìm thấy đăng ký'
                    ];
                    continue;
                }

                if ($registration->role->activity_id != $activityId) {
                    $skipped[] = [
                        'registration_id' => $attendance['registration_id'],
                        'reason' => 'Đăng ký không thuộc hoạt động này'
                    ];
                    continue;
                }

                if (!in_array($registration->status, ['registered', 'attended', 'absent'])) {
                    $skipped[] = [
                        'registration_id' => $registration->registration_id,
                        'student_name' => $registration->student->full_name,
                        'reason' => 'Trạng thái không hợp lệ: ' . $registration->status
                    ];
                    continue;
                }

                $oldStatus = $registration->status;
                $registration->update(['status' => $attendance['status']]);

                $updated[] = [
                    'registration_id' => $registration->registration_id,
                    'student_name' => $registration->student->full_name,
                    'student_code' => $registration->student->user_code,
                    'old_status' => $oldStatus,
                    'new_status' => $attendance['status']
                ];
            }

            DB::commit();

            Log::info('Điểm danh đã được cập nhật', [
                'activity_id' => $activityId,
                'advisor_id' => $currentUserId,
                'updated_count' => count($updated)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật điểm danh thành công',
                'data' => [
                    'total_updated' => count($updated),
                    'total_skipped' => count($skipped),
                    'updated' => $updated,
                    'skipped' => $skipped
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi cập nhật điểm danh', [
                'error' => $e->getMessage(),
                'activity_id' => $activityId
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách sinh viên có thể phân công (trong các lớp được gán hoạt động)
     * Role: Advisor only
     */
    public function getAvailableStudents(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        $activity = Activity::with('classes')->find($activityId);

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

        if (in_array($activity->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xem danh sách sinh viên cho hoạt động đã hoàn thành hoặc bị hủy'
            ], 400);
        }

        $assignedClassIds = $activity->classes->pluck('class_id');

        if ($assignedClassIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'activity' => $activity,
                    'summary' => [
                        'total_students' => 0,
                        'available_count' => 0,
                        'unavailable_count' => 0
                    ],
                    'available_students' => [],
                    'unavailable_students' => []
                ]
            ]);
        }

        // Lấy học kỳ gần nhất
        $latestSemester = Semester::orderBy('end_date', 'desc')->first();

        // Query tất cả sinh viên trong các lớp được gán
        $students = Student::whereIn('class_id', $assignedClassIds)
            ->with(['class:class_id,class_name'])
            ->get();

        // Query tất cả đăng ký của hoạt động này một lần (tránh N+1)
        $existingRegistrations = ActivityRegistration::whereHas('role', function ($q) use ($activityId) {
            $q->where('activity_id', $activityId);
        })
            ->whereIn('student_id', $students->pluck('student_id'))
            ->whereIn('status', ['registered', 'attended'])
            ->with('role:activity_role_id,role_name,activity_id')
            ->get()
            ->keyBy('student_id');

        // Map students với thông tin đăng ký
        $studentsData = $students->map(function ($student) use ($existingRegistrations, $latestSemester) {
            $registration = $existingRegistrations->get($student->student_id);

            $canAssign = true;
            $reasonCannotAssign = null;

            if ($registration) {
                $canAssign = false;
                $reasonCannotAssign = "Đã đăng ký vai trò '{$registration->role->role_name}' (Trạng thái: {$registration->status})";
            }

            // Tính điểm
            $training_point = 0;
            $social_point = 0;

            if ($latestSemester) {
                try {
                    $training_point = PointCalculationService::calculateTrainingPoints(
                        $student->student_id,
                        $latestSemester->semester_id
                    );

                    $social_point = PointCalculationService::calculateSocialPoints(
                        $student->student_id,
                        $latestSemester->semester_id
                    );
                } catch (\Exception $e) {
                    Log::warning('Lỗi khi tính điểm cho sinh viên', [
                        'student_id' => $student->student_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'student_id' => $student->student_id,
                'user_code' => $student->user_code,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'phone_number' => $student->phone_number,
                'class_name' => $student->class->class_name,
                'training_point' => $training_point,
                'social_point' => $social_point,
                'current_semester' => $latestSemester ? $latestSemester->semester_name : null,
                'status' => $student->status,
                'can_assign' => $canAssign,
                'reason_cannot_assign' => $reasonCannotAssign,
                'current_registration' => $registration ? [
                    'registration_id' => $registration->registration_id,
                    'role_name' => $registration->role->role_name,
                    'registration_status' => $registration->status
                ] : null
            ];
        })->values();

        $availableStudents = $studentsData->where('can_assign', true)->values();
        $unavailableStudents = $studentsData->where('can_assign', false)->values();

        return response()->json([
            'success' => true,
            'data' => [
                'activity' => [
                    'activity_id' => $activity->activity_id,
                    'title' => $activity->title,
                    'status' => $activity->status
                ],
                'assigned_classes' => $activity->classes->map(fn($class) => [
                    'class_id' => $class->class_id,
                    'class_name' => $class->class_name
                ]),
                'current_semester' => $latestSemester ? [
                    'semester_id' => $latestSemester->semester_id,
                    'semester_name' => $latestSemester->semester_name,
                    'academic_year' => $latestSemester->academic_year
                ] : null,
                'summary' => [
                    'total_students' => $studentsData->count(),
                    'available_count' => $availableStudents->count(),
                    'unavailable_count' => $unavailableStudents->count()
                ],
                'available_students' => $availableStudents,
                'unavailable_students' => $unavailableStudents
            ]
        ], 200);
    }

    /**
     * Phân công sinh viên tham gia hoạt động
     * Role: Advisor only
     */
    public function assignStudents(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        $validator = Validator::make($request->all(), [
            'assignments' => 'required|array|min:1',
            'assignments.*.student_id' => 'required|integer|exists:Students,student_id',
            'assignments.*.activity_role_id' => 'required|integer|exists:Activity_Roles,activity_role_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $activity = Activity::with('classes')->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động không tồn tại'
            ], 404);
        }

        if ($activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền phân công cho hoạt động này'
            ], 403);
        }

        if (in_array($activity->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể phân công cho hoạt động đã hoàn thành hoặc bị hủy'
            ], 400);
        }

        $assignedClassIds = $activity->classes->pluck('class_id');

        DB::beginTransaction();
        try {
            $assigned = [];
            $skipped = [];

            // Lấy tất cả activity roles của hoạt động này   
            $activityRoles = ActivityRole::where('activity_id', $activityId)
                ->get()
                ->keyBy('activity_role_id');

            // Lấy tất cả student_ids cần check
            $studentIds = collect($request->assignments)->pluck('student_id')->unique();

            $students = Student::whereIn('student_id', $studentIds)
                ->with('class')
                ->get()
                ->keyBy('student_id');

            // Lấy các đăng ký đã tồn tại CỦA HOẠT ĐỘNG NÀY (query một lần)
            $existingRegistrations = ActivityRegistration::whereHas('role', function ($q) use ($activityId) {
                $q->where('activity_id', $activityId);
            })
                ->whereIn('student_id', $studentIds)
                ->get()
                ->keyBy('student_id');

            // Lấy số lượng đã đăng ký cho từng vai trò (query một lần)
            $roleCounts = ActivityRegistration::whereIn('activity_role_id', $activityRoles->keys())
                ->whereIn('status', ['registered', 'attended'])
                ->groupBy('activity_role_id')
                ->select('activity_role_id', DB::raw('count(*) as count'))
                ->get()
                ->keyBy('activity_role_id')
                ->map(fn($item) => $item->count);

            foreach ($request->assignments as $assignment) {
                $studentId = $assignment['student_id'];
                $roleId = $assignment['activity_role_id'];

                // Kiểm tra vai trò có tồn tại không
                $role = $activityRoles->get($roleId);
                if (!$role) {
                    $skipped[] = [
                        'student_id' => $studentId,
                        'reason' => "Vai trò ID {$roleId} không thuộc hoạt động này"
                    ];
                    continue;
                }

                // Kiểm tra sinh viên có tồn tại không
                $student = $students->get($studentId);
                if (!$student) {
                    $skipped[] = [
                        'student_id' => $studentId,
                        'reason' => "Sinh viên ID {$studentId} không tồn tại"
                    ];
                    continue;
                }

                // Kiểm tra sinh viên có thuộc lớp được gán không
                if (!$assignedClassIds->contains($student->class_id)) {
                    $skipped[] = [
                        'student_id' => $studentId,
                        'student_name' => $student->full_name,
                        'reason' => 'Sinh viên không thuộc các lớp được gán cho hoạt động này'
                    ];
                    continue;
                }

                // Kiểm tra sinh viên đã đăng ký vai trò nào chưa
                if ($existingRegistrations->has($studentId)) {
                    $skipped[] = [
                        'student_id' => $studentId,
                        'student_name' => $student->full_name,
                        'reason' => 'Sinh viên đã đăng ký một vai trò khác trong hoạt động này'
                    ];
                    continue;
                }

                // Kiểm tra số lượng slot
                if ($role->max_slots) {
                    $currentCount = $roleCounts->get($roleId, 0);

                    if ($currentCount >= $role->max_slots) {
                        $skipped[] = [
                            'student_id' => $studentId,
                            'student_name' => $student->full_name,
                            'reason' => "Vai trò '{$role->role_name}' đã hết chỗ ({$role->max_slots} slots)"
                        ];
                        continue;
                    }

                    // Cập nhật count (tăng 1)
                    $roleCounts->put($roleId, $currentCount + 1);
                }

                // Tạo đăng ký
                $registration = ActivityRegistration::create([
                    'activity_role_id' => $roleId,
                    'student_id' => $studentId,
                    'status' => 'registered'
                ]);

                // Thêm vào danh sách đã đăng ký (để tránh trùng trong cùng request)
                $existingRegistrations->put($studentId, $registration);

                $assigned[] = [
                    'registration_id' => $registration->registration_id,
                    'student_id' => $studentId,
                    'student_code' => $student->user_code,
                    'student_name' => $student->full_name,
                    'role_name' => $role->role_name,
                    'points_awarded' => $role->points_awarded,
                    'point_type' => $role->point_type
                ];
            }

            DB::commit();

            Log::info('Sinh viên đã được phân công', [
                'activity_id' => $activityId,
                'advisor_id' => $currentUserId,
                'count' => count($assigned)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phân công thành công ' . count($assigned) . ' sinh viên' .
                    (count($skipped) > 0 ? ', bỏ qua ' . count($skipped) : ''),
                'data' => [
                    'total_assigned' => count($assigned),
                    'total_skipped' => count($skipped),
                    'assigned' => $assigned,
                    'skipped' => $skipped
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi phân công', [
                'error' => $e->getMessage(),
                'activity_id' => $activityId
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi phân công: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hủy phân công sinh viên (chỉ với status = 'registered')
     * Role: Advisor only
     */
    public function removeAssignment(Request $request, $activityId, $registrationId)
    {
        $currentUserId = $request->current_user_id;

        $registration = ActivityRegistration::with('role.activity', 'student')
            ->find($registrationId);

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'Đăng ký không tồn tại'
            ], 404);
        }

        // Kiểm tra đăng ký có thuộc hoạt động này không
        if ($registration->role->activity_id != $activityId) {
            return response()->json([
                'success' => false,
                'message' => 'Đăng ký không thuộc hoạt động này'
            ], 400);
        }

        // Kiểm tra quyền (chỉ người tạo HĐ mới được hủy)
        if ($registration->role->activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền hủy phân công này'
            ], 403);
        }

        // Chỉ cho phép hủy nếu status = 'registered'
        if ($registration->status !== 'registered') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể hủy phân công ở trạng thái "đã đăng ký" (status: registered). Trạng thái hiện tại: ' . $registration->status
            ], 400);
        }

        // // Kiểm tra hoạt động đã hoàn thành chưa
        // if ($registration->role->activity->status === 'completed') {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Không thể hủy phân công cho hoạt động đã hoàn thành'
        //     ], 400);
        // }

        DB::beginTransaction();
        try {
            $studentName = $registration->student->full_name;
            $studentCode = $registration->student->user_code;
            $roleName = $registration->role->role_name;

            $registration->delete();

            DB::commit();

            Log::info('Phân công đã bị xóa', [
                'registration_id' => $registrationId,
                'advisor_id' => $currentUserId,
                'student_id' => $registration->student_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hủy phân công thành công',
                'data' => [
                    'student_name' => $studentName,
                    'student_code' => $studentCode,
                    'role_name' => $roleName
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi hủy phân công', [
                'error' => $e->getMessage(),
                'registration_id' => $registrationId
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi hủy phân công: ' . $e->getMessage()
            ], 500);
        }
    }


}