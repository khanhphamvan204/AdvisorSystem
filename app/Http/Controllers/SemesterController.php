<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Models\SemesterReport;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SemesterController extends Controller
{
    /**
     * Lấy danh sách học kỳ
     * - Admin/Advisor: Xem tất cả học kỳ
     * - Student: Xem tất cả học kỳ
     */
    public function index(Request $request)
    {
        try {
            $semesters = Semester::orderBy('academic_year', 'desc')
                ->orderBy('semester_name', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $semesters,
                'message' => 'Lấy danh sách học kỳ thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem chi tiết học kỳ
     */
    public function show(Request $request, $id)
    {
        try {
            $semester = Semester::find($id);

            if (!$semester) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy học kỳ'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $semester,
                'message' => 'Lấy thông tin học kỳ thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo học kỳ mới (chỉ admin)
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'semester_name' => 'required|string|max:50',
                'academic_year' => 'required|string|max:20',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date'
            ], [
                'semester_name.required' => 'Tên học kỳ không được để trống',
                'academic_year.required' => 'Năm học không được để trống',
                'start_date.required' => 'Ngày bắt đầu không được để trống',
                'end_date.required' => 'Ngày kết thúc không được để trống',
                'end_date.after' => 'Ngày kết thúc phải sau ngày bắt đầu'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Kiểm tra trùng lặp
            $exists = Semester::where('semester_name', $request->semester_name)
                ->where('academic_year', $request->academic_year)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Học kỳ này đã tồn tại'
                ], 400);
            }

            $semester = Semester::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $semester,
                'message' => 'Tạo học kỳ thành công'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin học kỳ (chỉ admin)
     */
    public function update(Request $request, $id)
    {
        try {
            $semester = Semester::find($id);

            if (!$semester) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy học kỳ'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'semester_name' => 'sometimes|string|max:50',
                'academic_year' => 'sometimes|string|max:20',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after:start_date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $semester->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $semester,
                'message' => 'Cập nhật học kỳ thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa học kỳ (chỉ admin)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $semester = Semester::find($id);

            if (!$semester) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy học kỳ'
                ], 404);
            }

            // Kiểm tra học kỳ có dữ liệu liên quan không
            if (
                $semester->semesterReports()->count() > 0 ||
                $semester->courseGrades()->count() > 0
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa học kỳ có dữ liệu điểm'
                ], 400);
            }

            $semester->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa học kỳ thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy báo cáo học kỳ theo vai trò
     * - Admin: Xem báo cáo của các sinh viên trong khoa
     * - Advisor: Xem báo cáo sinh viên trong lớp mình cố vấn
     * - Student: Chỉ xem báo cáo của mình
     */
    public function getSemesterReports(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $semester = Semester::find($id);
            if (!$semester) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy học kỳ'
                ], 404);
            }

            $query = SemesterReport::with(['student.class'])
                ->where('semester_id', $id);

            switch ($role) {
                case 'admin':
                    // Admin xem báo cáo sinh viên trong khoa mình quản lý
                    $advisor = \App\Models\Advisor::find($userId);
                    if (!$advisor || !$advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không tìm thấy thông tin đơn vị quản lý'
                        ], 404);
                    }

                    $query->whereHas('student.class', function ($q) use ($advisor) {
                        $q->where('faculty_id', $advisor->unit_id);
                    });
                    break;

                case 'advisor':
                    // Advisor xem báo cáo sinh viên trong lớp mình cố vấn
                    $classIds = \App\Models\ClassModel::where('advisor_id', $userId)
                        ->pluck('class_id');

                    $query->whereHas('student', function ($q) use ($classIds) {
                        $q->whereIn('class_id', $classIds);
                    });
                    break;

                case 'student':
                    // Student chỉ xem báo cáo của mình
                    $query->where('student_id', $userId);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Vai trò không hợp lệ'
                    ], 403);
            }

            $reports = $query->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'semester' => $semester,
                    'reports' => $reports
                ],
                'message' => 'Lấy báo cáo học kỳ thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy báo cáo học kỳ của một sinh viên cụ thể
     */
    public function getStudentReport(Request $request, $semesterId, $studentId)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            // Kiểm tra quyền truy cập
            switch ($role) {
                case 'admin':
                    $advisor = \App\Models\Advisor::find($userId);
                    $student = Student::find($studentId);

                    if (!$student) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không tìm thấy sinh viên'
                        ], 404);
                    }

                    // Kiểm tra sinh viên có thuộc khoa admin quản lý không
                    if ($student->class->faculty_id !== $advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem báo cáo này'
                        ], 403);
                    }
                    break;

                case 'advisor':
                    $student = Student::find($studentId);

                    if (!$student) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không tìm thấy sinh viên'
                        ], 404);
                    }

                    // Kiểm tra sinh viên có trong lớp advisor cố vấn không
                    if ($student->class->advisor_id !== $userId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem báo cáo này'
                        ], 403);
                    }
                    break;

                case 'student':
                    // Student chỉ xem báo cáo của mình
                    if ($studentId != $userId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem báo cáo này'
                        ], 403);
                    }
                    break;
            }

            $report = SemesterReport::with(['student', 'semester'])
                ->where('semester_id', $semesterId)
                ->where('student_id', $studentId)
                ->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy báo cáo'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Lấy báo cáo thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy học kỳ hiện tại
     */
    public function getCurrentSemester(Request $request)
    {
        try {
            $currentDate = now();

            $semester = Semester::where('start_date', '<=', $currentDate)
                ->where('end_date', '>=', $currentDate)
                ->first();

            if (!$semester) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có học kỳ đang diễn ra'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $semester,
                'message' => 'Lấy học kỳ hiện tại thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }
}