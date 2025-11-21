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
use App\Http\Controllers\ScheduleImportController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\AdvisorController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\DialogController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\PointFeedbackController;
use App\Http\Controllers\StudentMonitoringNoteController;
use App\Http\Controllers\ActivityAttendanceController;


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
    Route::middleware(['check_role:advisor,admin'])->prefix('grades')->group(function () {

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

        // Excel Import/Export
        Route::get('/download-template', [GradeController::class, 'downloadTemplate']);
        Route::post('/import-excel', [GradeController::class, 'importFromExcel']);
        Route::get('/export-excel/{class_id}/{semester_id}', [GradeController::class, 'exportToExcel']);

        // Xem danh sách sinh viên và điểm trong khoa
        Route::get('/faculty-students', [GradeController::class, 'getFacultyStudentsGrades']);

        // Xem tổng quan điểm của khoa
        Route::get('/faculty-overview', [GradeController::class, 'getFacultyGradesOverview']);
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

Route::middleware(['auth.api', 'check_role:admin'])->prefix('admin/schedules')->group(function () {
    // Download template lịch học
    Route::get('/download-template', [ScheduleImportController::class, 'downloadTemplate']);

    // Import lịch học
    Route::post('/import', [ScheduleImportController::class, 'import']);

    // Tìm kiếm sinh viên theo lịch học (Admin search toàn bộ)
    Route::post('/search', [ScheduleImportController::class, 'searchBySchedule']);

    // Xóa lịch học của sinh viên trong học kỳ
    Route::delete('/student/{student_id}', [ScheduleImportController::class, 'deleteStudentSchedule']);
});

Route::middleware(['auth.api', 'check_role:admin,advisor,student'])->prefix('admin/schedules')->group(function () {
    // Xem lịch học của một sinh viên
    Route::get('/student/{student_id}', [ScheduleImportController::class, 'getStudentSchedule']);

    // Xem lịch học của cả lớp
    Route::get('/class/{class_id}', [ScheduleImportController::class, 'getClassSchedule']);
});

Route::middleware(['auth.api'])->prefix('schedules')->group(function () {
    // Kiểm tra xung đột lịch
    Route::post('/check-conflict', [ScheduleImportController::class, 'checkConflict']);
});

Route::middleware(['auth.api'])->group(function () {

    // ============================================================
    // CLASSES ROUTES - Tất cả role có thể xem, chỉ admin CRUD
    // ============================================================
    Route::prefix('classes')->group(function () {
        // Xem danh sách và chi tiết - Tất cả role
        Route::get('/', [ClassController::class, 'index']);
        Route::get('/{id}', [ClassController::class, 'show']);
        Route::get('/{id}/students', [ClassController::class, 'getStudents']);

        // CRUD - Chỉ admin
        Route::middleware(['check_role:admin'])->group(function () {
            Route::post('/', [ClassController::class, 'store']);
            Route::put('/{id}', [ClassController::class, 'update']);
            Route::delete('/{id}', [ClassController::class, 'destroy']);
        });
    });

    // ============================================================
// STUDENTS ROUTES
// ============================================================
    Route::prefix('students')->group(function () {
        // Xem danh sách và chi tiết - Tất cả role
        Route::get('/', [StudentController::class, 'index']);
        Route::get('/{id}', [StudentController::class, 'show']);
        Route::get('/{id}/academic-report', [StudentController::class, 'getAcademicReport']);

        // Đổi mật khẩu - Student tự đổi
        Route::middleware(['check_role:student'])->group(function () {
            Route::post('/change-password', [StudentController::class, 'changePassword']);
        });

        // Cập nhật thông tin cá nhân - Student, Admin và Advisor
        Route::middleware(['check_role:student,admin,advisor'])->group(function () {
            Route::put('/{id}', [StudentController::class, 'update']);
            Route::post('/{id}/avatar', [StudentController::class, 'uploadAvatar']);
        });

        // CRUD - Chỉ admin
        Route::middleware(['check_role:admin'])->group(function () {
            Route::post('/', [StudentController::class, 'store']);
            Route::delete('/{id}', [StudentController::class, 'destroy']);
            Route::post('/{id}/reset-password', [StudentController::class, 'resetPassword']);
        });
    });

    // ============================================================
// CLASS POSITIONS ROUTES
// ============================================================
    Route::get('/classes/{classId}/positions', [StudentController::class, 'getClassPositions']);

    // ============================================================
// ADVISORS ROUTES
// ============================================================
    Route::prefix('advisors')->group(function () {
        // Xem danh sách và chi tiết - Admin và Advisor
        Route::middleware(['check_role:admin,advisor'])->group(function () {
            Route::get('/', [AdvisorController::class, 'index']);
            Route::get('/{id}', [AdvisorController::class, 'show']);
            Route::get('/{id}/classes', [AdvisorController::class, 'getClasses']);
            Route::get('/{id}/statistics', [AdvisorController::class, 'getStatistics']);
        });

        // Đổi mật khẩu - Advisor tự đổi
        Route::middleware(['check_role:advisor,admin'])->group(function () {
            Route::post('/change-password', [AdvisorController::class, 'changePassword']);
        });

        // Cập nhật thông tin - Advisor và Admin
        Route::middleware(['check_role:advisor,admin'])->group(function () {
            Route::put('/{id}', [AdvisorController::class, 'update']);
            Route::post('/{id}/avatar', [AdvisorController::class, 'uploadAvatar']);
        });

        // CRUD - Chỉ admin
        Route::middleware(['check_role:admin'])->group(function () {
            Route::post('/', [AdvisorController::class, 'store']);
            Route::delete('/{id}', [AdvisorController::class, 'destroy']);
            Route::post('/{id}/reset-password', [AdvisorController::class, 'resetPassword']);
        });
    });

    // ============================================================
    // IMPORT/EXPORT ROUTES - Chỉ admin
    // ============================================================
    Route::prefix('import-export')->middleware(['check_role:admin'])->group(function () {
        // Download templates
        Route::get('/templates/download', [ImportExportController::class, 'downloadTemplates']);

        // Import
        Route::post('/classes/import', [ImportExportController::class, 'importClasses']);
        Route::post('/advisors/import', [ImportExportController::class, 'importAdvisors']);
        Route::post('/students/import', [ImportExportController::class, 'importStudents']);

        // Export
        Route::get('/classes/export', [ImportExportController::class, 'exportClasses']);
        Route::get('/students/{classId}/export', [ImportExportController::class, 'exportStudents']);
    });

    // ============================================================
    // DIALOG ROUTES - Student và Advisor
    // ============================================================
    Route::prefix('dialogs')->middleware(['check_role:student,advisor'])->group(function () {
        // Lấy danh sách hội thoại
        Route::get('/conversations', [DialogController::class, 'getConversations']);

        // Lấy tin nhắn trong hội thoại
        Route::get('/messages', [DialogController::class, 'getMessages']);

        // Gửi tin nhắn
        Route::post('/messages', [DialogController::class, 'sendMessage']);

        // Đánh dấu đã đọc
        Route::put('/messages/{id}/read', [DialogController::class, 'markAsRead']);

        // Xóa tin nhắn
        Route::delete('/messages/{id}', [DialogController::class, 'deleteMessage']);

        // Số tin nhắn chưa đọc
        Route::get('/unread-count', [DialogController::class, 'getUnreadCount']);

        // Tìm kiếm tin nhắn
        Route::get('/messages/search', [DialogController::class, 'searchMessages']);
    });

});

// ===================================================================
// QUẢN LÝ CÁC CUỘC HỌP (MEETINGS)
// ===================================================================


// ===================================================================
// NHÓM ROUTES CHO SINH VIÊN, CVHT, ADMIN (CẦN ĐĂNG NHẬP)
// ===================================================================
Route::middleware(['auth.api'])->group(function () {

    // Xem danh sách cuộc họp (phân quyền tự động trong controller)
    Route::get('meetings', [MeetingController::class, 'index']);

    // Xem chi tiết cuộc họp
    Route::get('meetings/{id}', [MeetingController::class, 'show']);

    // Tải biên bản đã lưu
    Route::get('meetings/{id}/download-minutes', [MeetingController::class, 'downloadMinutes']);

    // Sinh viên gửi feedback về cuộc họp
    Route::post('meetings/{id}/feedbacks', [MeetingController::class, 'storeFeedback']);

    // Xem danh sách feedback của cuộc họp
    Route::get('meetings/{id}/feedbacks', [MeetingController::class, 'getFeedbacks']);
});

// ===================================================================
// NHÓM ROUTES CHỈ CHO CVHT VÀ ADMIN
// ===================================================================
Route::middleware(['auth.api', 'check_role:advisor,admin'])->group(function () {

    // Tạo cuộc họp mới
    Route::post('meetings', [MeetingController::class, 'store']);

    // Cập nhật thông tin cuộc họp
    Route::put('meetings/{id}', [MeetingController::class, 'update']);

    // Xóa cuộc họp
    Route::delete('meetings/{id}', [MeetingController::class, 'destroy']);

    // Điểm danh sinh viên
    Route::post('meetings/{id}/attendance', [MeetingController::class, 'updateAttendance']);

    // Xuất biên bản họp tự động
    Route::get('meetings/{id}/export-minutes', [MeetingController::class, 'exportMinutes']);

    // Upload biên bản thủ công
    Route::post('meetings/{id}/upload-minutes', [MeetingController::class, 'uploadMinutes']);

    // Xóa biên bản
    Route::delete('meetings/{id}/minutes', [MeetingController::class, 'deleteMinutes']);

    // Cập nhật nội dung họp và ý kiến lớp
    Route::put('meetings/{id}/summary', [MeetingController::class, 'updateSummary']);

    // Thống kê cuộc họp
    Route::get('meetings/statistics/overview', [MeetingController::class, 'getStatistics']);
});

Route::middleware(['auth.api', 'check_role:admin'])->group(function () {
    Route::get('/statistics/dashboard-overview', [StatisticsController::class, 'getDashboardOverview']);
});

// ===================================================================
// PHẢN HỒI ĐIỂM RÈN LUYỆN / CTXH (POINT FEEDBACKS)
// ===================================================================
Route::middleware(['auth.api'])->prefix('point-feedbacks')->group(function () {

    // Xem danh sách phản hồi (có phân quyền tự động trong controller)
    // Student: Xem phản hồi của mình
    // Advisor: Xem phản hồi của sinh viên trong lớp mình phụ trách
    Route::get('/', [PointFeedbackController::class, 'index']);

    // Xem chi tiết một phản hồi
    Route::get('/{id}', [PointFeedbackController::class, 'show']);

    // Thống kê phản hồi (Advisor only)
    Route::get('/statistics/overview', [PointFeedbackController::class, 'statistics'])
        ->middleware('check_role:advisor');

    // Sinh viên tạo phản hồi mới
    Route::post('/', [PointFeedbackController::class, 'store'])
        ->middleware('check_role:student');

    // Sinh viên cập nhật phản hồi (chỉ khi status = pending)
    Route::put('/{id}', [PointFeedbackController::class, 'update'])
        ->middleware('check_role:student');

    // Sinh viên xóa phản hồi (chỉ khi status = pending)
    Route::delete('/{id}', [PointFeedbackController::class, 'destroy'])
        ->middleware('check_role:student');

    // Cố vấn phản hồi và phê duyệt/từ chối
    Route::post('/{id}/respond', [PointFeedbackController::class, 'respond'])
        ->middleware('check_role:advisor');
});

// ===================================================================
// GHI CHÚ THEO DÕI SINH VIÊN (MONITORING NOTES)
// ===================================================================
Route::middleware(['auth.api'])->prefix('monitoring-notes')->group(function () {

    // Xem danh sách ghi chú (có phân quyền tự động trong controller)
    // Student: Xem ghi chú về mình
    // Advisor: Xem ghi chú của sinh viên trong lớp mình phụ trách
    Route::get('/', [StudentMonitoringNoteController::class, 'index']);

    // Xem chi tiết một ghi chú
    Route::get('/{id}', [StudentMonitoringNoteController::class, 'show']);

    // Xem timeline ghi chú của một sinh viên
    Route::get('/student/{student_id}/timeline', [StudentMonitoringNoteController::class, 'studentTimeline']);

    // Thống kê ghi chú (Advisor only)
    Route::get('/statistics/overview', [StudentMonitoringNoteController::class, 'statistics'])
        ->middleware('check_role:advisor');

    // Cố vấn tạo ghi chú mới
    Route::post('/', [StudentMonitoringNoteController::class, 'store'])
        ->middleware('check_role:advisor');

    // Cố vấn cập nhật ghi chú (chỉ ghi chú do mình tạo)
    Route::put('/{id}', [StudentMonitoringNoteController::class, 'update'])
        ->middleware('check_role:advisor');

    // Cố vấn xóa ghi chú (chỉ ghi chú do mình tạo)
    Route::delete('/{id}', [StudentMonitoringNoteController::class, 'destroy'])
        ->middleware('check_role:advisor');
});


/*
|--------------------------------------------------------------------------
| Activity Attendance Routes
|--------------------------------------------------------------------------
|
| Routes để quản lý import/export điểm danh hoạt động
| Chỉ dành cho Advisor
|
*/

Route::middleware(['auth.api', 'check_role:advisor'])->group(function () {

    // ===== EXPORT =====

    // Export danh sách đăng ký hoạt động (tất cả sinh viên đã đăng ký)
    Route::get(
        '/activities/{activityId}/export-registrations',
        [ActivityAttendanceController::class, 'exportRegistrations']
    );

    // Export file mẫu điểm danh (template để điền)
    Route::get(
        '/activities/{activityId}/export-attendance-template',
        [ActivityAttendanceController::class, 'exportAttendanceTemplate']
    );

    // ===== IMPORT =====

    // Import file điểm danh (cập nhật trạng thái attended/absent)
    Route::post(
        '/activities/{activityId}/import-attendance',
        [ActivityAttendanceController::class, 'importAttendance']
    );

    // ===== STATISTICS =====

    // Xem thống kê điểm danh
    Route::get(
        '/activities/{activityId}/attendance-statistics',
        [ActivityAttendanceController::class, 'getAttendanceStatistics']
    );
});

