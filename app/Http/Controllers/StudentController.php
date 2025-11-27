<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Advisor;
use App\Models\Semester;
use App\Services\PointCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    /**
     * Lấy danh sách sinh viên
     * - Admin: Xem sinh viên thuộc các lớp trong khoa mình quản lý
     * - Advisor: Xem sinh viên trong các lớp mình làm cố vấn
     * - Student: Chỉ xem thông tin bản thân
     */
    public function index(Request $request)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $query = Student::with(['class', 'class.advisor', 'class.faculty']);

            switch ($role) {
                case 'admin':
                    $advisor = Advisor::find($userId);
                    if (!$advisor || !$advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không tìm thấy thông tin đơn vị quản lý'
                        ], 404);
                    }
                    // Lấy sinh viên thuộc các lớp trong khoa
                    $query->whereHas('class', function ($q) use ($advisor) {
                        $q->where('faculty_id', $advisor->unit_id);
                    });
                    break;

                case 'advisor':
                    // Lấy sinh viên trong các lớp mình làm cố vấn
                    $query->whereHas('class', function ($q) use ($userId) {
                        $q->where('advisor_id', $userId);
                    });
                    break;

                case 'student':
                    // Chỉ xem thông tin bản thân
                    $query->where('student_id', $userId);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Vai trò không hợp lệ'
                    ], 403);
            }

            // Filter by class
            if ($request->has('class_id')) {
                $query->where('class_id', $request->class_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('user_code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $students = $query->get();

            // Lấy học kì gần nhất để tính điểm rèn luyện
            $latestSemester = Semester::orderBy('start_date', 'desc')->first();

            // Thêm điểm rèn luyện và điểm CTXH cho mỗi sinh viên
            $studentsWithPoints = $students->map(function ($student) use ($latestSemester) {
                $studentData = $student->toArray();

                // Tính điểm rèn luyện từ học kì gần nhất
                if ($latestSemester) {
                    try {
                        $trainingPoints = PointCalculationService::calculateTrainingPoints(
                            $student->student_id,
                            $latestSemester->semester_id
                        );
                        $studentData['training_points'] = $trainingPoints;
                        $studentData['training_semester'] = [
                            'semester_id' => $latestSemester->semester_id,
                            'semester_name' => $latestSemester->semester_name,
                            'academic_year' => $latestSemester->academic_year
                        ];
                    } catch (\Exception $e) {
                        $studentData['training_points'] = null;
                        $studentData['training_semester'] = null;
                    }
                } else {
                    $studentData['training_points'] = null;
                    $studentData['training_semester'] = null;
                }

                // Tính điểm CTXH tích lũy từ đầu đến giờ
                try {
                    $socialPoints = PointCalculationService::calculateSocialPoints(
                        $student->student_id
                    );
                    $studentData['social_points'] = $socialPoints;
                } catch (\Exception $e) {
                    $studentData['social_points'] = 0;
                }

                return $studentData;
            });

            return response()->json([
                'success' => true,
                'data' => $studentsWithPoints,
                'message' => 'Lấy danh sách sinh viên thành công'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem chi tiết sinh viên
     */
    public function show(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $student = Student::with([
                'class',
                'class.advisor',
                'class.faculty',
                'semesterReports.semester',
                'academicWarnings.advisor',
                'academicWarnings.semester',
                'courseGrades.course',
                'courseGrades.semester'
            ])->find($id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sinh viên'
                ], 404);
            }

            // Kiểm tra quyền truy cập
            switch ($role) {
                case 'admin':
                    $advisor = Advisor::find($userId);
                    if ($student->class->faculty_id !== $advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem sinh viên này'
                        ], 403);
                    }
                    break;

                case 'advisor':
                    if ($student->class->advisor_id !== $userId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem sinh viên này'
                        ], 403);
                    }
                    break;

                case 'student':
                    if ($student->student_id !== $userId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn chỉ có thể xem thông tin của mình'
                        ], 403);
                    }
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => $student,
                'message' => 'Lấy thông tin sinh viên thành công'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo sinh viên mới (chỉ admin)
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_code' => 'required|string|max:20|unique:Students,user_code',
                'full_name' => 'required|string|max:100',
                'email' => 'required|email|unique:Students,email',
                'phone_number' => 'nullable|string|max:15',
                'class_id' => 'required|exists:Classes,class_id',
                'status' => 'nullable|in:studying,graduated,dropped,suspended,reserved',
                'position' => 'nullable|in:member,leader,vice_leader',
                'password' => 'nullable|string|min:6'
            ], [
                'user_code.required' => 'Mã sinh viên không được để trống',
                'user_code.unique' => 'Mã sinh viên đã tồn tại',
                'full_name.required' => 'Họ tên không được để trống',
                'email.required' => 'Email không được để trống',
                'email.email' => 'Email không hợp lệ',
                'email.unique' => 'Email đã tồn tại',
                'class_id.required' => 'Lớp không được để trống',
                'class_id.exists' => 'Lớp không tồn tại',
                'position.in' => 'Chức vụ không hợp lệ'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Kiểm tra admin chỉ tạo sinh viên cho lớp thuộc khoa mình quản lý
            $userId = $request->current_user_id;
            $advisor = Advisor::find($userId);
            $class = ClassModel::find($request->class_id);

            // Kiểm tra chức vụ đã tồn tại trong lớp
            if ($request->has('position') && in_array($request->position, ['leader', 'vice_leader'])) {
                $existingPosition = Student::where('class_id', $request->class_id)
                    ->where('position', $request->position)
                    ->first();

                if ($existingPosition) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Chức vụ ' . $request->position . ' đã có người đảm nhận trong lớp này'
                    ], 422);
                }
            }

            if ($class->faculty_id !== $advisor->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ có thể tạo sinh viên cho lớp thuộc khoa mình quản lý'
                ], 403);
            }

            $student = Student::create([
                'user_code' => $request->user_code,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'password_hash' => Hash::make($request->password ?? 'Password@123'),
                'phone_number' => $request->phone_number,
                'class_id' => $request->class_id,
                'status' => $request->status ?? 'studying',
                'position' => $request->position ?? 'member'
            ]);

            return response()->json([
                'success' => true,
                'data' => $student,
                'message' => 'Tạo sinh viên thành công'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload avatar cho sinh viên
     * - Admin: Upload cho sinh viên trong khoa mình quản lý
     * - Advisor: Upload cho sinh viên trong lớp mình phụ trách
     * - Student: Upload avatar của chính mình
     */
    public function uploadAvatar(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $student = Student::with('class')->find($id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sinh viên'
                ], 404);
            }

            // Kiểm tra quyền truy cập
            switch ($role) {
                case 'admin':
                    $advisor = Advisor::find($userId);
                    if (!$advisor || !$advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không tìm thấy thông tin đơn vị quản lý'
                        ], 404);
                    }

                    $studentClass = $student->class;
                    if (!$studentClass || $studentClass->faculty_id !== $advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền upload avatar cho sinh viên này'
                        ], 403);
                    }
                    break;

                case 'advisor':
                    $studentClass = $student->class;
                    if (!$studentClass || $studentClass->advisor_id !== $userId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền upload avatar cho sinh viên này'
                        ], 403);
                    }
                    break;

                case 'student':
                    if ($student->student_id !== $userId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn chỉ có thể upload avatar của mình'
                        ], 403);
                    }
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Vai trò không hợp lệ'
                    ], 403);
            }

            // Validate avatar file
            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
            ], [
                'avatar.required' => 'File avatar không được để trống',
                'avatar.image' => 'File phải là hình ảnh',
                'avatar.mimes' => 'File phải có định dạng: jpeg, png, jpg, gif',
                'avatar.max' => 'Kích thước file không được vượt quá 2MB'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Xóa avatar cũ nếu có
            if ($student->avatar_url) {
                $oldAvatarPath = str_replace('/storage/', '', $student->avatar_url);
                if (Storage::disk('public')->exists($oldAvatarPath)) {
                    Storage::disk('public')->delete($oldAvatarPath);
                }
            }

            // Lưu file mới
            $file = $request->file('avatar');
            $fileName = 'student_' . $student->student_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('avatars', $fileName, 'public');

            // Cập nhật avatar_url
            $student->avatar_url = '/storage/' . $path;
            $student->save();

            // Reload student với relationships
            $student->load(['class', 'class.advisor', 'class.faculty']);

            return response()->json([
                'success' => true,
                'data' => $student,
                'message' => 'Upload avatar thành công'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin sinh viên (không bao gồm avatar)
     * - Admin: Cập nhật sinh viên trong khoa mình quản lý
     * - Advisor: Cập nhật sinh viên trong lớp mình phụ trách
     * - Student: Cập nhật thông tin cá nhân (giới hạn)
     */
    public function update(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $student = Student::with('class')->find($id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sinh viên'
                ], 404);
            }

            // Lấy thông tin lớp của sinh viên
            $studentClass = $student->class;

            if (!$studentClass) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin lớp của sinh viên'
                ], 404);
            }

            // Kiểm tra quyền và validation dựa trên role
            if ($role === 'admin') {
                // Lấy thông tin admin
                $advisor = Advisor::find($userId);

                if (!$advisor || !$advisor->unit_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy thông tin đơn vị quản lý'
                    ], 404);
                }

                // Kiểm tra sinh viên có thuộc khoa admin quản lý không
                if ($studentClass->faculty_id !== $advisor->unit_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền cập nhật sinh viên này'
                    ], 403);
                }

                // Admin có thể cập nhật tất cả (trừ avatar)
                $validator = Validator::make($request->all(), [
                    'user_code' => 'sometimes|string|max:20|unique:Students,user_code,' . $id . ',student_id',
                    'full_name' => 'sometimes|string|max:100',
                    'email' => 'sometimes|email|unique:Students,email,' . $id . ',student_id',
                    'phone_number' => 'nullable|string|max:15',
                    'class_id' => 'sometimes|exists:Classes,class_id',
                    'status' => 'sometimes|in:studying,graduated,dropped,suspended,reserved',
                    'position' => 'sometimes|in:member,leader,vice_leader,secretary'
                ]);

                // Nếu admin đổi lớp cho sinh viên, kiểm tra lớp mới có thuộc khoa không
                if ($request->has('class_id') && $request->class_id != $student->class_id) {
                    $newClass = ClassModel::find($request->class_id);
                    if (!$newClass) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Lớp mới không tồn tại'
                        ], 404);
                    }

                    if ($newClass->faculty_id !== $advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Lớp mới không thuộc khoa bạn quản lý'
                        ], 403);
                    }
                }
            } elseif ($role === 'advisor') {
                // Advisor chỉ cập nhật sinh viên trong lớp mình phụ trách
                if ($studentClass->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền cập nhật sinh viên này'
                    ], 403);
                }

                // Advisor có thể cập nhật một số trường (trừ avatar)
                $validator = Validator::make($request->all(), [
                    'full_name' => 'sometimes|string|max:100',
                    'phone_number' => 'nullable|string|max:15',
                    'status' => 'sometimes|in:studying,graduated,dropped,suspended,reserved',
                    'position' => 'sometimes|in:member,leader,vice_leader,secretary'
                ]);

                // Advisor không được đổi lớp cho sinh viên
                if ($request->has('class_id')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cố vấn không có quyền chuyển lớp cho sinh viên'
                    ], 403);
                }
            } elseif ($role === 'student') {
                // Student chỉ cập nhật được thông tin của mình
                if ($student->student_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể cập nhật thông tin của mình'
                    ], 403);
                }

                // Student chỉ cập nhật được một số trường (trừ avatar)
                $validator = Validator::make($request->all(), [
                    'phone_number' => 'nullable|string|max:15'
                ]);

                // Student không được thay đổi các trường quan trọng
                $restrictedFields = ['user_code', 'full_name', 'email', 'class_id', 'status', 'position'];
                foreach ($restrictedFields as $field) {
                    if ($request->has($field)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Sinh viên không có quyền thay đổi trường ' . $field
                        ], 403);
                    }
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền cập nhật thông tin sinh viên'
                ], 403);
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Kiểm tra chức vụ đã tồn tại trong lớp (chỉ khi admin hoặc advisor cập nhật position)
            if (in_array($role, ['admin', 'advisor']) && $request->has('position')) {
                $newPosition = $request->position;

                // Nếu đổi position thành leader, vice_leader hoặc secretary
                if (in_array($newPosition, ['leader', 'vice_leader', 'secretary'])) {
                    // Lấy class_id: nếu admin đổi lớp thì dùng class_id mới, không thì dùng lớp hiện tại
                    $checkClassId = $request->has('class_id') ? $request->class_id : $student->class_id;

                    $existingPosition = Student::where('class_id', $checkClassId)
                        ->where('position', $newPosition)
                        ->where('student_id', '!=', $id)
                        ->first();

                    if ($existingPosition) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Chức vụ ' . $this->getPositionName($newPosition) . ' đã có người đảm nhận trong lớp này'
                        ], 422);
                    }
                }
            }

            // Cập nhật các trường
            $student->update($request->all());

            // Reload student với relationships
            $student->load(['class', 'class.advisor', 'class.faculty']);

            return response()->json([
                'success' => true,
                'data' => $student,
                'message' => 'Cập nhật sinh viên thành công'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getPositionName($position)
    {
        $positions = [
            'leader' => 'Lớp trưởng',
            'vice_leader' => 'Lớp phó',
            'secretary' => 'Thư ký',
            'member' => 'Thành viên'
        ];

        return $positions[$position] ?? $position;
    }

    /**
     * Xóa sinh viên (chỉ admin)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userId = $request->current_user_id;

            $student = Student::find($id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sinh viên'
                ], 404);
            }

            // Kiểm tra quyền
            $advisor = Advisor::find($userId);
            $class = ClassModel::find($student->class_id);

            if ($class->faculty_id !== $advisor->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa sinh viên này'
                ], 403);
            }

            $student->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa sinh viên thành công'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy báo cáo học tập của sinh viên
     */
    public function getAcademicReport(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $student = Student::with([
                'semesterReports.semester',
                'courseGrades.course',
                'courseGrades.semester',
                'academicWarnings.semester'
            ])->find($id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sinh viên'
                ], 404);
            }

            // Kiểm tra quyền
            switch ($role) {
                case 'admin':
                    $advisor = Advisor::find($userId);
                    if ($student->class->faculty_id !== $advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem báo cáo này'
                        ], 403);
                    }
                    break;

                case 'advisor':
                    if ($student->class->advisor_id !== $userId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem báo cáo này'
                        ], 403);
                    }
                    break;

                case 'student':
                    if ($student->student_id !== $userId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn chỉ có thể xem báo cáo của mình'
                        ], 403);
                    }
                    break;
            }

            $report = [
                'student_info' => [
                    'user_code' => $student->user_code,
                    'full_name' => $student->full_name,
                    'class' => $student->class->class_name
                ],
                'semester_reports' => $student->semesterReports,
                'course_grades' => $student->courseGrades->groupBy('semester_id'),
                'academic_warnings' => $student->academicWarnings
            ];

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Lấy báo cáo học tập thành công'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Đổi mật khẩu (student tự đổi)
     */
    public function changePassword(Request $request)
    {
        try {
            $userId = $request->current_user_id;

            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed'
            ], [
                'current_password.required' => 'Mật khẩu hiện tại không được để trống',
                'new_password.required' => 'Mật khẩu mới không được để trống',
                'new_password.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự',
                'new_password.confirmed' => 'Xác nhận mật khẩu không khớp'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::find($userId);

            if (!Hash::check($request->current_password, $student->password_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mật khẩu hiện tại không đúng'
                ], 400);
            }

            $student->password_hash = Hash::make($request->new_password);
            $student->save();

            return response()->json([
                'success' => true,
                'message' => 'Đổi mật khẩu thành công'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách sinh viên theo chức vụ trong lớp
     * Admin, Advisor: Xem chức vụ của các lớp thuộc quyền
     */
    public function getClassPositions(Request $request, $classId)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $class = ClassModel::find($classId);

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
                    $student = Student::where('student_id', $userId)->first();
                    if ($student->class_id !== $classId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn chỉ có thể xem chức vụ lớp của mình'
                        ], 403);
                    }
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Vai trò không hợp lệ'
                    ], 403);
            }

            $positions = [
                'leader' => Student::where('class_id', $classId)->where('position', 'leader')->first(),
                'vice_leader' => Student::where('class_id', $classId)->where('position', 'vice_leader')->first(),
                'secretary' => Student::where('class_id', $classId)->where('position', 'secretary')->first(),
                'members' => Student::where('class_id', $classId)->where('position', 'member')->get()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'class_name' => $class->class_name,
                    'positions' => $positions
                ],
                'message' => 'Lấy danh sách chức vụ thành công'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset mật khẩu sinh viên về user_code (chỉ admin)
     */
    public function resetPassword(Request $request, $id)
    {
        try {
            // Kiểm tra quyền admin
            if ($request->current_role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ admin mới có quyền reset mật khẩu'
                ], 403);
            }

            $student = Student::find($id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sinh viên'
                ], 404);
            }

            // Kiểm tra xem admin có quyền quản lý sinh viên này không
            $admin = Advisor::find($request->current_user_id);
            if (!$admin || !$admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin đơn vị quản lý'
                ], 404);
            }

            // Kiểm tra sinh viên có thuộc khoa admin quản lý không
            $studentClass = ClassModel::find($student->class_id);
            if (!$studentClass || $studentClass->faculty_id !== $admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền reset mật khẩu sinh viên này'
                ], 403);
            }

            // Reset mật khẩu về 123456
            $student->password_hash = Hash::make('123456');
            $student->save();

            return response()->json([
                'success' => true,
                'message' => "Đã reset mật khẩu của sinh viên {$student->full_name} ({$student->user_code}) về 123456 thành công"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }
}
