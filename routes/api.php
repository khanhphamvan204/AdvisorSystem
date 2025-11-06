<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationResponseController;
use App\Http\Controllers\NotificationRecipientController;
use App\Http\Controllers\PointManagementController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityRoleController;
use App\Http\Controllers\ActivityRegistrationController;



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


/*
|--------------------------------------------------------------------------
| Activity Management Routes
|--------------------------------------------------------------------------
| Các route quản lý hoạt động, vai trò và đăng ký hoạt động
|--------------------------------------------------------------------------
*/

// =====================================================
// HOẠT ĐỘNG (ACTIVITIES) - Dành cho cả Student & Advisor
// =====================================================
Route::middleware(['auth.api'])->prefix('activities')->group(function () {

    // Xem danh sách hoạt động (public cho cả student và advisor)
    Route::get('/', [ActivityController::class, 'index']);

    // Xem chi tiết hoạt động
    Route::get('/{activityId}', [ActivityController::class, 'show']);

    // =====================================================
    // QUẢN LÝ HOẠT ĐỘNG - Chỉ dành cho Advisor
    // =====================================================
    Route::middleware(['check_role:advisor'])->group(function () {
        // Tạo hoạt động mới
        Route::post('/', [ActivityController::class, 'store']);

        // Cập nhật hoạt động
        Route::put('/{activityId}', [ActivityController::class, 'update']);

        // Xóa hoạt động
        Route::delete('/{activityId}', [ActivityController::class, 'destroy']);

        // Xem danh sách sinh viên đã đăng ký hoạt động
        Route::get('/{activityId}/registrations', [ActivityController::class, 'getRegistrations']);

        // Cập nhật điểm danh
        Route::post('/{activityId}/attendance', [ActivityController::class, 'updateAttendance']);

        // ===== PHÂN CÔNG SINH VIÊN (MỚI) =====
        // Xem danh sách sinh viên có thể phân công (trong lớp của CVHT)
        Route::get('/{activityId}/available-students', [ActivityController::class, 'getAvailableStudents']);

        // Phân công sinh viên tham gia hoạt động
        Route::post('/{activityId}/assign-students', [ActivityController::class, 'assignStudents']);

        // Hủy phân công sinh viên
        Route::delete('/{activityId}/assignments/{registrationId}', [ActivityController::class, 'removeAssignment']);
    });
});

// =====================================================
// VAI TRÒ HOẠT ĐỘNG (ACTIVITY ROLES)
// =====================================================
Route::middleware(['auth.api'])->prefix('activities/{activityId}/roles')->group(function () {

    // Xem danh sách vai trò của hoạt động (public)
    Route::get('/', [ActivityRoleController::class, 'index']);

    // Xem chi tiết vai trò
    Route::get('/{roleId}', [ActivityRoleController::class, 'show']);

    // =====================================================
    // QUẢN LÝ VAI TRÒ - Chỉ dành cho Advisor
    // =====================================================
    Route::middleware(['check_role:advisor'])->group(function () {
        // Thêm vai trò mới vào hoạt động
        Route::post('/', [ActivityRoleController::class, 'store']);

        // Cập nhật vai trò
        Route::put('/{roleId}', [ActivityRoleController::class, 'update']);

        // Xóa vai trò
        Route::delete('/{roleId}', [ActivityRoleController::class, 'destroy']);

        // Xem danh sách sinh viên đã đăng ký vai trò này
        Route::get('/{roleId}/registrations', [ActivityRoleController::class, 'getRegistrations']);


    });
});

// =====================================================
// ĐĂNG KÝ HOẠT ĐỘNG - Dành cho Student
// =====================================================
Route::middleware(['auth.api', 'check_role:student'])->prefix('activity-registrations')->group(function () {

    // Đăng ký tham gia hoạt động (role cụ thể)
    Route::post('/register', [ActivityRegistrationController::class, 'register']);

    // Xem danh sách hoạt động đã đăng ký
    Route::get('/my-registrations', [ActivityRegistrationController::class, 'myRegistrations']);

    // Tạo yêu cầu hủy đăng ký
    Route::post('/cancel', [ActivityRegistrationController::class, 'cancelRegistration']);

    // Xem danh sách yêu cầu hủy của mình
    Route::get('/my-cancellation-requests', [ActivityRegistrationController::class, 'myCancellationRequests']);
});

// =====================================================
// YÊU CẦU HỦY ĐĂNG KÝ - Dành cho Advisor
// =====================================================
Route::middleware(['auth.api', 'check_role:advisor'])->prefix('activities/{activityId}/cancellation-requests')->group(function () {

    // Xem danh sách yêu cầu hủy của hoạt động
    Route::get('/', [ActivityRegistrationController::class, 'getCancellationRequests']);

    // Duyệt/từ chối yêu cầu hủy
    Route::patch('/{requestId}', [ActivityRegistrationController::class, 'approveCancellation']);
});


/*
|--------------------------------------------------------------------------
| API Routes - Quản lý Điểm và Hoạt động
|--------------------------------------------------------------------------
*/


// ============================================================
// QUẢN LÝ ĐIỂM RÈN LUYỆN, ĐIỂM CTXH
// ============================================================
Route::middleware(['auth.api'])->prefix('student-points')->group(function () {

    // Xem điểm rèn luyện, CTXH
    // Student: Xem điểm của chính mình
    // Advisor: Xem điểm của sinh viên trong lớp
    Route::get('/', [PointManagementController::class, 'getStudentPoints'])
        ->middleware('check_role:student,advisor');

    // Xem tổng hợp điểm cả lớp (Advisor only)
    Route::get('/class-summary', [PointManagementController::class, 'getClassPointsSummary'])
        ->middleware('check_role:advisor');
});
