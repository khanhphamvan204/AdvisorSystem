<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Advisor;
use App\Models\Course;
use App\Models\ClassModel;
use App\Models\Notification;
use App\Models\Meeting;
use App\Models\NotificationRecipient;

class StatisticsController extends Controller
{
    /**
     * Lấy thống kê tổng quan cho Dashboard Admin Khoa
     * Admin chỉ xem được thống kê của khoa mình quản lý
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardOverview(Request $request)
    {
        try {
            // Lấy thông tin từ middleware
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            // Kiểm tra quyền admin
            if ($currentRole !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ admin mới có quyền truy cập thống kê tổng quan'
                ], 403);
            }

            // Lấy thông tin admin và unit_id (khoa) của admin
            $admin = Advisor::find($currentUserId);

            if (!$admin || !$admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin chưa được gán vào khoa nào'
                ], 400);
            }

            $unitId = $admin->unit_id;

            // Thống kê tổng số giảng viên thuộc khoa này
            $totalAdvisors = Advisor::where('role', 'advisor')
                ->where('unit_id', $unitId)
                ->count();

            // Thống kê tổng số sinh viên thuộc khoa này
            // Sinh viên -> Class -> Faculty (unit_id)
            $totalStudents = Student::whereHas('class', function ($query) use ($unitId) {
                $query->where('faculty_id', $unitId);
            })->count();

            // Thống kê tổng số môn học thuộc khoa này
            $totalCourses = Course::where('unit_id', $unitId)->count();

            // Thống kê tổng số lớp học thuộc khoa này
            $totalClasses = ClassModel::where('faculty_id', $unitId)->count();

            return response()->json([
                'success' => true,
                'message' => 'Lấy thống kê tổng quan thành công',
                'data' => [
                    'unit_id' => $unitId,
                    'unit_name' => $admin->unit->unit_name ?? null,
                    'total_advisors' => $totalAdvisors,
                    'total_students' => $totalStudents,
                    'total_courses' => $totalCourses,
                    'total_classes' => $totalClasses
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thống kê: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thống kê tổng quan cho Cố vấn học tập
     * Advisor chỉ xem được thống kê của các lớp mình quản lý
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAdvisorOverview(Request $request)
    {
        try {
            // Lấy thông tin từ middleware
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            // Kiểm tra quyền advisor
            if ($currentRole !== 'advisor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ cố vấn học tập mới có quyền truy cập'
                ], 403);
            }

            // Lấy thông tin advisor
            $advisor = Advisor::with('unit')->find($currentUserId);

            if (!$advisor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin cố vấn'
                ], 404);
            }

            // Thống kê tổng số sinh viên mà advisor quản lý
            $totalStudents = Student::whereHas('class', function ($query) use ($currentUserId) {
                $query->where('advisor_id', $currentUserId);
            })->count();

            // Thống kê tổng số lớp mà advisor quản lý
            $totalClasses = ClassModel::where('advisor_id', $currentUserId)->count();

            // Thống kê tổng số thông báo đã gửi
            $totalNotifications = Notification::where('advisor_id', $currentUserId)->count();

            // Thống kê tổng số cuộc họp đã tổ chức
            $totalMeetings = Meeting::where('advisor_id', $currentUserId)->count();

            // Thống kê cuộc họp sắp tới (scheduled)
            $upcomingMeetings = Meeting::where('advisor_id', $currentUserId)
                ->where('status', 'scheduled')
                ->where('meeting_time', '>', now())
                ->count();

            // Thống kê thông báo chưa đọc (có thể tính từ recipients)
            $unreadNotifications = NotificationRecipient::whereHas('notification', function ($query) use ($currentUserId) {
                $query->where('advisor_id', $currentUserId);
            })
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Lấy thống kê cố vấn thành công',
                'data' => [
                    'advisor_id' => $advisor->advisor_id,
                    'advisor_name' => $advisor->full_name,
                    'unit_name' => $advisor->unit->unit_name ?? null,
                    'total_students' => $totalStudents,
                    'total_classes' => $totalClasses,
                    'total_notifications' => $totalNotifications,
                    'total_meetings' => $totalMeetings,
                    'upcoming_meetings' => $upcomingMeetings,
                    'unread_notifications' => $unreadNotifications
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thống kê: ' . $e->getMessage()
            ], 500);
        }
    }
}