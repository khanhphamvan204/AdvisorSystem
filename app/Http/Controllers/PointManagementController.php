<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\SemesterReport;
use App\Models\Semester;
use App\Models\ActivityRegistration;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PointManagementController extends Controller
{
    /**
     * Xem thông tin điểm rèn luyện, điểm CTXH của sinh viên
     * Role: Student, Advisor
     */
    public function getStudentPoints(Request $request)
    {
        $currentRole = $request->current_role;
        $currentUserId = $request->current_user_id;

        // Validate input
        $validator = Validator::make($request->all(), [
            'student_id' => 'required_if:current_role,advisor|integer|exists:Students,student_id',
            'semester_id' => 'nullable|integer|exists:Semesters,semester_id'
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

            // Advisor chỉ xem được sinh viên trong lớp mình phụ trách
            $student = Student::find($studentId);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sinh viên không tồn tại'
                ], 404);
            }

            $class = $student->class;
            if ($class->advisor_id != $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem thông tin sinh viên này'
                ], 403);
            }
        }

        // Lấy semester_id
        $semesterId = $request->semester_id;

        // Nếu không có semester_id, lấy học kỳ hiện tại
        if (!$semesterId) {
            $currentSemester = Semester::where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();

            if (!$currentSemester) {
                // Lấy học kỳ gần nhất
                $currentSemester = Semester::orderBy('end_date', 'desc')->first();
            }

            $semesterId = $currentSemester ? $currentSemester->semester_id : null;
        }

        if (!$semesterId) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy học kỳ'
            ], 404);
        }

        // Lấy thông tin học kỳ
        $semester = Semester::find($semesterId);
        if (!$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Học kỳ không tồn tại'
            ], 404);
        }

        // Lấy chi tiết các hoạt động đã tham gia trong học kỳ
        $activities = ActivityRegistration::where('student_id', $studentId)
            ->where('status', 'attended')
            ->whereHas('role.activity', function ($q) use ($semester) {
                $q->whereBetween('start_time', [$semester->start_date, $semester->end_date]);
            })
            ->with([
                'role.activity' => function ($q) {
                    $q->select('activity_id', 'title', 'start_time');
                },
                'role'
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

        // Tính tổng điểm từ hoạt động
        $ctxhFromActivities = $activities->where('point_type', 'ctxh')->sum('points_awarded');
        $renLuyenFromActivities = $activities->where('point_type', 'ren_luyen')->sum('points_awarded');

        // Lấy báo cáo học kỳ (nếu có)
        $report = SemesterReport::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->first();

        // Lấy thông tin sinh viên
        $student = Student::find($studentId);

        // Chuẩn bị dữ liệu response
        $responseData = [
            'student_info' => [
                'student_id' => $studentId,
                'full_name' => $student->full_name,
                'user_code' => $student->user_code
            ],
            'semester' => [
                'semester_id' => $semester->semester_id,
                'semester_name' => $semester->semester_name,
                'academic_year' => $semester->academic_year
            ],
            'summary' => [
                'training_point_from_activities' => $renLuyenFromActivities,
                'social_point_from_activities' => $ctxhFromActivities,
                'has_official_report' => $report ? true : false
            ],
            'activities' => $activities
        ];

        // Nếu có báo cáo chính thức, thêm thông tin báo cáo
        if ($report) {
            $responseData['summary']['training_point_summary'] = $report->training_point_summary;
            $responseData['summary']['social_point_summary'] = $report->social_point_summary;
            $responseData['outcome'] = $report->outcome;
        } else {
            $responseData['summary']['training_point_summary'] = null;
            $responseData['summary']['social_point_summary'] = null;
            $responseData['outcome'] = 'Chưa có báo cáo chính thức';
            $responseData['note'] = 'Điểm hiển thị là tổng điểm từ các hoạt động đã tham gia. Chưa có điểm đánh giá chính thức từ GVCN.';
        }

        return response()->json([
            'success' => true,
            'data' => $responseData
        ], 200);
    }

    /**
     * Cập nhật điểm rèn luyện, điểm CTXH cho sinh viên
     * Role: Advisor only
     */
    public function updateStudentPoints(Request $request)
    {
        $currentUserId = $request->current_user_id;

        // Validate input
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|integer|exists:Students,student_id',
            'semester_id' => 'required|integer|exists:Semesters,semester_id',
            'training_point_summary' => 'nullable|integer|min:0|max:100',
            'social_point_summary' => 'nullable|integer|min:0|max:100',
            'outcome' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Kiểm tra quyền: Advisor chỉ cập nhật được sinh viên trong lớp mình
        $student = Student::find($request->student_id);
        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Sinh viên không tồn tại'
            ], 404);
        }

        $class = $student->class;
        if ($class->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền cập nhật điểm cho sinh viên này'
            ], 403);
        }

        // Tìm hoặc tạo mới báo cáo học kỳ
        $report = SemesterReport::updateOrCreate(
            [
                'student_id' => $request->student_id,
                'semester_id' => $request->semester_id
            ],
            [
                'training_point_summary' => $request->training_point_summary ?? 0,
                'social_point_summary' => $request->social_point_summary ?? 0,
                'outcome' => $request->outcome
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật điểm thành công',
            'data' => $report
        ], 200);
    }

    /**
     * Lấy danh sách điểm của toàn bộ sinh viên trong lớp
     * Role: Advisor only
     */
    public function getClassPointsSummary(Request $request)
    {
        $currentUserId = $request->current_user_id;

        // Validate
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|integer|exists:Classes,class_id',
            'semester_id' => 'required|integer|exists:Semesters,semester_id'
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

        // Lấy thông tin học kỳ
        $semester = Semester::find($request->semester_id);

        // Lấy danh sách sinh viên và điểm
        $students = Student::where('class_id', $request->class_id)
            ->with([
                'semesterReports' => function ($q) use ($request) {
                    $q->where('semester_id', $request->semester_id);
                }
            ])
            ->get()
            ->map(function ($student) use ($request, $semester) {
                $report = $student->semesterReports->first();

                // Tính điểm từ hoạt động
                $activities = ActivityRegistration::where('student_id', $student->student_id)
                    ->where('status', 'attended')
                    ->whereHas('role.activity', function ($q) use ($semester) {
                    $q->whereBetween('start_time', [$semester->start_date, $semester->end_date]);
                })
                    ->with('role')
                    ->get();

                $ctxhFromActivities = $activities->where('role.point_type', 'ctxh')->sum('role.points_awarded');
                $renLuyenFromActivities = $activities->where('role.point_type', 'ren_luyen')->sum('role.points_awarded');

                return [
                    'student_id' => $student->student_id,
                    'user_code' => $student->user_code,
                    'full_name' => $student->full_name,
                    'training_point_from_activities' => $renLuyenFromActivities,
                    'social_point_from_activities' => $ctxhFromActivities,
                    'training_point_summary' => $report->training_point_summary ?? null,
                    'social_point_summary' => $report->social_point_summary ?? null,
                    'outcome' => $report->outcome ?? 'Chưa có báo cáo',
                    'has_official_report' => $report ? true : false
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'class_name' => $class->class_name,
                'semester_id' => $request->semester_id,
                'total_students' => $students->count(),
                'students' => $students
            ]
        ], 200);
    }
}