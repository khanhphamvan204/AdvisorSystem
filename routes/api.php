<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationResponseController;
use App\Http\Controllers\NotificationRecipientController;



// ========== Authentication Routes ==========
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.api');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth.api');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth.api');
});

// ========== Protected Routes ==========
Route::middleware(['auth.api'])->group(function () {

    // ===== Notification Routes =====
    Route::prefix('notifications')->group(function () {

        // Advisor only routes
        Route::middleware('check_role:advisor')->group(function () {
            Route::post('/', [NotificationController::class, 'store']);
            Route::put('/{id}', [NotificationController::class, 'update']);
            Route::delete('/{id}', [NotificationController::class, 'destroy']);
            Route::get('/notification-statistics', [NotificationController::class, 'statistics']);
        });
        // Common routes (cả Student và Advisor)
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/{id}', [NotificationController::class, 'show']);



        // Notification Responses
        Route::post('/{notificationId}/responses', [NotificationResponseController::class, 'store'])
            ->middleware('check_role:student');
        Route::get('/{notificationId}/responses', [NotificationResponseController::class, 'index'])
            ->middleware('check_role:advisor');
    });

    // Update notification response (Advisor only)
    Route::put('/notification-responses/{responseId}', [NotificationResponseController::class, 'update'])
        ->middleware('check_role:advisor');

    // ===== Student Notification Routes =====
    Route::prefix('student')->middleware('check_role:student')->group(function () {
        Route::get('/unread-notifications', [NotificationRecipientController::class, 'index']);
        Route::post('/mark-all-notifications-read', [NotificationRecipientController::class, 'markAllAsRead']);
    });
});