<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationResponseController;
use App\Http\Controllers\NotificationRecipientController;
use Illuminate\Support\Facades\Route;

// =============== Auth Routes ===============
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

Route::prefix('auth')->middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
});

// =============== Main App Routes (Authenticated) ===============
Route::middleware('auth:api')->group(function () {

    // --- 1. Quản lý chính Thông báo ---

    // index, show (Cho cả hai vai trò, logic phân quyền nằm trong controller)
    Route::apiResource('notifications', NotificationController::class)->only(['index', 'show']);

    // store, update, destroy (Chỉ Advisor)
    Route::apiResource('notifications', NotificationController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('check_role:advisor');

    // statistics (Chỉ Advisor)
    Route::get('notification-statistics', [NotificationController::class, 'statistics'])
        ->middleware('check_role:advisor');


    // --- 2. Quản lý Phản hồi của Thông báo ---

    // Lấy danh sách phản hồi (Chỉ Advisor)
    Route::get('notifications/{notificationId}/responses', [NotificationResponseController::class, 'index'])
        ->middleware('check_role:advisor');

    // Gửi phản hồi (Chỉ Student)
    Route::post('notifications/{notificationId}/responses', [NotificationResponseController::class, 'store'])
        ->middleware('check_role:student');

    // Trả lời 1 phản hồi cụ thể (Chỉ Advisor)
    Route::put('notification-responses/{responseId}', [NotificationResponseController::class, 'update'])
        ->middleware('check_role:advisor');


    // --- 3. Quản lý Trạng thái Thông báo (Chỉ Student) ---
    Route::prefix('student')->middleware('check_role:student')->group(function () {

        // Lấy danh sách chưa đọc
        Route::get('unread-notifications', [NotificationRecipientController::class, 'index']);

        // Đánh dấu tất cả đã đọc
        Route::post('mark-all-notifications-read', [NotificationRecipientController::class, 'markAllAsRead']);
    });
});