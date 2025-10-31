<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    /**
     * Xử lý request, xác thực token và gán user/role vào request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {

            // Lấy token từ request
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token không được cung cấp'
                ], 401);
            }


            // Parse payload
            $payload = JWTAuth::getPayload($token);
            $userRole = $payload->get('role');
            $userId = $payload->get('id');

            // Gán ID và Role vào request
            $request->merge([
                'current_role' => $userRole,
                'current_user_id' => $userId
            ]);

            return $next($request);

        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token đã hết hạn'
            ], 401);

        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token không hợp lệ'
            ], 401);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi xác thực: ' . $e->getMessage()
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi không xác định: ' . $e->getMessage()
            ], 500);
        }
    }
}
