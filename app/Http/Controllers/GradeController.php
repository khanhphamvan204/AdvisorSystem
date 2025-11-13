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
     * [ADVISOR] Xem điểm của sinh viên
     * GET /api/grades/student/{student_id}?semester_id={semester_id}
     */
    public function getStudentGrades(Request $request, $studentId)
    {
        $advisorId = $request->current_user_id;
        $semesterId = $request->query('semester_id');

        // Kiểm tra quyền
        $student = Student::with('class')->find($studentId);
        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sinh viên'
            ], 404);
        }

        if ($student->class->advisor_id != $advisorId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ được xem điểm sinh viên trong lớp mình quản lý'
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
            Log::error('Failed to get student grades by advisor', [
                'advisor_id' => $advisorId,
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
        ]);

        if ($validator->fails()) {
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
        ]);

        if ($validator->fails()) {
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
     * [ADVISOR] Xuất điểm lớp theo học kỳ
     * GET /api/grades/export-class-grades/{class_id}/{semester_id}
     */
    public function exportClassGrades(Request $request, $classId, $semesterId)
    {
        $advisorId = $request->current_user_id;

        // Kiểm tra quyền
        $class = \App\Models\ClassModel::find($classId);
        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy lớp'
            ], 404);
        }

        if ($class->advisor_id != $advisorId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ được xuất điểm lớp mình quản lý'
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
        ]);

        if ($validator->fails()) {
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
}