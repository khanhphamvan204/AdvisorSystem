<?php

namespace App\Http\Controllers;

use App\Models\Advisor;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;


class AdvisorController extends Controller
{
    /**
     * Lấy danh sách cố vấn
     * - Admin: Xem cố vấn trong đơn vị mình quản lý
     * - Advisor: Xem thông tin bản thân
     */
    public function index(Request $request)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $query = Advisor::with(['unit', 'classes']);

            switch ($role) {
                case 'admin':
                    $advisor = Advisor::find($userId);
                    if (!$advisor || !$advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Không tìm thấy thông tin đơn vị quản lý'
                        ], 404);
                    }
                    // Admin xem cố vấn trong đơn vị mình quản lý
                    $query->where('unit_id', $advisor->unit_id);
                    break;

                case 'advisor':
                    // Advisor chỉ xem thông tin bản thân
                    $query->where('advisor_id', $userId);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Vai trò không hợp lệ'
                    ], 403);
            }

            // Filter by role
            if ($request->has('role_filter')) {
                $query->where('role', $request->role_filter);
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

            $advisors = $query->get();

            return response()->json([
                'success' => true,
                'data' => $advisors,
                'message' => 'Lấy danh sách cố vấn thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem chi tiết cố vấn
     */
    public function show(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $advisor = Advisor::with([
                'unit',
                'classes.students',
                'activities',
                'notifications'
            ])->find($id);

            if (!$advisor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cố vấn'
                ], 404);
            }

            // Kiểm tra quyền truy cập
            if ($role === 'admin') {
                $currentAdvisor = Advisor::find($userId);
                if ($advisor->unit_id !== $currentAdvisor->unit_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem thông tin cố vấn này'
                    ], 403);
                }
            } elseif ($role === 'advisor') {
                if ($advisor->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể xem thông tin của mình'
                    ], 403);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem thông tin cố vấn'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $advisor,
                'message' => 'Lấy thông tin cố vấn thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo cố vấn mới (chỉ admin)
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_code' => 'required|string|max:20|unique:Advisors,user_code',
                'full_name' => 'required|string|max:100',
                'email' => 'required|email|unique:Advisors,email',
                'phone_number' => 'nullable|string|max:15',
                'unit_id' => 'nullable|exists:Units,unit_id',
                'role' => 'required|in:advisor,admin',
                'password' => 'nullable|string|min:6'
            ], [
                'user_code.required' => 'Mã giảng viên không được để trống',
                'user_code.unique' => 'Mã giảng viên đã tồn tại',
                'full_name.required' => 'Họ tên không được để trống',
                'email.required' => 'Email không được để trống',
                'email.email' => 'Email không hợp lệ',
                'email.unique' => 'Email đã tồn tại',
                'role.required' => 'Vai trò không được để trống',
                'role.in' => 'Vai trò không hợp lệ'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Kiểm tra admin chỉ tạo cố vấn cho đơn vị mình quản lý
            $userId = $request->current_user_id;
            $currentAdvisor = Advisor::find($userId);

            if ($request->unit_id && $request->unit_id !== $currentAdvisor->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ có thể tạo cố vấn cho đơn vị mình quản lý'
                ], 403);
            }

            $advisor = Advisor::create([
                'user_code' => $request->user_code,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'password_hash' => Hash::make($request->password ?? 'Password@123'),
                'phone_number' => $request->phone_number,
                'unit_id' => $request->unit_id ?? $currentAdvisor->unit_id,
                'role' => $request->role
            ]);

            return response()->json([
                'success' => true,
                'data' => $advisor,
                'message' => 'Tạo cố vấn thành công'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload avatar cho cố vấn
     * - Admin: Upload cho cố vấn trong đơn vị mình quản lý
     * - Advisor: Upload avatar của chính mình
     */
    public function uploadAvatar(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $advisor = Advisor::with('unit')->find($id);

            if (!$advisor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cố vấn'
                ], 404);
            }

            // Kiểm tra quyền truy cập
            if ($role === 'admin') {
                // Lấy thông tin admin hiện tại
                $currentAdvisor = Advisor::find($userId);

                if (!$currentAdvisor || !$currentAdvisor->unit_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy thông tin đơn vị quản lý'
                    ], 404);
                }

                // Kiểm tra cố vấn có thuộc đơn vị admin quản lý không
                if ($advisor->unit_id !== $currentAdvisor->unit_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền upload avatar cho cố vấn này'
                    ], 403);
                }

            } elseif ($role === 'advisor') {
                // Advisor chỉ upload avatar của chính mình
                if ($advisor->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể upload avatar của mình'
                    ], 403);
                }

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền upload avatar'
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
            if ($advisor->avatar_url) {
                $oldAvatarPath = str_replace('/storage/', '', $advisor->avatar_url);
                if (Storage::disk('public')->exists($oldAvatarPath)) {
                    Storage::disk('public')->delete($oldAvatarPath);
                }
            }

            // Lưu file mới
            $file = $request->file('avatar');
            $fileName = 'advisor_' . $advisor->advisor_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('avatars', $fileName, 'public');

            // Cập nhật avatar_url
            $advisor->avatar_url = '/storage/' . $path;
            $advisor->save();

            // Reload advisor với relationships
            $advisor->load(['unit', 'classes']);

            return response()->json([
                'success' => true,
                'data' => $advisor,
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
     * Cập nhật thông tin cố vấn (không bao gồm avatar)
     * - Admin: Cập nhật cố vấn trong đơn vị mình quản lý
     * - Advisor: Cập nhật thông tin cá nhân (giới hạn)
     */
    public function update(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $advisor = Advisor::with('unit')->find($id);

            if (!$advisor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cố vấn'
                ], 404);
            }

            // Kiểm tra quyền và validation dựa trên role
            if ($role === 'admin') {
                // Lấy thông tin admin hiện tại
                $currentAdvisor = Advisor::find($userId);

                if (!$currentAdvisor || !$currentAdvisor->unit_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy thông tin đơn vị quản lý'
                    ], 404);
                }

                // Kiểm tra cố vấn cần cập nhật có thuộc đơn vị admin quản lý không
                if ($advisor->unit_id !== $currentAdvisor->unit_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền cập nhật cố vấn này'
                    ], 403);
                }

                // Admin có thể cập nhật tất cả (trừ avatar)
                $validator = Validator::make($request->all(), [
                    'user_code' => 'sometimes|string|max:20|unique:Advisors,user_code,' . $id . ',advisor_id',
                    'full_name' => 'sometimes|string|max:100',
                    'email' => 'sometimes|email|unique:Advisors,email,' . $id . ',advisor_id',
                    'phone_number' => 'nullable|string|max:15',
                    'unit_id' => 'nullable|exists:Units,unit_id',
                    'role' => 'sometimes|in:advisor,admin'
                ]);

                // Nếu admin đổi đơn vị cho cố vấn, kiểm tra đơn vị mới
                if ($request->has('unit_id') && $request->unit_id != $advisor->unit_id) {
                    $newUnit = Unit::find($request->unit_id);
                    if (!$newUnit) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Đơn vị mới không tồn tại'
                        ], 404);
                    }

                    // Admin chỉ có thể chuyển cố vấn trong cùng đơn vị mình quản lý
                    if ($request->unit_id !== $currentAdvisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn chỉ có thể chuyển cố vấn trong đơn vị mình quản lý'
                        ], 403);
                    }
                }

                // Kiểm tra nếu thay đổi role
                if ($request->has('role') && $request->role != $advisor->role) {
                    // Nếu hạ admin xuống advisor, kiểm tra xem có phải admin duy nhất không
                    if ($advisor->role === 'admin' && $request->role === 'advisor') {
                        $adminCount = Advisor::where('unit_id', $advisor->unit_id)
                            ->where('role', 'admin')
                            ->count();

                        if ($adminCount <= 1) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Không thể hạ cấp admin duy nhất của đơn vị'
                            ], 403);
                        }
                    }
                }

            } elseif ($role === 'advisor') {
                // Advisor chỉ cập nhật được thông tin của mình
                if ($advisor->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể cập nhật thông tin của mình'
                    ], 403);
                }

                // Advisor chỉ cập nhật được một số trường (trừ avatar)
                $validator = Validator::make($request->all(), [
                    'phone_number' => 'nullable|string|max:15'
                ]);

                // Advisor không được thay đổi các trường quan trọng
                $restrictedFields = ['user_code', 'full_name', 'email', 'unit_id', 'role'];
                foreach ($restrictedFields as $field) {
                    if ($request->has($field)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cố vấn không có quyền thay đổi trường ' . $field
                        ], 403);
                    }
                }

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền cập nhật thông tin cố vấn'
                ], 403);
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cập nhật các trường
            $advisor->update($request->all());

            // Reload advisor với relationships
            $advisor->load(['unit', 'classes']);

            return response()->json([
                'success' => true,
                'data' => $advisor,
                'message' => 'Cập nhật cố vấn thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa cố vấn (chỉ admin)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userId = $request->current_user_id;

            $advisor = Advisor::find($id);

            if (!$advisor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cố vấn'
                ], 404);
            }

            // Kiểm tra quyền
            $currentAdvisor = Advisor::find($userId);

            if ($advisor->unit_id !== $currentAdvisor->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa cố vấn này'
                ], 403);
            }

            // Kiểm tra cố vấn có lớp nào đang phụ trách không
            if ($advisor->classes()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa cố vấn đang phụ trách lớp'
                ], 400);
            }

            $advisor->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa cố vấn thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách lớp của cố vấn
     */
    public function getClasses(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $advisor = Advisor::with(['classes.students', 'classes.faculty'])->find($id);

            if (!$advisor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cố vấn'
                ], 404);
            }

            // Kiểm tra quyền
            if ($role === 'admin') {
                $currentAdvisor = Advisor::find($userId);
                if ($advisor->unit_id !== $currentAdvisor->unit_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem thông tin này'
                    ], 403);
                }
            } elseif ($role === 'advisor') {
                if ($advisor->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể xem lớp của mình'
                    ], 403);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $advisor->classes,
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
     * Đổi mật khẩu (advisor tự đổi)
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

            $advisor = Advisor::find($userId);

            if (!Hash::check($request->current_password, $advisor->password_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mật khẩu hiện tại không đúng'
                ], 400);
            }

            $advisor->password_hash = Hash::make($request->new_password);
            $advisor->save();

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
     * Lấy thống kê của cố vấn
     */
    public function getStatistics(Request $request, $id)
    {
        try {
            $role = $request->current_role;
            $userId = $request->current_user_id;

            $advisor = Advisor::with([
                'classes.students',
                'activities',
                'notifications',
                'meetings'
            ])->find($id);

            if (!$advisor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cố vấn'
                ], 404);
            }

            // Kiểm tra quyền
            if ($role === 'admin') {
                $currentAdvisor = Advisor::find($userId);
                if ($advisor->unit_id !== $currentAdvisor->unit_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem thông tin này'
                    ], 403);
                }
            } elseif ($role === 'advisor') {
                if ($advisor->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể xem thống kê của mình'
                    ], 403);
                }
            }

            $totalStudents = $advisor->classes->sum(function ($class) {
                return $class->students->count();
            });

            $statistics = [
                'total_classes' => $advisor->classes->count(),
                'total_students' => $totalStudents,
                'total_activities' => $advisor->activities->count(),
                'total_notifications' => $advisor->notifications->count(),
                'total_meetings' => $advisor->meetings->count(),
                'classes_detail' => $advisor->classes->map(function ($class) {
                    return [
                        'class_name' => $class->class_name,
                        'student_count' => $class->students->count()
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => 'Lấy thống kê thành công'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset mật khẩu cố vấn về user_code (chỉ admin)
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

            $advisor = Advisor::find($id);

            if (!$advisor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cố vấn'
                ], 404);
            }

            // Kiểm tra xem admin có quyền quản lý cố vấn này không
            $admin = Advisor::find($request->current_user_id);
            if (!$admin || !$admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin đơn vị quản lý'
                ], 404);
            }

            // Kiểm tra cố vấn có thuộc cùng đơn vị không
            if ($advisor->unit_id !== $admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền reset mật khẩu cố vấn này'
                ], 403);
            }

            // Không cho phép admin tự reset mật khẩu của chính mình
            if ($advisor->advisor_id == $admin->advisor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể tự reset mật khẩu của chính mình'
                ], 403);
            }

            // Reset mật khẩu về 123456
            $advisor->password_hash = Hash::make('123456');
            $advisor->save();

            return response()->json([
                'success' => true,
                'message' => "Đã reset mật khẩu của cố vấn {$advisor->full_name} ({$advisor->user_code}) về 123456 thành công"
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }
}