<?php

namespace App\Http\Controllers;

use App\Services\ActivityAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ActivityAttendanceController extends Controller
{
    protected $attendanceService;

    public function __construct(ActivityAttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Export danh sách đăng ký hoạt động ra Excel
     * GET /api/activities/{activityId}/export-registrations
     * 
     * @param Request $request
     * @param int $activityId
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function exportRegistrations(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        $result = $this->attendanceService->exportRegistrations($activityId, $currentUserId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], $result['message'] === 'Bạn không có quyền xuất danh sách hoạt động này' ? 403 : 400);
        }

        // Trả về file để download
        return response()->download($result['file_path'], $result['file_name'])->deleteFileAfterSend(true);
    }

    /**
     * Export file mẫu điểm danh (template)
     * GET /api/activities/{activityId}/export-attendance-template
     * 
     * @param Request $request
     * @param int $activityId
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function exportAttendanceTemplate(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        $result = $this->attendanceService->exportAttendanceTemplate($activityId, $currentUserId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], $result['message'] === 'Bạn không có quyền xuất file mẫu cho hoạt động này' ? 403 : 400);
        }

        return response()->download($result['file_path'], $result['file_name'])->deleteFileAfterSend(true);
    }

    /**
     * Import file điểm danh và cập nhật trạng thái
     * POST /api/activities/{activityId}/import-attendance
     * 
     * @param Request $request
     * @param int $activityId
     * @return \Illuminate\Http\JsonResponse
     */
    public function importAttendance(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        // Validate file upload
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls|max:5120' // Max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'File không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Lưu file tạm
        $file = $request->file('file');
        $fileName = 'temp_attendance_' . time() . '.' . $file->getClientOriginalExtension();

        // Đảm bảo thư mục temp tồn tại
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Lưu file trực tiếp với đường dẫn đầy đủ
        $fullPath = $tempDir . '/' . $fileName;
        $file->move($tempDir, $fileName);

        try {
            // Kiểm tra file đã được lưu chưa
            if (!file_exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể lưu file tạm'
                ], 500);
            }

            // Import
            $result = $this->attendanceService->importAttendance($fullPath, $activityId, $currentUserId);

            // Xóa file tạm
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], $result['message'] === 'Bạn không có quyền cập nhật điểm danh cho hoạt động này' ? 403 : 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ], 200);

        } catch (\Exception $e) {
            // Xóa file tạm nếu có lỗi
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xử lý file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thống kê điểm danh
     * GET /api/activities/{activityId}/attendance-statistics
     * 
     * @param Request $request
     * @param int $activityId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAttendanceStatistics(Request $request, $activityId)
    {
        $currentUserId = $request->current_user_id;

        $activity = \App\Models\Activity::find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Hoạt động không tồn tại'
            ], 404);
        }

        if ($activity->advisor_id != $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem thống kê này'
            ], 403);
        }

        // Lấy thống kê
        $registrations = \App\Models\ActivityRegistration::whereHas('role', function ($q) use ($activityId) {
            $q->where('activity_id', $activityId);
        })->get();

        $statistics = [
            'total' => $registrations->count(),
            'registered' => $registrations->where('status', 'registered')->count(),
            'attended' => $registrations->where('status', 'attended')->count(),
            'absent' => $registrations->where('status', 'absent')->count(),
            'cancelled' => $registrations->where('status', 'cancelled')->count(),
            'attendance_rate' => 0
        ];

        // Tính tỷ lệ tham gia (nếu có sinh viên đã được điểm danh)
        $totalChecked = $statistics['attended'] + $statistics['absent'];
        if ($totalChecked > 0) {
            $statistics['attendance_rate'] = round(($statistics['attended'] / $totalChecked) * 100, 2);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'activity_id' => $activity->activity_id,
                'activity_title' => $activity->title,
                'activity_status' => $activity->status,
                'statistics' => $statistics
            ]
        ], 200);
    }
}