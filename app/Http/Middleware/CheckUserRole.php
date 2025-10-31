<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Lấy role đã được gán bởi middleware 'Authenticate'
        $userRole = $request->current_role;

        if (!$userRole) {
            // Lỗi này xảy ra nếu bạn quên bọc 'auth:api' bên ngoài
            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ: Vai trò người dùng không được xác định'
            ], 500);
        }

        // Kiểm tra quyền
        if (!in_array($userRole, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập'
            ], 403); // 403 Forbidden
        }

        return $next($request);
    }
}