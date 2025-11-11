<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ClassModel;
use App\Models\ActivityRegistration;
use App\Models\Advisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller thống kê hoạt động cho Admin
 * Chỉ admin mới được truy cập
 */
class ActivityStatisticsController extends Controller
{
    /**
     * Thống kê hoạt động của một lớp cụ thể
     * Role: Admin only
     * Chỉ thống kê các hoạt động thuộc khoa của admin
     */
    public function getClassActivityStatistics(Request $request, $classId)
    {
        $currentRole = $request->current_role;
        $adminId = $request->current_user_id;

        // Kiểm tra role phải là admin
        if ($currentRole !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ admin mới có quyền truy cập'
            ], 403);
        }

        // Lấy thông tin admin và unit
        $admin = Advisor::with('unit:unit_id,unit_name,type')->find($adminId);

        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin đơn vị của admin'
            ], 404);
        }

        // Lấy thông tin lớp
        $class = ClassModel::with([
            'advisor:advisor_id,full_name,email,phone_number',
            'faculty:unit_id,unit_name'
        ])->find($classId);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Lớp không tồn tại'
            ], 404);
        }

        // Kiểm tra lớp có thuộc khoa của admin không
        if ($class->faculty_id != $admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Lớp này không thuộc khoa bạn quản lý'
            ], 403);
        }

        // Lấy tất cả sinh viên trong lớp
        $totalStudents = $class->students()->count();

        // Lấy danh sách hoạt động được gán cho lớp này
        $activities = Activity::whereHas('classes', function ($q) use ($classId) {
            $q->where('classes.class_id', $classId);
        })
            ->with([
                'advisor:advisor_id,full_name',
                'organizerUnit:unit_id,unit_name'
            ])
            ->withCount([
                'roles as total_roles'
            ])
            ->orderBy('start_time', 'desc')
            ->get();

        // Thống kê chi tiết cho từng hoạt động
        $activityStatistics = $activities->map(function ($activity) use ($classId) {
            // Đếm số lượng đăng ký của sinh viên trong lớp này
            $registrations = ActivityRegistration::whereHas('role', function ($q) use ($activity) {
                $q->where('activity_id', $activity->activity_id);
            })
                ->whereHas('student', function ($q) use ($classId) {
                    $q->where('class_id', $classId);
                })
                ->with('student:student_id,user_code,full_name,class_id')
                ->get();

            $statusCount = [
                'registered' => $registrations->where('status', 'registered')->count(),
                'attended' => $registrations->where('status', 'attended')->count(),
                'absent' => $registrations->where('status', 'absent')->count(),
                'cancelled' => $registrations->where('status', 'cancelled')->count()
            ];

            // Tính điểm đã nhận (chỉ attended)
            $attendedRegistrations = $registrations->where('status', 'attended');

            $pointsBreakdown = [
                'training_points' => $attendedRegistrations->where('role.point_type', 'ren_luyen')
                    ->sum('role.points_awarded'),
                'social_points' => $attendedRegistrations->where('role.point_type', 'ctxh')
                    ->sum('role.points_awarded')
            ];

            return [
                'activity_id' => $activity->activity_id,
                'title' => $activity->title,
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
                'location' => $activity->location,
                'status' => $activity->status,
                'organizer' => $activity->organizerUnit?->unit_name ?? 'N/A',
                'advisor_name' => $activity->advisor?->full_name ?? 'N/A',
                'total_roles' => $activity->total_roles,
                'total_registered' => $registrations->count(),
                'status_breakdown' => $statusCount,
                'participation_rate' => $registrations->count() . ' sinh viên',
                'points_earned' => [
                    'training_points' => $pointsBreakdown['training_points'],
                    'social_points' => $pointsBreakdown['social_points'],
                    'total' => $pointsBreakdown['training_points'] + $pointsBreakdown['social_points']
                ]
            ];
        });

        // Tính tổng hợp
        $summary = [
            'total_activities' => $activities->count(),
            'activities_by_status' => [
                'upcoming' => $activities->where('status', 'upcoming')->count(),
                'ongoing' => $activities->where('status', 'ongoing')->count(),
                'completed' => $activities->where('status', 'completed')->count(),
                'cancelled' => $activities->where('status', 'cancelled')->count()
            ],
            'total_registrations' => $activityStatistics->sum('total_registered'),
            'total_attended' => $activityStatistics->sum('status_breakdown.attended'),
            'total_points_earned' => [
                'training_points' => $activityStatistics->sum('points_earned.training_points'),
                'social_points' => $activityStatistics->sum('points_earned.social_points'),
                'total' => $activityStatistics->sum('points_earned.total')
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'class_info' => [
                    'class_id' => $class->class_id,
                    'class_name' => $class->class_name,
                    'faculty_name' => $class->faculty?->unit_name ?? 'N/A',
                    'advisor_name' => $class->advisor?->full_name ?? 'N/A',
                    'advisor_email' => $class->advisor?->email ?? 'N/A',
                    'total_students' => $totalStudents
                ],
                'admin_info' => [
                    'unit_name' => $admin->unit->unit_name,
                    'unit_type' => $admin->unit->type
                ],
                'summary' => $summary,
                'activities' => $activityStatistics
            ]
        ], 200);
    }

    /**
     * Thống kê các lớp tham gia một hoạt động cụ thể
     * Role: Admin only
     * Chỉ thống kê hoạt động thuộc khoa của admin
     */
    public function getActivityClassStatistics(Request $request, $activityId)
    {
        $currentRole = $request->current_role;
        $adminId = $request->current_user_id;

        // Kiểm tra role phải là admin
        if ($currentRole !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ admin mới có quyền truy cập'
            ], 403);
        }

        // Lấy thông tin admin và unit
        $admin = Advisor::with('unit:unit_id,unit_name,type')->find($adminId);

        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin đơn vị của admin'
            ], 404);
        }

        // Lấy thông tin hoạt động
        $activity = Activity::with([
            'advisor:advisor_id,full_name,email',
            'organizerUnit:unit_id,unit_name',
            'classes' => function ($q) {
                $q->select('classes.class_id', 'class_name', 'faculty_id')
                    ->with('faculty:unit_id,unit_name');
            },
            'roles:activity_role_id,activity_id,role_name,points_awarded,point_type,max_slots'
        ])->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động không tồn tại'
            ], 404);
        }

        // Kiểm tra xem có lớp nào thuộc khoa của admin không
        $hasClassInUnit = $activity->classes->contains(function ($class) use ($admin) {
            return $class->faculty_id == $admin->unit_id;
        });

        if (!$hasClassInUnit) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động này không có lớp nào thuộc khoa bạn quản lý'
            ], 403);
        }

        // Lọc chỉ lấy các lớp thuộc khoa của admin
        $classesInUnit = $activity->classes->filter(function ($class) use ($admin) {
            return $class->faculty_id == $admin->unit_id;
        });

        // Thống kê cho từng lớp
        $classStatistics = $classesInUnit->map(function ($class) use ($activity) {
            // Đếm tổng số sinh viên trong lớp
            $totalStudents = $class->students()->count();

            // Lấy danh sách đăng ký của sinh viên trong lớp này
            $registrations = ActivityRegistration::whereHas('role', function ($q) use ($activity) {
                $q->where('activity_id', $activity->activity_id);
            })
                ->whereHas('student', function ($q) use ($class) {
                    $q->where('class_id', $class->class_id);
                })
                ->with([
                    'student:student_id,user_code,full_name',
                    'role:activity_role_id,role_name,points_awarded,point_type'
                ])
                ->get();

            // Lọc bỏ các đăng ký đã bị hủy
            $activeRegistrations = $registrations->whereNotIn('status', ['cancelled']);

            // Đếm số lượng theo trạng thái
            $statusCount = [
                'registered' => $registrations->where('status', 'registered')->count(),
                'attended' => $registrations->where('status', 'attended')->count(),
                'absent' => $registrations->where('status', 'absent')->count(),
                'cancelled' => $registrations->where('status', 'cancelled')->count()
            ];

            // Tính tỷ lệ tham gia (không tính cancelled)
            $participationRate = $totalStudents > 0
                ? round(($activeRegistrations->count() / $totalStudents) * 100, 2)
                : 0;

            // Thống kê theo vai trò (không tính cancelled)
            $roleBreakdown = $activeRegistrations->groupBy('role.role_name')->map(function ($items, $roleName) {
                return [
                    'role_name' => $roleName,
                    'count' => $items->count(),
                    'attended' => $items->where('status', 'attended')->count()
                ];
            })->values();

            // Danh sách sinh viên đã tham gia (attended)
            $attendedStudents = $registrations->where('status', 'attended')
                ->map(function ($reg) {
                    return [
                        'student_id' => $reg->student->student_id,
                        'user_code' => $reg->student->user_code,
                        'full_name' => $reg->student->full_name,
                        'role_name' => $reg->role->role_name,
                        'points_awarded' => $reg->role->points_awarded,
                        'point_type' => $reg->role->point_type
                    ];
                })->values();

            //Danh sách sinh viên đã đăng ký (registered)
            $registeredStudents = $registrations->where('status', 'registered')
                ->map(function ($reg) {
                    return [
                        'student_id' => $reg->student->student_id,
                        'user_code' => $reg->student->user_code,
                        'full_name' => $reg->student->full_name,
                        'role_name' => $reg->role->role_name,
                        'points_awarded' => $reg->role->points_awarded,
                        'point_type' => $reg->role->point_type
                    ];
                })->values();

            return [
                'class_id' => $class->class_id,
                'class_name' => $class->class_name,
                'faculty_name' => $class->faculty?->unit_name ?? 'N/A',
                'total_students' => $totalStudents,
                'total_registered' => $activeRegistrations->count(),
                'participation_rate' => $participationRate . '%',
                'status_breakdown' => $statusCount,
                'role_breakdown' => $roleBreakdown,
                'registered_students' => $registeredStudents,
                'attended_students' => $attendedStudents
            ];
        })->values();

        // Tổng hợp thống kê
        $summary = [
            'total_classes' => $classStatistics->count(),
            'total_students_in_classes' => $classStatistics->sum('total_students'),
            'total_registered' => $classStatistics->sum('total_registered'),
            'total_attended' => $classStatistics->sum('status_breakdown.attended'),
            'average_participation_rate' => $classStatistics->count() > 0
                ? round($classStatistics->avg(function ($class) {
                    return floatval(str_replace('%', '', $class['participation_rate']));
                }), 2) . '%'
                : '0%'
        ];

        // Trả về kết quả
        return response()->json([
            'success' => true,
            'data' => [
                'activity_info' => [
                    'activity_id' => $activity->activity_id,
                    'title' => $activity->title,
                    'start_time' => $activity->start_time,
                    'end_time' => $activity->end_time,
                    'location' => $activity->location,
                    'status' => $activity->status,
                    'organizer' => $activity->organizerUnit?->unit_name ?? 'N/A',
                    'advisor_name' => $activity->advisor?->full_name ?? 'N/A',
                    'total_roles' => $activity->roles->count()
                ],
                'admin_info' => [
                    'unit_name' => $admin->unit->unit_name,
                    'unit_type' => $admin->unit->type
                ],
                'summary' => $summary,
                'classes' => $classStatistics
            ]
        ], 200);
    }


    /**
     * Thống kê tổng quan tất cả lớp trong khoa
     * Role: Admin only
     */
    public function getFacultyOverviewStatistics(Request $request)
    {
        $currentRole = $request->current_role;
        $adminId = $request->current_user_id;

        // Kiểm tra role phải là admin
        if ($currentRole !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ admin mới có quyền truy cập'
            ], 403);
        }

        // Lấy thông tin admin và unit
        $admin = Advisor::with('unit:unit_id,unit_name,type')->find($adminId);

        if (!$admin || !$admin->unit_id) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin đơn vị của admin'
            ], 404);
        }

        // Lấy tất cả lớp thuộc khoa
        $classes = ClassModel::where('faculty_id', $admin->unit_id)
            ->with('advisor:advisor_id,full_name')
            ->get();

        // Thống kê cho từng lớp
        $classesStatistics = $classes->map(function ($class) {
            $totalStudents = $class->students()->count();

            // Đếm số hoạt động được gán cho lớp
            $totalActivities = Activity::whereHas('classes', function ($q) use ($class) {
                $q->where('classes.class_id', $class->class_id);
            })->count();

            // Đếm tổng số đăng ký của sinh viên trong lớp
            $totalRegistrations = ActivityRegistration::whereHas('student', function ($q) use ($class) {
                $q->where('class_id', $class->class_id);
            })->count();

            // Đếm số sinh viên đã attended ít nhất 1 hoạt động
            $activeStudents = ActivityRegistration::whereHas('student', function ($q) use ($class) {
                $q->where('class_id', $class->class_id);
            })
                ->where('status', 'attended')
                ->distinct('student_id')
                ->count('student_id');

            return [
                'class_id' => $class->class_id,
                'class_name' => $class->class_name,
                'advisor_name' => $class->advisor?->full_name ?? 'N/A',
                'total_students' => $totalStudents,
                'total_activities' => $totalActivities,
                'total_registrations' => $totalRegistrations,
                'active_students' => $activeStudents,
                'activity_participation_rate' => $totalStudents > 0
                    ? round(($activeStudents / $totalStudents) * 100, 2) . '%'
                    : '0%'
            ];
        });

        $summary = [
            'total_classes' => $classes->count(),
            'total_students' => $classesStatistics->sum('total_students'),
            'total_activities_assigned' => $classesStatistics->sum('total_activities'),
            'total_registrations' => $classesStatistics->sum('total_registrations'),
            'total_active_students' => $classesStatistics->sum('active_students')
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'faculty_info' => [
                    'unit_name' => $admin->unit->unit_name,
                    'unit_type' => $admin->unit->type
                ],
                'summary' => $summary,
                'classes' => $classesStatistics
            ]
        ], 200);
    }
}