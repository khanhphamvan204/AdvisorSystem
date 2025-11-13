<?php

namespace App\Http\Controllers;

use App\Models\ClassModel;
use App\Models\Advisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClassController extends Controller
{
    /**
     * Lấy danh sách lớp theo vai trò
     * - Admin: Xem các lớp thuộc khoa mình quản lý
     * - Advisor: Xem lớp mình làm cố vấn
     * - Student: Xem lớp của mình
     */
    public function index(Request $request)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $query = ClassModel::with(['advisor', 'faculty']);

            switch ($role) {
                case 'admin':
                    // Admin xem các lớp thuộc khoa mình quản lý
                    $advisor = Advisor::find($userId);
                    if (!$advisor || !$advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không tìm thấy thông tin đơn vị quản lý'
                        ], 404);
                    }
                    $query->where('faculty_id', $advisor->unit_id);
                    break;

                case 'advisor':
                    // Advisor chỉ xem lớp mình làm cố vấn
                    $query->where('advisor_id', $userId);
                    break;

                case 'student':
                    // Student chỉ xem lớp của mình
                    $student = \App\Models\Student::find($userId);
                    if (!$student) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không tìm thấy thông tin sinh viên'
                        ], 404);
                    }
                    $query->where('class_id', $student->class_id);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Vai trò không hợp lệ'
                    ], 403);
            }

            $classes = $query->get();

            return response()->json([
                'success' => true,
                'data' => $classes,
                'message' => 'Lấy danh sách lớp thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem chi tiết lớp
     */
    public function show(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $class = ClassModel::with(['advisor', 'faculty', 'students'])->find($id);

            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy lớp'
                ], 404);
            }

            // Kiểm tra quyền truy cập
            switch ($role) {
                case 'admin':
                    $advisor = Advisor::find($userId);
                    if ($class->faculty_id !== $advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem lớp này'
                        ], 403);
                    }
                    break;

                case 'advisor':
                    if ($class->advisor_id !== $userId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem lớp này'
                        ], 403);
                    }
                    break;

                case 'student':
                    $student = \App\Models\Student::find($userId);
                    if ($class->class_id !== $student->class_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem lớp này'
                        ], 403);
                    }
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => $class,
                'message' => 'Lấy thông tin lớp thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo lớp mới (chỉ admin)
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'class_name' => 'required|string|max:50|unique:Classes,class_name',
                'advisor_id' => 'nullable|exists:Advisors,advisor_id',
                'faculty_id' => 'required|exists:Units,unit_id',
                'description' => 'nullable|string'
            ], [
                'class_name.required' => 'Tên lớp không được để trống',
                'class_name.unique' => 'Tên lớp đã tồn tại',
                'faculty_id.required' => 'Khoa không được để trống',
                'faculty_id.exists' => 'Khoa không tồn tại',
                'advisor_id.exists' => 'Cố vấn không tồn tại'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Kiểm tra admin chỉ tạo lớp cho khoa mình quản lý
            $userId = $request->current_user_id;
            $advisor = Advisor::find($userId);

            if ($request->faculty_id !== $advisor->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ có thể tạo lớp cho khoa mình quản lý'
                ], 403);
            }

            $class = ClassModel::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $class,
                'message' => 'Tạo lớp thành công'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin lớp (chỉ admin)
     */
    public function update(Request $request, $id)
    {
        try {
            $class = ClassModel::find($id);

            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy lớp'
                ], 404);
            }

            // Kiểm tra admin chỉ sửa lớp thuộc khoa mình quản lý
            $userId = $request->current_user_id;
            $advisor = Advisor::find($userId);

            if ($class->faculty_id !== $advisor->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền cập nhật lớp này'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'class_name' => 'sometimes|string|max:50|unique:Classes,class_name,' . $id . ',class_id',
                'advisor_id' => 'nullable|exists:Advisors,advisor_id',
                'faculty_id' => 'sometimes|exists:Units,unit_id',
                'description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $class->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $class,
                'message' => 'Cập nhật lớp thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa lớp (chỉ admin)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $class = ClassModel::find($id);

            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy lớp'
                ], 404);
            }

            // Kiểm tra admin chỉ xóa lớp thuộc khoa mình quản lý
            $userId = $request->current_user_id;
            $advisor = Advisor::find($userId);

            if ($class->faculty_id !== $advisor->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa lớp này'
                ], 403);
            }

            // Kiểm tra lớp có sinh viên không
            if ($class->students()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa lớp có sinh viên'
                ], 400);
            }

            $class->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa lớp thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách sinh viên trong lớp
     */
    public function getStudents(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $class = ClassModel::with('students')->find($id);

            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy lớp'
                ], 404);
            }

            // Kiểm tra quyền truy cập
            switch ($role) {
                case 'admin':
                    $advisor = Advisor::find($userId);
                    if ($class->faculty_id !== $advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem lớp này'
                        ], 403);
                    }
                    break;

                case 'advisor':
                    if ($class->advisor_id !== $userId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem lớp này'
                        ], 403);
                    }
                    break;

                case 'student':
                    $student = \App\Models\Student::find($userId);
                    if ($class->class_id !== $student->class_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem lớp này'
                        ], 403);
                    }
                    break;
            }

            // Lấy thông tin sinh viên kèm theo cảnh cáo học vụ
            $studentsWithWarnings = $class->students->map(function ($student) {
                // Lấy toàn bộ cảnh cáo học vụ của sinh viên
                $warnings = \App\Models\AcademicWarning::where('student_id', $student->student_id)
                    ->with('semester')
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($warning) {
                        return [
                            'warning_id' => $warning->warning_id,
                            'title' => $warning->title,
                            'content' => $warning->content,
                            'advice' => $warning->advice,
                            'semester' => $warning->semester->semester_name . ' ' . $warning->semester->academic_year,
                            'created_at' => $warning->created_at->format('d/m/Y H:i')
                        ];
                    });

                return [
                    'student_id' => $student->student_id,
                    'user_code' => $student->user_code,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                    'status' => $student->status,
                    'has_academic_warning' => $warnings->isNotEmpty(),
                    'warnings' => $warnings,
                    'warnings_count' => $warnings->count()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $studentsWithWarnings,
                'message' => 'Lấy danh sách sinh viên thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }
}