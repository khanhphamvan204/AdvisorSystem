<?php

namespace App\Http\Controllers;
use App\Models\Student;
use App\Models\ActivityRegistration;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PointManagementController extends Controller
{
    /**
     * Xem điểm rèn luyện (theo kỳ) và điểm CTXH (tổng tất cả) của sinh viên
     * Role: Student, Advisor
     * 
     * Parameters:
     * - semester_id (optional): Lọc điểm rèn luyện theo kỳ học
     * - Điểm CTXH: Tính tổng từ tất cả hoạt động (không lọc)
     */
    public function getStudentPoints(Request $request)
    {
        $currentRole = $request->current_role;
        $currentUserId = $request->current_user_id;

        // Validate
        $validator = Validator::make($request->all(), [
            'student_id' => 'required_if:current_role,advisor|integer|exists:Students,student_id',
            'semester_id' => 'nullable|integer|exists:Semesters,semester_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Xác định student_id
        if ($currentRole === 'student') {
            $studentId = $currentUserId;
        } else {
            $studentId = $request->student_id;
            $student = Student::find($studentId);
            if (!$student) {
                return response()->json(['success' => false, 'message' => 'Sinh viên không tồn tại'], 404);
            }
            $class = $student->class;
            if ($class->advisor_id != $currentUserId) {
                return response()->json(['success' => false, 'message' => 'Bạn không có quyền xem thông tin sinh viên này'], 403);
            }
        }

        // Lấy thông tin sinh viên
        $student = Student::find($studentId);

        // Lấy tất cả hoạt động đã tham gia
        $activitiesQuery = ActivityRegistration::where('student_id', $studentId)
            ->where('status', 'attended')
            ->with([
                'role.activity' => function ($q) {
                    $q->select('activity_id', 'title', 'start_time');
                },
                'role' => function ($q) {
                    $q->select('activity_role_id', 'role_name', 'points_awarded', 'point_type', 'activity_id');
                }
            ]);

        $allActivities = $activitiesQuery->get();

        // Lọc điểm rèn luyện theo KỲ HỌC
        $semesterId = $request->semester_id;
        $trainingActivities = $allActivities->filter(function ($reg) use ($semesterId) {
            if ($reg->role->point_type !== 'ren_luyen') {
                return false;
            }

            // Nếu có semester_id, lọc theo kỳ
            if ($semesterId) {
                $semester = \App\Models\Semester::find($semesterId);
                if (!$semester) {
                    return false;
                }

                $activityDate = $reg->role->activity->start_time;
                if (!$activityDate) {
                    return false;
                }

                return $activityDate >= $semester->start_date && $activityDate <= $semester->end_date;
            }

            return true; // Nếu không có semester_id, lấy tất cả
        });

        // Lấy TẤT CẢ điểm CTXH (không lọc theo thời gian)
        $socialActivities = $allActivities->filter(function ($reg) {
            return $reg->role->point_type === 'ctxh';
        });

        // Map dữ liệu chi tiết
        $trainingDetails = $trainingActivities->map(function ($reg) {
            return [
                'activity_title' => $reg->role->activity->title ?? 'N/A',
                'role_name' => $reg->role->role_name ?? 'N/A',
                'points_awarded' => $reg->role->points_awarded ?? 0,
                'point_type' => 'ren_luyen',
                'activity_date' => $reg->role->activity->start_time ?? null
            ];
        })->values();

        $socialDetails = $socialActivities->map(function ($reg) {
            return [
                'activity_title' => $reg->role->activity->title ?? 'N/A',
                'role_name' => $reg->role->role_name ?? 'N/A',
                'points_awarded' => $reg->role->points_awarded ?? 0,
                'point_type' => 'ctxh',
                'activity_date' => $reg->role->activity->start_time ?? null
            ];
        })->values();

        // Tính tổng điểm
        $totalTraining = $trainingActivities->sum('role.points_awarded');
        $totalSocial = $socialActivities->sum('role.points_awarded');

        // Chuẩn bị dữ liệu response
        $semesterInfo = $semesterId ? \App\Models\Semester::find($semesterId) : null;

        $responseData = [
            'student_info' => [
                'student_id' => $studentId,
                'full_name' => $student->full_name,
                'user_code' => $student->user_code
            ],
            'filter_info' => [
                'semester_id' => $semesterId,
                'semester_name' => $semesterInfo ? $semesterInfo->semester_name : null,
                'academic_year' => $semesterInfo ? $semesterInfo->academic_year : null,
            ],
            'summary' => [
                'total_training_points' => $totalTraining, // Tổng điểm rèn luyện (theo kỳ)
                'total_social_points' => $totalSocial,   // Tổng điểm CTXH (tất cả hoạt động)
            ],
            'training_activities' => $trainingDetails, // Danh sách hoạt động rèn luyện (theo kỳ)
            'social_activities' => $socialDetails  // Danh sách hoạt động CTXH (tất cả)
        ];

        return response()->json([
            'success' => true,
            'data' => $responseData
        ], 200);
    }



    /**
     * Lấy danh sách điểm của toàn bộ sinh viên trong lớp
     * Role: Advisor only
     * 
     * Điểm rèn luyện: Tính theo kỳ học (semester_id)
     * Điểm CTXH: Tính tổng từ tất cả hoạt động (không lọc)
     */
    public function getClassPointsSummary(Request $request)
    {
        $currentUserId = $request->current_user_id;

        // Validate
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|integer|exists:Classes,class_id',
            'semester_id' => 'nullable|integer|exists:Semesters,semester_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Kiểm tra quyền
        $class = \App\Models\ClassModel::find($request->class_id);
        if ($class->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem thông tin lớp này'
            ], 403);
        }

        $semesterId = $request->semester_id;

        // Lấy thông tin kỳ học nếu có
        $semester = $semesterId ? \App\Models\Semester::find($semesterId) : null;

        // Lấy danh sách sinh viên và tính điểm
        $students = Student::where('class_id', $request->class_id)
            ->get()
            ->map(function ($student) use ($semesterId, $semester) {
                // Lấy tất cả hoạt động đã tham gia
                $allActivities = ActivityRegistration::where('student_id', $student->student_id)
                    ->where('status', 'attended')
                    ->with(['role.activity', 'role'])
                    ->get();

                // Lọc điểm rèn luyện theo KỲ HỌC
                $trainingPoints = $allActivities->filter(function ($reg) use ($semesterId, $semester) {
                    if ($reg->role->point_type !== 'ren_luyen') {
                        return false;
                    }

                    // Nếu có semester_id, lọc theo kỳ
                    if ($semesterId && $semester) {
                        $activityDate = $reg->role->activity->start_time;
                        if (!$activityDate) {
                            return false;
                        }

                        return $activityDate >= $semester->start_date && $activityDate <= $semester->end_date;
                    }

                    return true; // Nếu không có semester_id, lấy tất cả
                })->sum('role.points_awarded');

                // Lấy TẤT CẢ điểm CTXH (không lọc theo thời gian)
                $socialPoints = $allActivities->filter(function ($reg) {
                    return $reg->role->point_type === 'ctxh';
                })->sum('role.points_awarded');

                return [
                    'student_id' => $student->student_id,
                    'user_code' => $student->user_code,
                    'full_name' => $student->full_name,
                    'total_training_points' => $trainingPoints, // Điểm rèn luyện (theo kỳ)
                    'total_social_points' => $socialPoints,   // Điểm CTXH (tất cả hoạt động)
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'class_name' => $class->class_name,
                'filter_info' => [
                    'semester_id' => $semesterId,
                    'semester_name' => $semester ? $semester->semester_name : null,
                    'academic_year' => $semester ? $semester->academic_year : null,
                ],
                'total_students' => $students->count(),
                'students' => $students
            ]
        ], 200);
    }
}