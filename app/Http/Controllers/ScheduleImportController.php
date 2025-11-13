<?php

namespace App\Http\Controllers;

use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage; // <--- THÊM DÒNG NÀY

class ScheduleImportController extends Controller
{
    protected $scheduleService;

    public function __construct(ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    /**
     * Import lịch học từ Excel
     * POST /api/admin/schedules/import
     * Role: Admin only
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls|max:10240' // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'File không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $fileName = 'schedule_import_' . time() . '.xlsx';

            // 1. Lưu file vào disk 'local' (storage/app)
            $relativePath = $file->storeAs('temp', $fileName, 'local');

            // 2. Lấy đường dẫn tuyệt đối chuẩn của hệ điều hành (Sửa lỗi đường dẫn)
            $absolutePath = Storage::disk('local')->path($relativePath);

            // Gọi Service xử lý
            $result = $this->scheduleService->importSchedulesFromExcel($absolutePath);

            // 3. Xóa file temp bằng Storage cho an toàn
            Storage::disk('local')->delete($relativePath);

            Log::info('Imported schedules', [
                'admin_id' => $request->input('current_user_id'), // Sửa cách lấy user id nếu cần
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Import thành công',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            // Nếu có lỗi, cố gắng xóa file nếu nó còn tồn tại
            if (isset($relativePath) && Storage::disk('local')->exists($relativePath)) {
                Storage::disk('local')->delete($relativePath);
            }

            Log::error('Failed to import schedules', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // Log thêm trace để dễ debug
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kiểm tra xung đột lịch
     * POST /api/schedules/check-conflict
     */
    public function checkConflict(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|integer|exists:Students,student_id',
            'activity_id' => 'required|integer|exists:Activities,activity_id',
            'semester_id' => 'required|integer|exists:Semesters,semester_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Lấy thông tin activity từ database
            $activity = \App\Models\Activity::find($request->activity_id);

            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hoạt động không tồn tại'
                ], 404);
            }

            $result = $this->scheduleService->checkScheduleConflict(
                $request->student_id,
                $activity->start_time,
                $activity->end_time,
                $request->semester_id
            );

            // Thêm thông tin activity vào response
            $result['activity'] = [
                'activity_id' => $activity->activity_id,
                'title' => $activity->title,
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time
            ];

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}