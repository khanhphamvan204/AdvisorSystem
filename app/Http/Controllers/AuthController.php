<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\RateLimiter;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Xử lý đăng nhập
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'user_code' => 'required|string|exists:users,user_code',
                'password' => 'required|string|min:6',
            ]);

            $userCode = $request->user_code;
            $key = "login|{$userCode}|{$request->ip()}";

            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'error' => 'Too many attempts',
                    'message' => "Quá nhiều lần thử. Vui lòng đợi {$seconds} giây."
                ], 429);
            }

            $user = User::where('user_code', $userCode)->first();

            if (!$user || !Hash::check($request->password, $user->password_hash)) {
                RateLimiter::hit($key, 60);
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid credentials',
                    'message' => 'Mã số hoặc mật khẩu không đúng.'
                ], 401);
            }

            RateLimiter::clear($key);

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'user' => $user->only(['user_id', 'user_code', 'full_name', 'email', 'role', 'avatar_url'])
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
     */
    public function me()
    {
        try {
            /** @var \App\Models\User $user */
            $user = JWTAuth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'Token không hợp lệ.'
                ], 401);
            }

            // Load thêm thông tin chi tiết tùy theo vai trò
            $user->load(['student.class.faculty', 'advisor.unit']);

            return response()->json([
                'success' => true,
                'data' => $user
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