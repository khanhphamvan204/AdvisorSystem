<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Facades\Log;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        try {
            $token = JWTAuth::getToken(); // Lấy token từ request
            if (!$token) {
                Log::warning('Không cung cấp token.');
                return response()->json([
                    'success' => false,
                    'error' => 'Yêu cầu xác thực.'
                ], 401);
            }

            $user = JWTAuth::parseToken()->authenticate();
            $userRole = $user->role;
            Log::info('Vai trò người dùng: ' . $userRole);

            if (!in_array($userRole, $roles)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Quyền truy cập bị từ chối. Bạn không có vai trò phù hợp.'
                ], 403);
            }

            return $next($request);

        } catch (TokenExpiredException $e) {
            Log::warning('Token đã hết hạn: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Token đã hết hạn. Vui lòng đăng nhập lại.'
            ], 401);
        } catch (TokenInvalidException $e) {
            Log::warning('Token không hợp lệ: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Token không hợp lệ.'
            ], 401);
        } catch (JWTException $e) {
            Log::error('Lỗi JWT: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Không được phép.'
            ], 401);
        } catch (\Exception $e) {
            Log::error('Lỗi không xác định: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Đã có lỗi xảy ra.'
            ], 500);
        }
    }
}