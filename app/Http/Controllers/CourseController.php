<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseGrade;
use App\Models\Student;
use App\Models\Semester;
use App\Models\Advisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CourseController extends Controller
{
    /**
     * [PUBLIC] Xem danh sách tất cả môn học
     * GET /api/courses
     */
    public function index(Request $request)
    {
        try {
            $search = $request->query('search');
            $unitId = $request->query('unit_id'); // Lọc theo khoa

            $query = Course::with('unit');

            // Tìm kiếm theo mã hoặc tên môn học
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('course_code', 'like', "%{$search}%")
                        ->orWhere('course_name', 'like', "%{$search}%");
                });
            }

            // Lọc theo khoa
            if ($unitId) {
                $query->where('unit_id', $unitId);
            }

            $courses = $query->orderBy('course_code')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'courses' => $courses->map(function ($course) {
                        return [
                            'course_id' => $course->course_id,
                            'course_code' => $course->course_code,
                            'course_name' => $course->course_name,
                            'credits' => $course->credits,
                            'unit' => $course->unit ? [
                                'unit_id' => $course->unit->unit_id,
                                'unit_name' => $course->unit->unit_name,
                                'type' => $course->unit->type
                            ] : null
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get courses list', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách môn học: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [PUBLIC] Xem chi tiết môn học
     * GET /api/courses/{course_id}
     */
    public function show($courseId)
    {
        try {
            $course = Course::with('unit')->find($courseId);

            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy môn học'
                ], 404);
            }

            // Thống kê số sinh viên đã học môn này
            $totalStudents = CourseGrade::where('course_id', $courseId)
                ->distinct('student_id')
                ->count('student_id');

            $passedCount = CourseGrade::where('course_id', $courseId)
                ->where('status', 'passed')
                ->distinct('student_id')
                ->count('student_id');

            $failedCount = CourseGrade::where('course_id', $courseId)
                ->where('status', 'failed')
                ->distinct('student_id')
                ->count('student_id');

            return response()->json([
                'success' => true,
                'data' => [
                    'course' => [
                        'course_id' => $course->course_id,
                        'course_code' => $course->course_code,
                        'course_name' => $course->course_name,
                        'credits' => $course->credits,
                        'unit' => $course->unit ? [
                            'unit_id' => $course->unit->unit_id,
                            'unit_name' => $course->unit->unit_name,
                            'type' => $course->unit->type
                        ] : null
                    ],
                    'statistics' => [
                        'total_students' => $totalStudents,
                        'passed_count' => $passedCount,
                        'failed_count' => $failedCount,
                        'pass_rate' => $totalStudents > 0 ? round(($passedCount / $totalStudents) * 100, 2) : 0
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get course detail', [
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin môn học: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Tạo môn học mới (chỉ thuộc khoa của mình)
     * POST /api/courses
     */
    public function store(Request $request)
    {
        $adminId = $request->current_user_id;

        // Lấy thông tin admin
        $admin = Advisor::find($adminId);

        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Admin chưa được gán vào khoa nào'
            ], 403);
        }

        // Kiểm tra unit_id trong request phải khớp với unit_id của admin
        $validator = Validator::make($request->all(), [
            'course_code' => 'required|string|max:20|unique:Courses,course_code',
            'course_name' => 'required|string|max:100',
            'credits' => 'required|integer|min:1|max:10',
            'unit_id' => 'required|integer|exists:Units,unit_id'
        ], [
            'course_code.required' => 'Mã môn học là bắt buộc',
            'course_code.string' => 'Mã môn học phải là chuỗi',
            'course_code.max' => 'Mã môn học không được vượt quá 20 ký tự',
            'course_code.unique' => 'Mã môn học đã tồn tại',
            'course_name.required' => 'Tên môn học là bắt buộc',
            'course_name.string' => 'Tên môn học phải là chuỗi',
            'course_name.max' => 'Tên môn học không được vượt quá 100 ký tự',
            'credits.required' => 'Số tín chỉ là bắt buộc',
            'credits.integer' => 'Số tín chỉ phải là số nguyên',
            'credits.min' => 'Số tín chỉ phải ít nhất là 1',
            'credits.max' => 'Số tín chỉ không được vượt quá 10',
            'unit_id.required' => 'Khoa là bắt buộc',
            'unit_id.integer' => 'Khoa phải là số nguyên',
            'unit_id.exists' => 'Khoa không tồn tại'
        ]);

        if ($validator->fails()) {
            Log::warning('Course creation validation failed', [
                'admin_id' => $adminId,
                'errors' => $validator->errors(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Kiểm tra quyền: Admin chỉ được thêm môn học thuộc khoa của mình
        if ($request->unit_id != $admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ có thể thêm môn học thuộc khoa của mình'
            ], 403);
        }

        try {
            $course = Course::create([
                'course_code' => $request->course_code,
                'course_name' => $request->course_name,
                'credits' => $request->credits,
                'unit_id' => $request->unit_id
            ]);

            $course->load('unit');

            Log::info('Course created by admin', [
                'admin_id' => $adminId,
                'course_id' => $course->course_id,
                'course_code' => $course->course_code,
                'unit_id' => $course->unit_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo môn học thành công',
                'data' => [
                    'course' => $course
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create course', [
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo môn học: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Cập nhật thông tin môn học (chỉ môn học thuộc khoa của mình)
     * PUT /api/courses/{course_id}
     */
    public function update(Request $request, $courseId)
    {
        $adminId = $request->current_user_id;

        // Lấy thông tin admin
        $admin = Advisor::find($adminId);

        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Admin chưa được gán vào khoa nào'
            ], 403);
        }

        $course = Course::find($courseId);

        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy môn học'
            ], 404);
        }

        // Kiểm tra quyền: Admin chỉ được sửa môn học thuộc khoa của mình
        if ($course->unit_id != $admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ có thể cập nhật môn học thuộc khoa của mình'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'course_code' => 'sometimes|string|max:20|unique:Courses,course_code,' . $courseId . ',course_id',
            'course_name' => 'sometimes|string|max:100',
            'credits' => 'sometimes|integer|min:1|max:10',
            'unit_id' => 'sometimes|integer|exists:Units,unit_id'
        ], [
            'course_code.string' => 'Mã môn học phải là chuỗi',
            'course_code.max' => 'Mã môn học không được vượt quá 20 ký tự',
            'course_code.unique' => 'Mã môn học đã tồn tại',
            'course_name.string' => 'Tên môn học phải là chuỗi',
            'course_name.max' => 'Tên môn học không được vượt quá 100 ký tự',
            'credits.integer' => 'Số tín chỉ phải là số nguyên',
            'credits.min' => 'Số tín chỉ phải ít nhất là 1',
            'credits.max' => 'Số tín chỉ không được vượt quá 10',
            'unit_id.integer' => 'Khoa phải là số nguyên',
            'unit_id.exists' => 'Khoa không tồn tại'
        ]);

        if ($validator->fails()) {
            Log::warning('Course update validation failed', [
                'admin_id' => $adminId,
                'course_id' => $courseId,
                'errors' => $validator->errors(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Nếu có thay đổi unit_id, kiểm tra phải là unit của admin
        if ($request->has('unit_id') && $request->unit_id != $admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ có thể chuyển môn học sang khoa của mình'
            ], 403);
        }

        try {
            if ($request->has('course_code')) {
                $course->course_code = $request->course_code;
            }
            if ($request->has('course_name')) {
                $course->course_name = $request->course_name;
            }
            if ($request->has('credits')) {
                $course->credits = $request->credits;
            }
            if ($request->has('unit_id')) {
                $course->unit_id = $request->unit_id;
            }

            $course->save();
            $course->load('unit');

            Log::info('Course updated by admin', [
                'admin_id' => $adminId,
                'course_id' => $course->course_id,
                'changes' => $request->all()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật môn học thành công',
                'data' => [
                    'course' => $course
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update course', [
                'admin_id' => $adminId,
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật môn học: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Xóa môn học (chỉ môn học thuộc khoa của mình)
     * DELETE /api/courses/{course_id}
     */
    public function destroy(Request $request, $courseId)
    {
        $adminId = $request->current_user_id;

        // Lấy thông tin admin
        $admin = Advisor::find($adminId);

        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Admin chưa được gán vào khoa nào'
            ], 403);
        }

        $course = Course::find($courseId);

        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy môn học'
            ], 404);
        }

        // Kiểm tra quyền: Admin chỉ được xóa môn học thuộc khoa của mình
        if ($course->unit_id != $admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ có thể xóa môn học thuộc khoa của mình'
            ], 403);
        }

        // Kiểm tra xem môn học đã có điểm chưa
        $hasGrades = CourseGrade::where('course_id', $courseId)->exists();

        if ($hasGrades) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa môn học đã có điểm. Vui lòng xóa điểm trước.'
            ], 400);
        }

        try {
            $courseCode = $course->course_code;
            $course->delete();

            Log::info('Course deleted by admin', [
                'admin_id' => $adminId,
                'course_id' => $courseId,
                'course_code' => $courseCode
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa môn học thành công'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete course', [
                'admin_id' => $adminId,
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa môn học: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Xem danh sách môn học thuộc khoa của mình
     * GET /api/courses/my-unit-courses
     */
    public function getMyUnitCourses(Request $request)
    {
        $adminId = $request->current_user_id;

        // Lấy thông tin admin
        $admin = Advisor::find($adminId);

        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Admin chưa được gán vào khoa nào'
            ], 403);
        }

        try {
            $search = $request->query('search');

            $query = Course::with('unit')
                ->where('unit_id', $admin->unit_id);

            // Tìm kiếm
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('course_code', 'like', "%{$search}%")
                        ->orWhere('course_name', 'like', "%{$search}%");
                });
            }

            $courses = $query->orderBy('course_code')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'unit_info' => [
                        'unit_id' => $admin->unit->unit_id,
                        'unit_name' => $admin->unit->unit_name,
                        'type' => $admin->unit->type
                    ],
                    'courses' => $courses,
                    'total_courses' => $courses->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get admin unit courses', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách môn học: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [STUDENT] Xem danh sách môn học của mình
     * GET /api/courses/my-courses?semester_id={semester_id}
     */
    public function getMyCourses(Request $request)
    {
        $studentId = $request->current_user_id;
        $semesterId = $request->query('semester_id');

        try {
            $query = CourseGrade::with(['course.unit', 'semester'])
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
                        'unit_name' => $grade->course->unit ? $grade->course->unit->unit_name : null,
                        'semester' => $grade->semester->semester_name . ' ' . $grade->semester->academic_year,
                        'grade_value' => $grade->grade_value,
                        'grade_letter' => $grade->grade_letter,
                        'grade_4_scale' => $grade->grade_4_scale,
                        'status' => $grade->status
                    ];
                });

            // Thống kê
            $totalCredits = $grades->sum('credits');
            $passedCredits = $grades->where('status', 'passed')->sum('credits');
            $failedCourses = $grades->where('status', 'failed')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'courses' => $grades,
                    'summary' => [
                        'total_courses' => $grades->count(),
                        'total_credits' => $totalCredits,
                        'passed_credits' => $passedCredits,
                        'failed_courses' => $failedCourses
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get student courses', [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách môn học: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADVISOR] Xem danh sách sinh viên học một môn trong học kỳ
     * GET /api/courses/{course_id}/students?semester_id={semester_id}
     */
    public function getCourseStudents(Request $request, $courseId)
    {
        $advisorId = $request->current_user_id;
        $semesterId = $request->query('semester_id');

        if (!$semesterId) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng cung cấp semester_id'
            ], 422);
        }

        // Kiểm tra môn học
        $course = Course::with('unit')->find($courseId);
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy môn học'
            ], 404);
        }

        try {
            // Lấy danh sách sinh viên học môn này trong học kỳ
            // Chỉ lấy sinh viên trong các lớp mà advisor quản lý
            $grades = CourseGrade::with(['student.class'])
                ->where('course_id', $courseId)
                ->where('semester_id', $semesterId)
                ->whereHas('student.class', function ($query) use ($advisorId) {
                    $query->where('advisor_id', $advisorId);
                })
                ->get()
                ->map(function ($grade) {
                    return [
                        'grade_id' => $grade->grade_id,
                        'student_id' => $grade->student->student_id,
                        'user_code' => $grade->student->user_code,
                        'full_name' => $grade->student->full_name,
                        'class_name' => $grade->student->class->class_name,
                        'grade_value' => $grade->grade_value,
                        'grade_letter' => $grade->grade_letter,
                        'grade_4_scale' => $grade->grade_4_scale,
                        'status' => $grade->status
                    ];
                });

            $semester = Semester::find($semesterId);

            // Thống kê
            $totalStudents = $grades->count();
            $passedCount = $grades->where('status', 'passed')->count();
            $failedCount = $grades->where('status', 'failed')->count();
            $avgGrade = $grades->whereNotNull('grade_value')->avg('grade_value');

            return response()->json([
                'success' => true,
                'data' => [
                    'course_info' => [
                        'course_code' => $course->course_code,
                        'course_name' => $course->course_name,
                        'credits' => $course->credits,
                        'unit_name' => $course->unit ? $course->unit->unit_name : null
                    ],
                    'semester_info' => [
                        'semester_name' => $semester->semester_name,
                        'academic_year' => $semester->academic_year
                    ],
                    'students' => $grades,
                    'statistics' => [
                        'total_students' => $totalStudents,
                        'passed_count' => $passedCount,
                        'failed_count' => $failedCount,
                        'pass_rate' => $totalStudents > 0 ? round(($passedCount / $totalStudents) * 100, 2) : 0,
                        'average_grade' => round($avgGrade ?? 0, 2)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get course students', [
                'course_id' => $courseId,
                'semester_id' => $semesterId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách sinh viên: ' . $e->getMessage()
            ], 500);
        }
    }
}
