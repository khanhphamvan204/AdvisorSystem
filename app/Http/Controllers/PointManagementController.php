<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Semester;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\PointCalculationService;
use Illuminate\Support\Facades\Log;
class PointManagementController extends Controller
{
    /**
     * Xem điểm rèn luyện (theo kỳ) và điểm CTXH (tổng tất cả) của sinh viên
     * Role: Student, Advisor
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

        // === XỬ LÝ HỌC KỲ ===
        $semesterId = $request->semester_id;
        if (!$semesterId) {
            // Nếu không có semester_id, tìm học kỳ hiện tại
            $currentSemester = Semester::where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();

            if (!$currentSemester) {
                // Nếu không có kỳ hiện tại, lấy kỳ gần nhất
                $currentSemester = Semester::orderBy('end_date', 'desc')->first();
            }

            if (!$currentSemester) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy học kỳ nào để tính điểm'], 404);
            }
            $semesterId = $currentSemester->semester_id;
        }

        $semester = Semester::find($semesterId);

        // === GỌI SERVICE ĐỂ TÍNH ĐIỂM ===
        try {
            // 1. Tính điểm Rèn luyện (THEO KỲ)
            $totalTraining = PointCalculationService::calculateTrainingPoints($studentId, $semesterId);
            $trainingDetails = PointCalculationService::getTrainingActivitiesDetail($studentId, $semesterId);

            // 2. Tính điểm CTXH (TỔNG TẤT CẢ)
            // (Truyền `null` để Service lấy tất cả, không lọc theo kỳ)
            $totalSocial = PointCalculationService::calculateSocialPoints($studentId, null);
            $socialDetails = PointCalculationService::getSocialActivitiesDetail($studentId, null);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tính toán điểm: ' . $e->getMessage()
            ], 500);
        }

        $responseData = [
            'student_info' => [
                'student_id' => $studentId,
                'full_name' => $student->full_name,
                'user_code' => $student->user_code
            ],
            'filter_info' => [
                'semester_id' => $semesterId,
                'semester_name' => $semester->semester_name,
                'academic_year' => $semester->academic_year,
                'note' => $request->semester_id ? 'Lọc theo học kỳ đã chọn' : 'Lọc theo học kỳ hiện tại/gần nhất'
            ],
            'summary' => [
                'total_training_points' => $totalTraining,
                'total_social_points' => $totalSocial,
            ],
            'training_activities' => $trainingDetails,
            'social_activities' => $socialDetails
        ];

        return response()->json([
            'success' => true,
            'data' => $responseData
        ], 200);
    }



    /**
     * Lấy danh sách điểm của toàn bộ sinh viên trong lớp
     * (Đã sửa để dùng PointCalculationService)
     */
    public function getClassPointsSummary(Request $request)
    {
        $currentUserId = $request->current_user_id;

        $validator = Validator::make($request->all(), [
            'class_id' => 'required|integer|exists:Classes,class_id',
            'semester_id' => 'nullable|integer|exists:Semesters,semester_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ', 'errors' => $validator->errors()], 422);
        }

        $class = ClassModel::find($request->class_id);
        if ($class->advisor_id != $currentUserId) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền xem thông tin lớp này'], 403);
        }

        // === XỬ LÝ HỌC KỲ ===
        $semesterId = $request->semester_id;
        if (!$semesterId) {
            $currentSemester = Semester::where('start_date', '<=', now())->where('end_date', '>=', now())->first();
            if (!$currentSemester) {
                $currentSemester = Semester::orderBy('end_date', 'desc')->first();
            }
            if (!$currentSemester) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy học kỳ nào để tính điểm'], 404);
            }
            $semesterId = $currentSemester->semester_id;
        }

        $semester = Semester::find($semesterId);

        // Lấy danh sách sinh viên
        $students = Student::where('class_id', $request->class_id)
            ->get()
            ->map(function ($student) use ($semesterId) {

                try {
                    // 1. Tính điểm Rèn luyện (THEO KỲ)
                    $trainingPoints = PointCalculationService::calculateTrainingPoints($student->student_id, $semesterId);

                    // 2. Tính điểm CTXH (TỔNG TẤT CẢ)
                    $socialPoints = PointCalculationService::calculateSocialPoints($student->student_id, null);

                    return [
                        'student_id' => $student->student_id,
                        'user_code' => $student->user_code,
                        'full_name' => $student->full_name,
                        'total_training_points' => $trainingPoints,
                        'total_social_points' => $socialPoints,
                    ];
                } catch (\Exception $e) {
                    // Nếu lỗi (ví dụ SV mới), trả về null để lọc ra sau
                    return null;
                }
            })
            ->filter(); // Lọc bỏ những sinh viên bị lỗi

        return response()->json([
            'success' => true,
            'data' => [
                'class_name' => $class->class_name,
                'filter_info' => [
                    'semester_id' => $semesterId,
                    'semester_name' => $semester->semester_name,
                    'academic_year' => $semester->academic_year,
                    'note' => $request->semester_id ? 'Lọc theo học kỳ đã chọn' : 'Lọc theo học kỳ hiện tại/gần nhất'
                ],
                'total_students' => $students->count(),
                'students' => $students->values()
            ]
        ], 200);
    }
    public function batchUpdateClassPoints(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'semester_id' => 'required|exists:Semesters,semester_id',
            'class_id' => 'required|exists:Classes,class_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $advisorId = $request->current_user_id;
        $semesterId = $request->semester_id;
        $classId = $request->class_id;

        // Kiểm tra quyền: Advisor chỉ cập nhật cho lớp mình quản lý
        $class = ClassModel::find($classId);
        if (!$class || $class->advisor_id != $advisorId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ được cập nhật điểm cho lớp mình quản lý'
            ], 403);
        }

        try {
            // Lấy danh sách sinh viên trong lớp
            $studentIds = Student::where('class_id', $classId)->pluck('student_id')->toArray();

            if (empty($studentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lớp không có sinh viên nào'
                ], 404);
            }

            // Cập nhật hàng loạt
            $result = PointCalculationService::batchUpdateSemesterPoints($semesterId, $studentIds);

            Log::info('Batch class points updated', [
                'advisor_id' => $advisorId,
                'class_id' => $classId,
                'semester_id' => $semesterId,
                'success_count' => $result['summary']['success_count'],
                'error_count' => $result['summary']['error_count']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật điểm hàng loạt thành công',
                'data' => [
                    'class_name' => $class->class_name,
                    'semester_id' => $semesterId,
                    'results' => $result['success'],
                    'errors' => $result['errors'],
                    'summary' => $result['summary']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to batch update class points', [
                'advisor_id' => $advisorId,
                'class_id' => $classId,
                'semester_id' => $semesterId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật hàng loạt: ' . $e->getMessage()
            ], 500);
        }
    }
}