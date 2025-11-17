<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Advisor;
use App\Models\Course;
use App\Models\ClassModel;

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
}