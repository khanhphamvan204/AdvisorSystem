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
     * Xử lý đăng nhập cho cả Student và Advisor
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'user_code' => 'required|string',
                'password' => 'required|string|min:6',
                'role' => 'required|in:student,advisor', // Xác định đăng nhập với vai trò nào
            ]);

            $userCode = $request->user_code;
            $role = $request->role;
            $key = "login|{$userCode}|{$role}|{$request->ip()}";

            // Rate limiting
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'error' => 'Too many attempts',
                    'message' => "Quá nhiều lần thử. Vui lòng đợi {$seconds} giây."
                ], 429);
            }

            // Tìm user dựa trên role
            $user = null;
            if ($role === 'student') {
                $user = Student::where('user_code', $userCode)->first();
            } elseif ($role === 'advisor') {
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

            RateLimiter::clear($key);

            // Tạo token với thông tin role
            $customClaims = [
                'role' => $role,
                'id' => $role === 'student' ? $user->student_id : $user->advisor_id
            ];

            $token = JWTAuth::claims($customClaims)->fromUser($user);

            // Cập nhật last_login
            $user->update(['last_login' => now()]);

            // Chuẩn bị thông tin trả về
            $userData = [
                'id' => $role === 'student' ? $user->student_id : $user->advisor_id,
                'user_code' => $user->user_code,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $role,
                'avatar_url' => $user->avatar_url,
                'phone_number' => $user->phone_number,
            ];

            // Thêm thông tin đặc thù theo role
            if ($role === 'student') {
                $user->load('class.faculty');
                $userData['class'] = $user->class;
                $userData['status'] = $user->status;
            } elseif ($role === 'advisor') {
                $user->load('unit');
                $userData['unit'] = $user->unit;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'user' => $userData
                ]
            ], 200);

        } catch (ValidationException $ve) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $ve->errors()
            ], 422);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => 'JWT Error',
                'message' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error',
                'message' => $e->getMessage()
            ], 500);
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

            $role = $payload->get('role');
            $id = $payload->get('id');

            $user = null;
            if ($role === 'student') {
                $user = Student::with('class.faculty', 'class.advisor')->find($id);
            } elseif ($role === 'advisor') {
                $user = Advisor::with('unit', 'classes')->find($id);
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'Token không hợp lệ.'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'user_code' => $user->user_code,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'role' => $role,
                    'avatar_url' => $user->avatar_url,
                    'phone_number' => $user->phone_number,
                    'details' => $user
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Token error',
                'message' => $e->getMessage()
            ], 401);
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
            return response()->json([
                'success' => false,
                'error' => 'JWT Error',
                'message' => $e->getMessage()
            ], 500);
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
                return response()->json([
                    'success' => false,
                    'error' => 'Token missing',
                    'message' => 'Không tìm thấy token trong request.'
                ], 401);
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
            return response()->json([
                'success' => false,
                'error' => 'Refresh failed',
                'message' => $e->getMessage()
            ], 401);
        }
    }
}