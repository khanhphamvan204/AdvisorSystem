<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function ($schedule) {
        // Tự động cập nhật trạng thái hoạt động mỗi ngày lúc 00:01
        $schedule->command('activities:update-status')
            ->dailyAt('00:01')
            ->timezone('Asia/Ho_Chi_Minh');

        // Chạy thêm vào các thời điểm trong ngày để đảm bảo chính xác
        $schedule->command('activities:update-status')
            ->cron('1 8,12,18 * * *')  // 8:01, 12:01, 18:01 mỗi ngày
            ->timezone('Asia/Ho_Chi_Minh');
    })
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->alias([
            'auth.api' => \App\Http\Middleware\Authenticate::class,
            'check_role' => \App\Http\Middleware\CheckUserRole::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            return route('login');
        });

    })
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthenticated.',
                    'message' => 'Token không hợp lệ hoặc đã hết hạn.'
                ], 401);
            }
        });

    })->create();