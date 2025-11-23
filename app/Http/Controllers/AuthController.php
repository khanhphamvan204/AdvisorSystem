<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Student;
use App\Models\Advisor;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\RateLimiter;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Xử lý đăng nhập cho Student, Advisor, và Admin
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        try {
            // SỬA 1: Cho phép 'admin' đăng nhập
            $request->validate([
                'user_code' => 'required|string',
                'password' => 'required|string|min:6',
                'role' => 'required|in:student,advisor,admin', // Thêm 'admin'
            ]);

            $userCode = $request->user_code;
            $role = $request->role; // Vai trò người dùng CHỌN KHI ĐĂNG NHẬP
            $key = "login|{$userCode}|{$role}|{$request->ip()}";

            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'error' => 'Too many attempts',
                    'message' => "Quá nhiều lần thử. Vui lòng đợi {$seconds} giây."
                ], 429);
            }

            // SỬA 2: Tìm user dựa trên role
            $user = null;
            if ($role === 'student') {
                $user = Student::where('user_code', $userCode)->first();
            } elseif ($role === 'advisor' || $role === 'admin') {
                // Admin và Advisor đều nằm trong bảng Advisors
                $user = Advisor::where('user_code', $userCode)->first();
            }

            // Kiểm tra user và mật khẩu
            if (!$user || !Hash::check($request->password, $user->password_hash)) {
                RateLimiter::hit($key, 60);
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid credentials',
                    'message' => 'Mã số hoặc mật khẩu không đúng.'
                ], 401);
            }

            // SỬA 3: KIỂM TRA QUYỀN TRƯỚC KHI TẠO TOKEN
            // Lấy role thực tế từ CSDL
            $actualRole = ($role === 'student') ? 'student' : $user->role;

            // Chặn trường hợp: Advisor (GV001) cố đăng nhập với role 'admin'
            // Hoặc Admin (ADMIN001) cố đăng nhập với role 'advisor'
            if ($role !== $actualRole) {
                RateLimiter::hit($key, 60);
                return response()->json([
                    'success' => false,
                    'error' => 'Role mismatch',
                    'message' => 'Vai trò đăng nhập không khớp với tài khoản này.'
                ], 401);
            }

            RateLimiter::clear($key);

            // SỬA 4: TẠO TOKEN
            // Xóa $customClaims.
            // Hàm fromUser() sẽ tự động gọi getJWTCustomClaims() trong Model
            // (Model Advisor sẽ trả về role='admin' nếu là admin)
            $token = JWTAuth::fromUser($user);

            // Cập nhật last_login
            $user->update(['last_login' => now()]);

            // SỬA 5: CHUẨN BỊ USER DATA
            // Lấy ID và Role từ $user (CSDL) thay vì $request
            $userData = [
                'id' => $user->getKey(), // Dùng getKey() cho cả 2 model
                'user_code' => $user->user_code,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $actualRole, // Lấy vai trò thực tế từ CSDL
                'avatar_url' => $user->avatar_url,
                'phone_number' => $user->phone_number,
            ];

            // Thêm thông tin đặc thù
            if ($actualRole === 'student') {
                $user->load(['class.faculty', 'class.advisor']);
                $userData['class'] = [
                    'class_id' => $user->class->class_id,
                    'class_name' => $user->class->class_name,
                    'faculty' => $user->class->faculty ? [
                        'unit_id' => $user->class->faculty->unit_id,
                        'unit_name' => $user->class->faculty->unit_name,
                        'type' => $user->class->faculty->type,
                    ] : null,
                    'advisor' => $user->class->advisor ? [
                        'advisor_id' => $user->class->advisor->advisor_id,
                        'full_name' => $user->class->advisor->full_name,
                    ] : null
                ];
                $userData['status'] = $user->status;

            } elseif ($actualRole === 'advisor' || $actualRole === 'admin') {
                $user->load(['unit', 'classes']);
                $userData['unit'] = $user->unit ? [
                    'unit_id' => $user->unit->unit_id,
                    'unit_name' => $user->unit->unit_name,
                    'type' => $user->unit->type,
                ] : null;
                // Admin không quản lý lớp, chỉ advisor mới có
                if ($actualRole === 'advisor') {
                    $userData['classes'] = $user->classes->map(function ($class) {
                        return [
                            'class_id' => $class->class_id,
                            'class_name' => $class->class_name,
                        ];
                    });
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'user' => $userData
                ]
            ], 200);

        } catch (ValidationException $ve) {
            return response()->json(['success' => false, 'error' => 'Validation failed', 'errors' => $ve->errors()], 422);
        } catch (JWTException $e) {
            return response()->json(['success' => false, 'error' => 'JWT Error', 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Unexpected error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Lấy thông tin user hiện tại
     * GET /api/auth/me
     */
    public function me()
    {
        try {
            $token = JWTAuth::getToken();
            $payload = JWTAuth::getPayload($token);

            $role = $payload->get('role'); // Lấy role TỪ TOKEN
            $id = $payload->get('id');

            $user = null;
            $userData = [
                'id' => $id,
                'role' => $role
            ];

            if ($role === 'student') {
                $user = Student::with(['class.faculty', 'class.advisor'])->find($id);
            }
            // SỬA 6: Gộp 'advisor' và 'admin' vì cùng 1 bảng
            elseif ($role === 'advisor' || $role === 'admin') {
                $user = Advisor::with([
                    'unit',
                    'classes' => function ($query) {
                        $query->withCount('students')->with('faculty');
                    }
                ])->find($id);
            }

            if (!$user) {
                return response()->json(['success' => false, 'error' => 'Unauthorized', 'message' => 'Token không hợp lệ.'], 401);
            }

            // Xây dựng response data
            $userData = array_merge($userData, [
                'user_code' => $user->user_code,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'phone_number' => $user->phone_number,
                'created_at' => $user->created_at,
                'last_login' => $user->last_login,
            ]);

            // Thêm chi tiết dựa trên role
            if ($role === 'student') {
                $userData['status'] = $user->status;
                $userData['class'] = [
                    'class_id' => $user->class->class_id,
                    'class_name' => $user->class->class_name,
                    'description' => $user->class->description,
                    'faculty' => [
                        'unit_id' => $user->class->faculty->unit_id,
                        'unit_name' => $user->class->faculty->unit_name,
                        'type' => $user->class->faculty->type
                    ],
                    'advisor' => $user->class->advisor ? [
                        'advisor_id' => $user->class->advisor->advisor_id,
                        'full_name' => $user->class->advisor->full_name,
                        'email' => $user->class->advisor->email,
                        'phone_number' => $user->class->advisor->phone_number
                    ] : null
                ];
            } elseif ($role === 'advisor' || $role === 'admin') {
                $userData['unit'] = $user->unit ? [
                    'unit_id' => $user->unit->unit_id,
                    'unit_name' => $user->unit->unit_name,
                    'type' => $user->unit->type,
                    'description' => $user->unit->description
                ] : null;
                // Chỉ Advisor mới có danh sách lớp
                if ($role === 'advisor') {
                    $userData['classes'] = $user->classes->map(function ($class) {
                        return [
                            'class_id' => $class->class_id,
                            'class_name' => $class->class_name,
                            'description' => $class->description,
                        ];
                    });
                }
            }

            return response()->json([
                'success' => true,
                'data' => $userData
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Token error', 'message' => $e->getMessage()], 401);
        }
    }

    /**
     * Xử lý đăng xuất
     * POST /api/auth/logout
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json([
                'success' => true,
                'message' => 'Đăng xuất thành công'
            ], 200);
        } catch (JWTException $e) {
            return response()->json(['success' => false, 'error' => 'JWT Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Làm mới token
     * POST /api/auth/refresh
     */
    public function refresh()
    {
        try {
            $token = JWTAuth::getToken();
            if (!$token) {
                return response()->json(['success' => false, 'error' => 'Token missing', 'message' => 'Không tìm thấy token trong request.'], 401);
            }

            $newToken = JWTAuth::refresh($token);

            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $newToken,
                    'token_type' => 'bearer',
                    'expires_in' => JWTAuth::factory()->getTTL() * 60
                ]
            ]);
        } catch (JWTException $e) {
            return response()->json(['success' => false, 'error' => 'Refresh failed', 'message' => $e->getMessage()], 401);
        }
    }
}