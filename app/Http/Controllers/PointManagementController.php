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
     * Xem tổng điểm rèn luyện, điểm CTXH của sinh viên từ tất cả hoạt động
     * Role: Student, Advisor
     */
    public function getStudentPoints(Request $request)
    {
        $currentRole = $request->current_role;
        $currentUserId = $request->current_user_id;

        // Validate: Bỏ 'semester_id'
        $validator = Validator::make($request->all(), [
            'student_id' => 'required_if:current_role,advisor|integer|exists:Students,student_id',
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

        // Lấy chi tiết TẤT CẢ các hoạt động đã tham gia (bỏ lọc theo học kỳ)
        $activities = ActivityRegistration::where('student_id', $studentId)
            ->where('status', 'attended') // Chỉ tính hoạt động đã tham gia
            ->with([
                'role.activity' => function ($q) {
                    $q->select('activity_id', 'title', 'start_time');
                },
                'role' => function ($q) {
                    $q->select('activity_role_id', 'role_name', 'points_awarded', 'point_type', 'activity_id');
                }
            ])
            ->get()
            ->map(function ($reg) {
                return [
                    'activity_title' => $reg->role->activity->title ?? 'N/A',
                    'role_name' => $reg->role->role_name ?? 'N/A',
                    'points_awarded' => $reg->role->points_awarded ?? 0,
                    'point_type' => $reg->role->point_type ?? 'N/A',
                    'activity_date' => $reg->role->activity->start_time ?? null
                ];
            });

        // Tính tổng điểm từ TẤT CẢ hoạt động
        $totalCtxh = $activities->where('point_type', 'ctxh')->sum('points_awarded');
        $totalRenLuyen = $activities->where('point_type', 'ren_luyen')->sum('points_awarded');

        // Chuẩn bị dữ liệu response
        $responseData = [
            'student_info' => [
                'student_id' => $studentId,
                'full_name' => $student->full_name,
                'user_code' => $student->user_code
            ],
            'summary' => [
                'total_training_points' => $totalRenLuyen, // Tổng điểm rèn luyện
                'total_social_points' => $totalCtxh,   // Tổng điểm CTXH
            ],
            'activities' => $activities // Danh sách chi tiết
        ];

        return response()->json([
            'success' => true,
            'data' => $responseData
        ], 200);
    }



    /**
     * Lấy danh sách tổng điểm của toàn bộ sinh viên trong lớp
     * Role: Advisor only
     */
    public function getClassPointsSummary(Request $request)
    {
        $currentUserId = $request->current_user_id;

        // Validate: Bỏ 'semester_id'
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|integer|exists:Classes,class_id',
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

        // Lấy danh sách sinh viên
        $students = Student::where('class_id', $request->class_id)
            ->get()
            ->map(function ($student) {
                // Tính điểm từ TẤT CẢ hoạt động (bỏ lọc theo học kỳ)
                $activities = ActivityRegistration::where('student_id', $student->student_id)
                    ->where('status', 'attended')
                    ->with('role')
                    ->get();

                $ctxhFromActivities = $activities->where('role.point_type', 'ctxh')->sum('role.points_awarded');
                $renLuyenFromActivities = $activities->where('role.point_type', 'ren_luyen')->sum('role.points_awarded');

                return [
                    'student_id' => $student->student_id,
                    'user_code' => $student->user_code,
                    'full_name' => $student->full_name,
                    'total_training_points' => $renLuyenFromActivities, // Tổng rèn luyện
                    'total_social_points' => $ctxhFromActivities,   // Tổng CTXH
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'class_name' => $class->class_name,
                'total_students' => $students->count(),
                'students' => $students
            ]
        ], 200);
    }
}