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
use App\Http\Controllers\CourseController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\AcademicMonitoringController;
use App\Http\Controllers\ActivityStatisticsController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\SemesterController;



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

    // Xem danh sách hoạt động đã tham gia kèm vai trò
    Route::get('/my-participated-activities', [ActivityRegistrationController::class, 'getMyParticipatedActivities']);

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


Route::middleware(['auth.api', 'check_role:admin'])->group(function () {
    Route::prefix('activities/statistics')->group(function () {
        Route::get('/class/{classId}', [ActivityStatisticsController::class, 'getClassActivityStatistics']);
        Route::get('/activity/{activityId}', [ActivityStatisticsController::class, 'getActivityClassStatistics']);
        Route::get('/faculty/overview', [ActivityStatisticsController::class, 'getFacultyOverviewStatistics']);
    });
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


/**
 * ============================================================
 * ROUTES QUẢN LÝ MÔN HỌC (COURSES)
 * ============================================================
 */

Route::middleware(['auth.api'])->group(function () {

    /**
     * ROUTES CHO ADMIN - QUẢN LÝ MÔN HỌC
     * Admin chỉ được quản lý môn học thuộc khoa của mình
     */
    Route::middleware(['check_role:admin'])->prefix('courses')->group(function () {

        // Xem danh sách môn học thuộc khoa của admin
        Route::get('/my-unit-courses', [CourseController::class, 'getMyUnitCourses']);

        // Tạo môn học mới (chỉ thuộc khoa của mình)
        Route::post('/', [CourseController::class, 'store']);

        // Cập nhật môn học (chỉ môn học thuộc khoa của mình)
        Route::put('/{course_id}', [CourseController::class, 'update']);

        // Xóa môn học (chỉ môn học thuộc khoa của mình)
        Route::delete('/{course_id}', [CourseController::class, 'destroy']);
    });

    /**
     * ROUTES CHO STUDENT - MÔN HỌC
     */
    Route::middleware(['check_role:student'])->prefix('courses')->group(function () {

        // Xem danh sách môn học của mình
        Route::get('/my-courses', [CourseController::class, 'getMyCourses']);
    });

    /**
     * ROUTES PUBLIC - Xem danh sách môn học
     */
    Route::prefix('courses')->group(function () {

        // Danh sách tất cả môn học (có phân trang và tìm kiếm)
        Route::get('/', [CourseController::class, 'index']);

        // Chi tiết môn học
        Route::get('/{course_id}', [CourseController::class, 'show']);
    });



    /**
     * ROUTES CHO ADVISOR - MÔN HỌC
     */
    Route::middleware(['check_role:advisor'])->prefix('courses')->group(function () {

        // Xem danh sách sinh viên học một môn
        Route::get('/{course_id}/students', [CourseController::class, 'getCourseStudents']);
    });


});

/**
 * ============================================================
 * ROUTES QUẢN LÝ ĐIỂM (GRADES)
 * ============================================================
 */

Route::middleware(['auth.api'])->group(function () {

    /**
     * ROUTES CHO STUDENT - ĐIỂM
     */
    Route::middleware(['check_role:student'])->prefix('grades')->group(function () {

        // Xem điểm của chính mình
        Route::get('/my-grades', [GradeController::class, 'getMyGrades']);
    });

    /**
     * ROUTES CHO ADVISOR - ĐIỂM
     */
    Route::middleware(['check_role:advisor'])->prefix('grades')->group(function () {

        // Xem điểm của sinh viên trong lớp
        Route::get('/student/{student_id}', [GradeController::class, 'getStudentGrades']);

        // Xuất điểm cả lớp
        Route::get(
            '/export-class-grades/{class_id}/{semester_id}',
            [GradeController::class, 'exportClassGrades']
        );
    });

    /**
     * ROUTES CHO ADMIN - QUẢN LÝ ĐIỂM
     * Admin chỉ được nhập/sửa/xóa điểm cho sinh viên thuộc các lớp trong khoa mình
     */
    Route::middleware(['check_role:admin'])->prefix('grades')->group(function () {

        // Nhập điểm cho sinh viên (chỉ sinh viên thuộc lớp trong khoa mình)
        Route::post('/', [GradeController::class, 'store']);

        // Cập nhật điểm (chỉ sinh viên thuộc lớp trong khoa mình)
        Route::put('/{grade_id}', [GradeController::class, 'update']);

        // Nhập điểm hàng loạt (chỉ sinh viên thuộc lớp trong khoa mình)
        Route::post('/batch-import', [GradeController::class, 'batchImport']);

        // Xóa điểm (chỉ sinh viên thuộc lớp trong khoa mình)
        Route::delete('/{grade_id}', [GradeController::class, 'destroy']);
    });
});


/**
 * ============================================================
 * ROUTES THEO DÕI HỌC VỤ VÀ CẢNH CÁO HỌC VỤ
 * ============================================================
 */

Route::middleware(['auth.api'])->group(function () {

    /**
     * ROUTES CHO STUDENT - HỌC VỤ
     */
    Route::middleware(['check_role:student'])->prefix('academic')->group(function () {

        // Xem báo cáo học kỳ của chính mình
        Route::get('/my-semester-report/{semester_id}', function ($semesterId) {
            $request = request();
            $studentId = $request->current_user_id;
            return app(AcademicMonitoringController::class)
                ->getSemesterReport($request, $studentId, $semesterId);
        });

        // Xem danh sách cảnh cáo học vụ của mình
        Route::get('/my-warnings', [AcademicMonitoringController::class, 'getMyWarnings']);
    });

    /**
     * ROUTES CHO ADVISOR - HỌC VỤ
     */
    Route::middleware(['check_role:advisor'])->prefix('academic')->group(function () {

        // Xem báo cáo học kỳ của sinh viên (trong lớp mình quản lý)
        Route::get(
            '/semester-report/{student_id}/{semester_id}',
            [AcademicMonitoringController::class, 'getSemesterReport']
        );

        // Xem danh sách sinh viên có nguy cơ bỏ học
        Route::get('/at-risk-students', [AcademicMonitoringController::class, 'getAtRiskStudents']);

        // Tự động tạo cảnh cáo học vụ
        Route::post('/create-warnings', [AcademicMonitoringController::class, 'createAcademicWarnings']);

        // Xem danh sách cảnh cáo đã tạo
        Route::get('/warnings-created', [AcademicMonitoringController::class, 'getWarningsCreated']);

        // Thống kê tổng quan học vụ
        Route::get('/statistics', [AcademicMonitoringController::class, 'getAcademicStatistics']);

        // Cập nhật báo cáo học kỳ (tính lại GPA, CPA, điểm DRL/CTXH)
        Route::post('/update-semester-report', [AcademicMonitoringController::class, 'updateSemesterReport']);

        // Cập nhật báo cáo hàng loạt cho cả lớp
        Route::post('/batch-update-semester-reports', [AcademicMonitoringController::class, 'batchUpdateSemesterReports']);
    });

    /**
     * ROUTES CHO STUDENT - ĐIỂM RÈN LUYỆN & CTXH
     */
    Route::middleware(['check_role:student'])->prefix('points')->group(function () {

        // Xem điểm rèn luyện và CTXH của chính mình trong học kỳ
        Route::get(
            '/my-semester-points/{semester_id}',
            [PointManagementController::class, 'getMySemesterPoints']
        );

        // Xem lịch sử tham gia hoạt động
        Route::get(
            '/my-activity-history',
            [PointManagementController::class, 'getMyActivityHistory']
        );

        // Xem tổng điểm tích lũy
        Route::get(
            '/my-total-points',
            [PointManagementController::class, 'getMyTotalPoints']
        );
    });

    /**
     * ROUTES CHO ADVISOR - ĐIỂM RÈN LUYỆN & CTXH
     */
    Route::middleware(['check_role:advisor'])->prefix('points')->group(function () {

        // Xem điểm của sinh viên trong lớp mình
        Route::get(
            '/student-semester-points/{student_id}/{semester_id}',
            [PointManagementController::class, 'getStudentSemesterPoints']
        );

        // Cập nhật điểm cho một sinh viên
        Route::post(
            '/update-student-points',
            [PointManagementController::class, 'updateStudentPoints']
        );

        // Cập nhật điểm hàng loạt cho cả lớp
        Route::post(
            '/batch-update-class-points',
            [PointManagementController::class, 'batchUpdateClassPoints']
        );

        // Xem tổng hợp điểm của cả lớp
        Route::get(
            '/class-points-summary/{class_id}/{semester_id}',
            [PointManagementController::class, 'getClassPointsSummary']
        );

        // Xem sinh viên có điểm thấp
        Route::get(
            '/low-points-students',
            [PointManagementController::class, 'getLowPointsStudents']
        );
    });
});


// Routes cho Class Management
Route::middleware(['auth.api'])->group(function () {

    // Routes cho tất cả roles (xem danh sách và chi tiết)
    Route::get('/classes', [ClassController::class, 'index']);
    Route::get('/classes/{id}', [ClassController::class, 'show']);
    Route::get('/classes/{id}/students', [ClassController::class, 'getStudents']);

    // Routes chỉ cho Admin (tạo, sửa, xóa)
    Route::middleware(['check_role:admin'])->group(function () {
        Route::post('/classes', [ClassController::class, 'store']);
        Route::put('/classes/{id}', [ClassController::class, 'update']);
        Route::delete('/classes/{id}', [ClassController::class, 'destroy']);
    });
});

// Routes cho Semester Management
Route::middleware(['auth.api'])->group(function () {

    Route::get('/semesters/current', [SemesterController::class, 'getCurrentSemester']);

    // Xem báo cáo học kỳ (có phân quyền trong controller)
    Route::get('/semesters/{semesterId}/students/{studentId}/report', [SemesterController::class, 'getStudentReport']);
    Route::get('/semesters/{id}/reports', [SemesterController::class, 'getSemesterReports']);


    // Routes cho tất cả roles (xem học kỳ)
    Route::get('/semesters', [SemesterController::class, 'index']);
    Route::get('/semesters/{id}', [SemesterController::class, 'show']);




    // Routes chỉ cho Admin (quản lý học kỳ)
    Route::middleware(['check_role:admin'])->group(function () {
        Route::post('/semesters', [SemesterController::class, 'store']);
        Route::put('/semesters/{id}', [SemesterController::class, 'update']);
        Route::delete('/semesters/{id}', [SemesterController::class, 'destroy']);
    });
});


