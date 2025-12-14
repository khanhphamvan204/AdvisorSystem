<?php

namespace App\Http\Controllers;

use App\Models\CourseGrade;
use App\Models\Student;
use App\Models\Course;
use App\Models\Semester;
use App\Services\AcademicMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\GradeImportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;


class GradeController extends Controller
{
    protected $gradeImportService;

    public function __construct(GradeImportService $gradeImportService)
    {
        $this->gradeImportService = $gradeImportService;
    }
    /**
     * [STUDENT] Xem điểm của chính mình
     * GET /api/grades/my-grades?semester_id={semester_id}
     */
    public function getMyGrades(Request $request)
    {

        $studentId = $request->current_user_id;
        $semesterId = $request->query('semester_id');

        try {
            $query = CourseGrade::with(['course', 'semester'])
                ->where('student_id', $studentId);

            if ($semesterId) {
                $query->where('semester_id', $semesterId);
            }

            $grades = $query->orderBy('semester_id', 'desc')
                ->get()
                ->map(function ($grade) {
                    return [
                        'grade_id' => $grade->grade_id,
                        'course_code' => $grade->course->course_code,
                        'course_name' => $grade->course->course_name,
                        'credits' => $grade->course->credits,
                        'semester' => $grade->semester->semester_name . ' ' . $grade->semester->academic_year,
                        'semester_id' => $grade->semester->semester_id,
                        'grade_10' => $grade->grade_value,
                        'grade_letter' => $grade->grade_letter,
                        'grade_4' => $grade->grade_4_scale,
                        'status' => $grade->status
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'grades' => $grades,
                    'summary' => [
                        'total_courses' => $grades->count(),
                        'passed_courses' => $grades->where('status', 'passed')->count(),
                        'failed_courses' => $grades->where('status', 'failed')->count(),
                        'studying_courses' => $grades->where('status', 'studying')->count()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get student grades', [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy điểm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADVISOR/ADMIN] Xem điểm của sinh viên
     * GET /api/grades/student/{student_id}?semester_id={semester_id}
     */
    public function getStudentGrades(Request $request, $studentId)
    {
        $userId = $request->current_user_id;
        $userRole = $request->current_role;
        $semesterId = $request->query('semester_id');

        // Kiểm tra quyền
        $student = Student::with('class.faculty')->find($studentId);
        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sinh viên'
            ], 404);
        }

        // Kiểm tra quyền truy cập
        if ($userRole === 'advisor') {
            // Advisor chỉ xem được sinh viên trong lớp mình quản lý
            if ($student->class->advisor_id != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ được xem điểm sinh viên trong lớp mình quản lý'
                ], 403);
            }
        } elseif ($userRole === 'admin') {
            // Admin chỉ xem được sinh viên trong khoa mình quản lý
            $admin = \App\Models\Advisor::find($userId);
            if (!$admin || !$admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin chưa được gán vào khoa nào'
                ], 403);
            }

            if (!$student->class || $student->class->faculty_id != $admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ được xem điểm sinh viên trong khoa mình quản lý'
                ], 403);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền truy cập'
            ], 403);
        }

        try {
            $query = CourseGrade::with(['course', 'semester'])
                ->where('student_id', $studentId);

            if ($semesterId) {
                $query->where('semester_id', $semesterId);
            }

            $grades = $query->orderBy('semester_id', 'desc')
                ->get()
                ->map(function ($grade) {
                    return [
                        'grade_id' => $grade->grade_id,
                        'course_code' => $grade->course->course_code,
                        'course_name' => $grade->course->course_name,
                        'credits' => $grade->course->credits,
                        'semester' => $grade->semester->semester_name . ' ' . $grade->semester->academic_year,
                        'grade_10' => $grade->grade_value,
                        'grade_letter' => $grade->grade_letter,
                        'grade_4' => $grade->grade_4_scale,
                        'status' => $grade->status
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'student_info' => [
                        'student_id' => $student->student_id,
                        'user_code' => $student->user_code,
                        'full_name' => $student->full_name,
                        'class_name' => $student->class->class_name
                    ],
                    'grades' => $grades,
                    'summary' => [
                        'total_courses' => $grades->count(),
                        'passed_courses' => $grades->where('status', 'passed')->count(),
                        'failed_courses' => $grades->where('status', 'failed')->count()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get student grades', [
                'user_id' => $userId,
                'user_role' => $userRole,
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy điểm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Nhập điểm cho sinh viên
     * POST /api/grades
     * CHỈ ADMIN MỚI ĐƯỢC NHẬP ĐIỂM (CHỈ CHO CÁC LỚP THUỘC KHOA MÌNH QUẢN LÝ)
     */
    public function store(Request $request)
    {
        // Kiểm tra role phải là admin
        if ($request->current_role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Admin mới có quyền nhập điểm'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:Students,student_id',
            'course_id' => 'required|exists:Courses,course_id',
            'semester_id' => 'required|exists:Semesters,semester_id',
            'grade_value' => 'required|numeric|min:0|max:10'
        ], [
            'student_id.required' => 'Mã sinh viên là bắt buộc',
            'student_id.exists' => 'Sinh viên không tồn tại',
            'course_id.required' => 'Mã môn học là bắt buộc',
            'course_id.exists' => 'Môn học không tồn tại',
            'semester_id.required' => 'Học kỳ là bắt buộc',
            'semester_id.exists' => 'Học kỳ không tồn tại',
            'grade_value.required' => 'Điểm là bắt buộc',
            'grade_value.numeric' => 'Điểm phải là số',
            'grade_value.min' => 'Điểm phải từ 0 đến 10',
            'grade_value.max' => 'Điểm phải từ 0 đến 10'
        ]);

        if ($validator->fails()) {
            Log::warning('Grade creation validation failed', [
                'admin_id' => $request->current_user_id,
                'errors' => $validator->errors(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Lấy thông tin admin và kiểm tra unit_id
        $admin = \App\Models\Advisor::find($request->current_user_id);
        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Admin chưa được gán vào khoa nào'
            ], 403);
        }

        // Kiểm tra sinh viên thuộc lớp nào và lớp đó thuộc khoa nào
        $student = Student::with('class.faculty')->find($request->student_id);
        if (!$student || !$student->class) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin sinh viên hoặc lớp'
            ], 404);
        }

        // Kiểm tra lớp của sinh viên có thuộc khoa của admin không
        if ($student->class->faculty_id != $admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ được nhập điểm cho sinh viên trong các lớp thuộc khoa mình quản lý'
            ], 403);
        }

        // Kiểm tra xem đã có điểm chưa
        $existingGrade = CourseGrade::where('student_id', $request->student_id)
            ->where('course_id', $request->course_id)
            ->where('semester_id', $request->semester_id)
            ->first();

        if ($existingGrade) {
            return response()->json([
                'success' => false,
                'message' => 'Sinh viên đã có điểm môn này trong học kỳ. Vui lòng sử dụng API cập nhật.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Quy đổi điểm
            $converted = AcademicMonitoringService::convertGrade($request->grade_value);

            // Tạo điểm mới
            $grade = CourseGrade::create([
                'student_id' => $request->student_id,
                'course_id' => $request->course_id,
                'semester_id' => $request->semester_id,
                'grade_value' => $request->grade_value,
                'grade_letter' => $converted['letter'],
                'grade_4_scale' => $converted['scale4'],
                'status' => $request->grade_value >= 4.0 ? 'passed' : 'failed'
            ]);

            DB::commit();

            $grade->load(['student', 'course', 'semester']);

            Log::info('Grade created by admin', [
                'admin_id' => $request->current_user_id,
                'grade_id' => $grade->grade_id,
                'student_id' => $request->student_id,
                'course_id' => $request->course_id,
                'grade_value' => $request->grade_value
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nhập điểm thành công',
                'data' => [
                    'grade' => [
                        'grade_id' => $grade->grade_id,
                        'student_name' => $grade->student->full_name,
                        'course_name' => $grade->course->course_name,
                        'semester' => $grade->semester->semester_name . ' ' . $grade->semester->academic_year,
                        'grade_10' => $grade->grade_value,
                        'grade_letter' => $grade->grade_letter,
                        'grade_4' => $grade->grade_4_scale,
                        'status' => $grade->status
                    ]
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create grade', [
                'admin_id' => $request->current_user_id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi nhập điểm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Cập nhật điểm
     * PUT /api/grades/{grade_id}
     * CHỈ ADMIN MỚI ĐƯỢC CẬP NHẬT ĐIỂM (CHỈ CHO CÁC LỚP THUỘC KHOA MÌNH QUẢN LÝ)
     */
    public function update(Request $request, $gradeId)
    {
        // Kiểm tra role phải là admin
        if ($request->current_role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Admin mới có quyền cập nhật điểm'
            ], 403);
        }

        $grade = CourseGrade::with('student.class.faculty')->find($gradeId);

        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy điểm'
            ], 404);
        }

        // Lấy thông tin admin và kiểm tra unit_id
        $admin = \App\Models\Advisor::find($request->current_user_id);
        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Admin chưa được gán vào khoa nào'
            ], 403);
        }

        // Kiểm tra lớp của sinh viên có thuộc khoa của admin không
        if (!$grade->student || !$grade->student->class || $grade->student->class->faculty_id != $admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ được cập nhật điểm cho sinh viên trong các lớp thuộc khoa mình quản lý'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'grade_value' => 'required|numeric|min:0|max:10'
        ], [
            'grade_value.required' => 'Điểm là bắt buộc',
            'grade_value.numeric' => 'Điểm phải là số',
            'grade_value.min' => 'Điểm phải từ 0 đến 10',
            'grade_value.max' => 'Điểm phải từ 0 đến 10'
        ]);

        if ($validator->fails()) {
            Log::warning('Grade update validation failed', [
                'admin_id' => $request->current_user_id,
                'grade_id' => $gradeId,
                'errors' => $validator->errors(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldGrade = $grade->grade_value;

            // Quy đổi điểm mới
            $converted = AcademicMonitoringService::convertGrade($request->grade_value);

            $grade->grade_value = $request->grade_value;
            $grade->grade_letter = $converted['letter'];
            $grade->grade_4_scale = $converted['scale4'];
            $grade->status = $request->grade_value >= 4.0 ? 'passed' : 'failed';
            $grade->save();

            DB::commit();

            Log::info('Grade updated by admin', [
                'admin_id' => $request->current_user_id,
                'grade_id' => $grade->grade_id,
                'old_grade' => $oldGrade,
                'new_grade' => $request->grade_value
            ]);

            $grade->load(['student', 'course', 'semester']);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật điểm thành công',
                'data' => [
                    'grade' => [
                        'grade_id' => $grade->grade_id,
                        'student_name' => $grade->student->full_name,
                        'course_name' => $grade->course->course_name,
                        'semester' => $grade->semester->semester_name . ' ' . $grade->semester->academic_year,
                        'grade_10' => $grade->grade_value,
                        'grade_letter' => $grade->grade_letter,
                        'grade_4' => $grade->grade_4_scale,
                        'status' => $grade->status
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update grade', [
                'admin_id' => $request->current_user_id,
                'grade_id' => $gradeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật điểm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Nhập điểm hàng loạt
     * POST /api/grades/batch-import
     * CHỈ ADMIN MỚI ĐƯỢC NHẬP ĐIỂM HÀNG LOẠT (CHỈ CHO CÁC LỚP THUỘC KHOA MÌNH QUẢN LÝ)
     */
    public function batchImport(Request $request)
    {
        // Kiểm tra role phải là admin
        if ($request->current_role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Admin mới có quyền nhập điểm hàng loạt'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'semester_id' => 'required|exists:Semesters,semester_id',
            'course_id' => 'required|exists:Courses,course_id',
            'grades' => 'required|array|min:1',
            'grades.*.student_id' => 'required|exists:Students,student_id',
            'grades.*.grade_value' => 'required|numeric|min:0|max:10'
        ], [
            'semester_id.required' => 'Học kỳ là bắt buộc',
            'semester_id.exists' => 'Học kỳ không tồn tại',
            'course_id.required' => 'Mã môn học là bắt buộc',
            'course_id.exists' => 'Môn học không tồn tại',
            'grades.required' => 'Danh sách điểm là bắt buộc',
            'grades.array' => 'Danh sách điểm phải là mảng',
            'grades.min' => 'Danh sách điểm phải có ít nhất 1 bản ghi',
            'grades.*.student_id.required' => 'Mã sinh viên là bắt buộc',
            'grades.*.student_id.exists' => 'Sinh viên không tồn tại',
            'grades.*.grade_value.required' => 'Điểm là bắt buộc',
            'grades.*.grade_value.numeric' => 'Điểm phải là số',
            'grades.*.grade_value.min' => 'Điểm phải từ 0 đến 10',
            'grades.*.grade_value.max' => 'Điểm phải từ 0 đến 10'
        ]);

        if ($validator->fails()) {
            Log::warning('Batch import validation failed', [
                'admin_id' => $request->current_user_id,
                'errors' => $validator->errors(),
                'data' => $request->except(['grades'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Lấy thông tin admin và kiểm tra unit_id
        $admin = \App\Models\Advisor::find($request->current_user_id);
        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Admin chưa được gán vào khoa nào'
            ], 403);
        }

        $semesterId = $request->semester_id;
        $courseId = $request->course_id;
        $gradesData = $request->grades;

        $results = [
            'success' => [],
            'errors' => [],
            'updated' => []
        ];

        DB::beginTransaction();
        try {
            foreach ($gradesData as $gradeData) {
                $studentId = $gradeData['student_id'];
                $gradeValue = $gradeData['grade_value'];

                // Kiểm tra sinh viên thuộc lớp nào và lớp đó thuộc khoa nào
                $student = Student::with('class.faculty')->find($studentId);
                if (!$student || !$student->class || $student->class->faculty_id != $admin->unit_id) {
                    $results['errors'][] = [
                        'student_id' => $studentId,
                        'message' => 'Sinh viên không thuộc khoa bạn quản lý'
                    ];
                    continue;
                }

                // Kiểm tra xem đã có điểm chưa
                $existingGrade = CourseGrade::where('student_id', $studentId)
                    ->where('course_id', $courseId)
                    ->where('semester_id', $semesterId)
                    ->first();

                $converted = AcademicMonitoringService::convertGrade($gradeValue);

                if ($existingGrade) {
                    // Cập nhật điểm cũ
                    $existingGrade->grade_value = $gradeValue;
                    $existingGrade->grade_letter = $converted['letter'];
                    $existingGrade->grade_4_scale = $converted['scale4'];
                    $existingGrade->status = $gradeValue >= 4.0 ? 'passed' : 'failed';
                    $existingGrade->save();

                    $student = Student::find($studentId);
                    $results['updated'][] = [
                        'student_id' => $studentId,
                        'user_code' => $student->user_code,
                        'full_name' => $student->full_name,
                        'grade_value' => $gradeValue,
                        'status' => 'updated'
                    ];
                } else {
                    // Tạo mới
                    $grade = CourseGrade::create([
                        'student_id' => $studentId,
                        'course_id' => $courseId,
                        'semester_id' => $semesterId,
                        'grade_value' => $gradeValue,
                        'grade_letter' => $converted['letter'],
                        'grade_4_scale' => $converted['scale4'],
                        'status' => $gradeValue >= 4.0 ? 'passed' : 'failed'
                    ]);

                    $student = Student::find($studentId);
                    $results['success'][] = [
                        'student_id' => $studentId,
                        'user_code' => $student->user_code,
                        'full_name' => $student->full_name,
                        'grade_value' => $gradeValue,
                        'status' => 'created'
                    ];
                }
            }

            DB::commit();

            Log::info('Batch grades imported by admin', [
                'admin_id' => $request->current_user_id,
                'course_id' => $courseId,
                'semester_id' => $semesterId,
                'total_grades' => count($gradesData),
                'created' => count($results['success']),
                'updated' => count($results['updated'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nhập điểm hàng loạt thành công',
                'data' => [
                    'results' => array_merge($results['success'], $results['updated']),
                    'errors' => $results['errors'],
                    'summary' => [
                        'total_processed' => count($gradesData),
                        'created' => count($results['success']),
                        'updated' => count($results['updated']),
                        'errors' => count($results['errors'])
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to batch import grades', [
                'admin_id' => $request->current_user_id,
                'error' => $e->getMessage(),
                'course_id' => $courseId,
                'semester_id' => $semesterId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi nhập điểm hàng loạt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Xóa điểm
     * DELETE /api/grades/{grade_id}
     * CHỈ ADMIN MỚI ĐƯỢC XÓA ĐIỂM (CHỈ CHO CÁC LỚP THUỘC KHOA MÌNH QUẢN LÝ)
     */
    public function destroy(Request $request, $gradeId)
    {
        // Kiểm tra role phải là admin
        if ($request->current_role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Admin mới có quyền xóa điểm'
            ], 403);
        }

        $grade = CourseGrade::with('student.class.faculty')->find($gradeId);

        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy điểm'
            ], 404);
        }

        // Lấy thông tin admin và kiểm tra unit_id
        $admin = \App\Models\Advisor::find($request->current_user_id);
        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Admin chưa được gán vào khoa nào'
            ], 403);
        }

        // Kiểm tra lớp của sinh viên có thuộc khoa của admin không
        if (!$grade->student || !$grade->student->class || $grade->student->class->faculty_id != $admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ được xóa điểm cho sinh viên trong các lớp thuộc khoa mình quản lý'
            ], 403);
        }

        try {
            $studentId = $grade->student_id;
            $courseId = $grade->course_id;

            $grade->delete();

            Log::info('Grade deleted by admin', [
                'admin_id' => $request->current_user_id,
                'grade_id' => $gradeId,
                'student_id' => $studentId,
                'course_id' => $courseId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa điểm thành công'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete grade', [
                'admin_id' => $request->current_user_id,
                'grade_id' => $gradeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa điểm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADVISOR/ADMIN] Xuất điểm lớp theo học kỳ
     * GET /api/grades/export-class-grades/{class_id}/{semester_id}
     */
    public function exportClassGrades(Request $request, $classId, $semesterId)
    {
        $userId = $request->current_user_id;
        $userRole = $request->current_role;

        // Kiểm tra quyền
        $class = \App\Models\ClassModel::with('faculty')->find($classId);
        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy lớp'
            ], 404);
        }

        // Kiểm tra quyền truy cập
        if ($userRole === 'advisor') {
            // Advisor chỉ xuất được lớp mình quản lý
            if ($class->advisor_id != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ được xuất điểm lớp mình quản lý'
                ], 403);
            }
        } elseif ($userRole === 'admin') {
            // Admin chỉ xuất được lớp trong khoa mình quản lý
            $admin = \App\Models\Advisor::find($userId);
            if (!$admin || !$admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin chưa được gán vào khoa nào'
                ], 403);
            }

            if ($class->faculty_id != $admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lớp này không thuộc khoa bạn quản lý'
                ], 403);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền truy cập'
            ], 403);
        }

        try {
            $students = Student::where('class_id', $classId)->get();
            $semester = Semester::find($semesterId);

            $gradesData = $students->map(function ($student) use ($semesterId) {
                $grades = CourseGrade::with('course')
                    ->where('student_id', $student->student_id)
                    ->where('semester_id', $semesterId)
                    ->get();

                $coursesGrades = $grades->map(function ($grade) {
                    return [
                        'course_code' => $grade->course->course_code,
                        'course_name' => $grade->course->course_name,
                        'credits' => $grade->course->credits,
                        'grade_10' => $grade->grade_value,
                        'grade_letter' => $grade->grade_letter,
                        'grade_4' => $grade->grade_4_scale,
                        'status' => $grade->status
                    ];
                });

                return [
                    'student_id' => $student->student_id,
                    'user_code' => $student->user_code,
                    'full_name' => $student->full_name,
                    'courses' => $coursesGrades
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'class_info' => [
                        'class_name' => $class->class_name
                    ],
                    'semester_info' => [
                        'semester_name' => $semester->semester_name,
                        'academic_year' => $semester->academic_year
                    ],
                    'students_grades' => $gradesData
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to export class grades', [
                'class_id' => $classId,
                'semester_id' => $semesterId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất điểm: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * [ADMIN] Download template Excel để import điểm
     * GET /api/grades/download-template
     */
    public function downloadTemplate(Request $request)
    {
        if ($request->current_role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Admin mới có quyền tải template'
            ], 403);
        }

        try {
            $adminId = $request->current_user_id;

            $spreadsheet = $this->gradeImportService->generateTemplate($adminId);

            // Tạo file tạm
            $fileName = 'template_import_diem_' . date('YmdHis') . '.xlsx';
            $tempFile = storage_path('app/temp/' . $fileName);

            // Tạo thư mục nếu chưa tồn tại
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile);

            Log::info('Template downloaded', [
                'admin_id' => $adminId,
                'file_name' => $fileName
            ]);

            // Xóa tất cả output buffer trước khi download file
            if (ob_get_length()) {
                ob_end_clean();
            }

            return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Failed to download template', [
                'admin_id' => $request->current_user_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Import điểm từ file Excel
     * POST /api/grades/import-excel
     */


    public function importFromExcel(Request $request)
    {
        if ($request->current_role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Admin mới có quyền import điểm'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls|max:5120'
        ], [
            'file.required' => 'File Excel là bắt buộc',
            'file.file' => 'File không hợp lệ',
            'file.mimes' => 'File phải có định dạng xlsx hoặc xls',
            'file.max' => 'Kích thước file không được vượt quá 5MB'
        ]);

        if ($validator->fails()) {
            Log::warning('Excel import validation failed', [
                'admin_id' => $request->current_user_id,
                'errors' => $validator->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $adminId = $request->current_user_id;
        $file = $request->file('file');
        $fileName = 'import_' . $adminId . '_' . time() . '.' . $file->getClientOriginalExtension();

        // Lưu file vào storage/app/temp
        $filePath = $file->storeAs('temp', $fileName); // → temp/import_xxx.xlsx

        // LẤY ĐƯỜNG DẪN VẬT LÝ ĐÚNG (Windows-safe)
        $fullPath = Storage::path($filePath);

        Log::info('Starting Excel import', [
            'admin_id' => $adminId,
            'original_name' => $file->getClientOriginalName(),
            'temp_path' => $fullPath
        ]);

        try {
            // Gọi service
            $results = $this->gradeImportService->importFromExcel($fullPath, $adminId);

            // XÓA FILE SAU KHI XỬ LÝ XONG
            Storage::delete($filePath);

            $message = sprintf(
                'Import hoàn tất: %d thành công, %d cập nhật, %d lỗi',
                $results['summary']['success_count'],
                $results['summary']['updated_count'],
                $results['summary']['error_count']
            );

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            // XÓA FILE KHI LỖI
            Storage::delete($filePath);

            Log::error('Excel import failed', [
                'admin_id' => $adminId,
                'file_path' => $fullPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Xuất điểm lớp ra file Excel
     * GET /api/grades/export-excel/{class_id}/{semester_id}
     */
    public function exportToExcel(Request $request, $classId, $semesterId)
    {
        if ($request->current_role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Admin mới có quyền xuất điểm'
            ], 403);
        }

        $adminId = $request->current_user_id;

        // Kiểm tra quyền
        $admin = \App\Models\Advisor::with('unit')->find($adminId);
        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Admin chưa được gán vào khoa nào'
            ], 403);
        }

        $class = \App\Models\ClassModel::with('faculty')->find($classId);
        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy lớp'
            ], 404);
        }

        // Kiểm tra lớp thuộc khoa admin quản lý
        if ($class->faculty_id != $admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Lớp này không thuộc khoa bạn quản lý'
            ], 403);
        }

        try {
            $semester = Semester::find($semesterId);
            if (!$semester) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy học kỳ'
                ], 404);
            }

            // Lấy danh sách sinh viên và điểm
            $students = Student::where('class_id', $classId)
                ->orderBy('user_code')
                ->get();

            // Lấy tất cả môn học trong học kỳ của lớp này
            $courses = CourseGrade::with('course')
                ->whereIn('student_id', $students->pluck('student_id'))
                ->where('semester_id', $semesterId)
                ->get()
                ->pluck('course')
                ->unique('course_id')
                ->sortBy('course_code')
                ->values();

            // Tạo Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Bảng điểm');

            // Header thông tin
            $sheet->setCellValue('A1', 'BẢNG ĐIỂM LỚP ' . $class->class_name);
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('A2', 'Học kỳ: ' . $semester->semester_name . ' - ' . $semester->academic_year);
            $sheet->mergeCells('A2:F2');

            $sheet->setCellValue('A3', 'Khoa: ' . $admin->unit->unit_name);
            $sheet->mergeCells('A3:F3');

            // Header bảng
            $row = 5;
            $sheet->setCellValue('A' . $row, 'STT');
            $sheet->setCellValue('B' . $row, 'Mã SV');
            $sheet->setCellValue('C' . $row, 'Họ tên');

            // Header các môn học
            $col = 'D';
            foreach ($courses as $course) {
                $sheet->setCellValue($col . $row, $course->course_code);
                $col++;
            }
            $sheet->setCellValue($col . $row, 'GPA');

            // Format header
            $lastCol = $col;
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('CCE5FF');

            // Data
            $row++;
            $stt = 1;
            foreach ($students as $student) {
                $sheet->setCellValue('A' . $row, $stt++);
                $sheet->setCellValue('B' . $row, $student->user_code);
                $sheet->setCellValue('C' . $row, $student->full_name);

                // Điểm các môn
                $col = 'D';
                $totalGrade = 0;
                $totalCredits = 0;

                foreach ($courses as $course) {
                    $grade = CourseGrade::where('student_id', $student->student_id)
                        ->where('course_id', $course->course_id)
                        ->where('semester_id', $semesterId)
                        ->first();

                    if ($grade) {
                        $sheet->setCellValue($col . $row, $grade->grade_value);
                        if ($grade->grade_value !== null) {
                            $totalGrade += $grade->grade_value * $course->credits;
                            $totalCredits += $course->credits;
                        }
                    } else {
                        $sheet->setCellValue($col . $row, '-');
                    }
                    $col++;
                }

                // GPA
                $gpa = $totalCredits > 0 ? round($totalGrade / $totalCredits, 2) : 0;
                $sheet->setCellValue($col . $row, $gpa);

                $row++;
            }

            // Format số liệu
            $sheet->getStyle('A5:' . $lastCol . ($row - 1))->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            // Auto width
            foreach (range('A', $lastCol) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Xuất file
            $fileName = 'bangdiem_' . $class->class_name . '_' . $semester->semester_name . '_' . date('YmdHis') . '.xlsx';
            $tempFile = storage_path('app/temp/' . $fileName);

            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile);

            Log::info('Grades exported to Excel', [
                'admin_id' => $adminId,
                'class_id' => $classId,
                'semester_id' => $semesterId,
                'file_name' => $fileName
            ]);

            // Xóa tất cả output buffer trước khi download file
            if (ob_get_length()) {
                ob_end_clean();
            }

            return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Failed to export grades to Excel', [
                'admin_id' => $adminId,
                'class_id' => $classId,
                'semester_id' => $semesterId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất file Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Xem danh sách sinh viên và điểm trong khoa
     * GET /api/grades/faculty-students?semester_id={semester_id}&class_id={class_id}&search={search}
     */
    public function getFacultyStudentsGrades(Request $request)
    {
        if ($request->current_role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Admin mới có quyền xem danh sách này'
            ], 403);
        }

        $adminId = $request->current_user_id;
        $semesterId = $request->query('semester_id');
        $classId = $request->query('class_id');
        $search = $request->query('search');

        try {
            // Lấy thông tin admin và khoa
            $admin = \App\Models\Advisor::with('unit')->find($adminId);
            if (!$admin || !$admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin chưa được gán vào khoa nào'
                ], 403);
            }

            // Lấy danh sách lớp thuộc khoa
            $classesQuery = \App\Models\ClassModel::where('faculty_id', $admin->unit_id);

            // Filter theo class_id nếu có
            if ($classId) {
                $classesQuery->where('class_id', $classId);
            }

            $classes = $classesQuery->get();
            $classIds = $classes->pluck('class_id');

            // Lấy danh sách sinh viên trong các lớp thuộc khoa
            $studentsQuery = Student::with(['class'])
                ->whereIn('class_id', $classIds);

            // Tìm kiếm theo tên hoặc mã sinh viên
            if ($search) {
                $studentsQuery->where(function ($query) use ($search) {
                    $query->where('full_name', 'LIKE', "%{$search}%")
                        ->orWhere('user_code', 'LIKE', "%{$search}%");
                });
            }

            $students = $studentsQuery->orderBy('user_code')->get();

            // Lấy điểm của từng sinh viên
            $studentsData = $students->map(function ($student) use ($semesterId) {
                // Tính CPA (điểm trung bình tích lũy)
                $cpaData = AcademicMonitoringService::calculateCPA($student->student_id);

                // Nếu có filter semester_id, tính GPA của học kỳ đó
                $gpaData = null;
                if ($semesterId) {
                    $gpaData = AcademicMonitoringService::calculateGPA($student->student_id, $semesterId);
                }

                // Lấy điểm để đếm số môn
                $gradesQuery = CourseGrade::with(['course', 'semester'])
                    ->where('student_id', $student->student_id);

                if ($semesterId) {
                    $gradesQuery->where('semester_id', $semesterId);
                }

                $grades = $gradesQuery->orderBy('semester_id', 'desc')->get();
                $passedCourses = $grades->where('status', 'passed')->count();
                $failedCourses = $grades->where('status', 'failed')->count();

                $academicSummary = [
                    'cpa_10' => $cpaData['cpa_10'],
                    'cpa_4' => $cpaData['cpa_4'],
                    'total_credits_passed' => $cpaData['total_credits_passed'],
                    'passed_courses' => $passedCourses,
                    'failed_courses' => $failedCourses,
                    'total_courses' => $grades->count()
                ];

                // Thêm GPA nếu có filter semester
                if ($semesterId && $gpaData) {
                    $academicSummary['semester_gpa_10'] = $gpaData['gpa_10'];
                    $academicSummary['semester_gpa_4'] = $gpaData['gpa_4'];
                    $academicSummary['semester_credits'] = $gpaData['credits_registered'];
                }

                return [
                    'student_id' => $student->student_id,
                    'user_code' => $student->user_code,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'phone_number' => $student->phone_number,
                    'class_name' => $student->class->class_name,
                    'class_id' => $student->class_id,
                    'status' => $student->status,
                    'academic_summary' => $academicSummary
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'faculty_info' => [
                        'unit_id' => $admin->unit_id,
                        'unit_name' => $admin->unit->unit_name
                    ],
                    'students' => $studentsData,
                    'summary' => [
                        'total_students' => $studentsData->count(),
                        'total_classes' => $classes->count()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get faculty students grades', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách sinh viên: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Xem tổng quan điểm của khoa
     * GET /api/grades/faculty-overview?semester_id={semester_id}
     */
    public function getFacultyGradesOverview(Request $request)
    {
        if ($request->current_role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Admin mới có quyền xem tổng quan'
            ], 403);
        }

        $adminId = $request->current_user_id;
        $semesterId = $request->query('semester_id');

        try {
            // Lấy thông tin admin và khoa
            $admin = \App\Models\Advisor::with('unit')->find($adminId);
            if (!$admin || !$admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin chưa được gán vào khoa nào'
                ], 403);
            }

            // Lấy danh sách lớp thuộc khoa
            $classes = \App\Models\ClassModel::where('faculty_id', $admin->unit_id)->get();
            $classIds = $classes->pluck('class_id');

            // Lấy danh sách sinh viên
            $students = Student::whereIn('class_id', $classIds)->get();
            $studentIds = $students->pluck('student_id');

            // Lấy điểm của sinh viên
            $gradesQuery = CourseGrade::with(['course'])
                ->whereIn('student_id', $studentIds);

            if ($semesterId) {
                $gradesQuery->where('semester_id', $semesterId);
            }

            $grades = $gradesQuery->get();

            // Thống kê
            $totalGrades = $grades->count();
            $passedGrades = $grades->where('status', 'passed')->count();
            $failedGrades = $grades->where('status', 'failed')->count();
            $studyingGrades = $grades->where('status', 'studying')->count();

            // Phân phối điểm
            $gradeDistribution = [
                'excellent' => $grades->where('grade_value', '>=', 8.5)->count(), // Xuất sắc
                'good' => $grades->whereBetween('grade_value', [7.0, 8.4])->count(), // Giỏi
                'average' => $grades->whereBetween('grade_value', [5.5, 6.9])->count(), // Khá
                'below_average' => $grades->whereBetween('grade_value', [4.0, 5.4])->count(), // Trung bình
                'failed' => $grades->where('grade_value', '<', 4.0)->count() // Yếu/Kém
            ];

            // Tính điểm trung bình của khoa
            $totalCredits = 0;
            $totalGradePoints = 0;

            foreach ($grades->where('status', 'passed') as $grade) {
                $totalGradePoints += $grade->grade_value * $grade->course->credits;
                $totalCredits += $grade->course->credits;
            }

            $averageScore = $totalCredits > 0 ? round($totalGradePoints / $totalCredits, 2) : 0;

            // Tính CPA trung bình của toàn khoa (không phụ thuộc filter semester)
            $totalCPA = 0;
            $studentCount = 0;
            foreach ($students as $student) {
                $cpaData = AcademicMonitoringService::calculateCPA($student->student_id);
                if ($cpaData['cpa_10'] > 0) {
                    $totalCPA += $cpaData['cpa_10'];
                    $studentCount++;
                }
            }
            $averageCPA = $studentCount > 0 ? round($totalCPA / $studentCount, 2) : 0;

            // Thống kê theo lớp
            $classStats = $classes->map(function ($class) use ($semesterId) {
                $students = Student::where('class_id', $class->class_id)->get();
                $studentIds = $students->pluck('student_id');

                // Tính CPA trung bình của lớp
                $totalCPA = 0;
                $cpaCount = 0;
                foreach ($students as $student) {
                    $cpaData = AcademicMonitoringService::calculateCPA($student->student_id);
                    if ($cpaData['cpa_10'] > 0) {
                        $totalCPA += $cpaData['cpa_10'];
                        $cpaCount++;
                    }
                }
                $classCPA = $cpaCount > 0 ? round($totalCPA / $cpaCount, 2) : 0;

                // Nếu có semester filter, tính GPA của học kỳ đó
                $classGPA = null;
                if ($semesterId) {
                    $totalGPA = 0;
                    $gpaCount = 0;
                    foreach ($students as $student) {
                        $gpaData = AcademicMonitoringService::calculateGPA($student->student_id, $semesterId);
                        if ($gpaData['gpa_10'] > 0) {
                            $totalGPA += $gpaData['gpa_10'];
                            $gpaCount++;
                        }
                    }
                    $classGPA = $gpaCount > 0 ? round($totalGPA / $gpaCount, 2) : 0;
                }

                $gradesQuery = CourseGrade::with(['course'])
                    ->whereIn('student_id', $studentIds);

                if ($semesterId) {
                    $gradesQuery->where('semester_id', $semesterId);
                }

                $classGrades = $gradesQuery->get();

                $classStats = [
                    'class_id' => $class->class_id,
                    'class_name' => $class->class_name,
                    'total_students' => $students->count(),
                    'average_cpa' => $classCPA,
                    'passed_courses' => $classGrades->where('status', 'passed')->count(),
                    'failed_courses' => $classGrades->where('status', 'failed')->count()
                ];

                // Thêm GPA nếu có semester filter
                if ($semesterId) {
                    $classStats['average_semester_gpa'] = $classGPA;
                }

                return $classStats;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'faculty_info' => [
                        'unit_id' => $admin->unit_id,
                        'unit_name' => $admin->unit->unit_name
                    ],
                    'overview' => [
                        'total_students' => $students->count(),
                        'total_classes' => $classes->count(),
                        'total_grades' => $totalGrades,
                        'average_cpa' => $averageCPA,
                        'average_score' => $semesterId ? $averageScore : null,
                        'passed_rate' => $totalGrades > 0 ? round(($passedGrades / $totalGrades) * 100, 2) : 0
                    ],
                    'grade_statistics' => [
                        'passed' => $passedGrades,
                        'failed' => $failedGrades,
                        'studying' => $studyingGrades
                    ],
                    'grade_distribution' => $gradeDistribution,
                    'class_statistics' => $classStats
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get faculty grades overview', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy tổng quan điểm: ' . $e->getMessage()
            ], 500);
        }
    }
}
