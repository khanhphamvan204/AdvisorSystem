<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ExportPointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExportPointController extends Controller
{
    protected $exportService;

    public function __construct(ExportPointService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Xuất điểm rèn luyện theo lớp
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function exportTrainingPointsByClass(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'class_id' => 'required|integer|exists:Classes,class_id',
                'semester_id' => 'required|integer|exists:Semesters,semester_id'
            ], [
                'class_id.required' => 'Mã lớp không được để trống',
                'class_id.exists' => 'Lớp không tồn tại',
                'semester_id.required' => 'Mã học kỳ không được để trống',
                'semester_id.exists' => 'Học kỳ không tồn tại'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Gọi service xuất file
            $result = $this->exportService->exportTrainingPointsByClass(
                $request->class_id,
                $request->semester_id
            );

            // Xóa tất cả output buffer trước khi download file
            if (ob_get_length()) {
                ob_end_clean();
            }

            // Trả về file download
            return response()->download(
                $result['file_path'],
                $result['file_name'],
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất điểm rèn luyện',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xuất điểm rèn luyện theo khoa (lấy faculty_id từ admin đang đăng nhập)
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function exportTrainingPointsByFaculty(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'semester_id' => 'required|integer|exists:Semesters,semester_id'
            ], [
                'semester_id.required' => 'Mã học kỳ không được để trống',
                'semester_id.exists' => 'Học kỳ không tồn tại'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Lấy thông tin admin đang đăng nhập để lấy faculty_id
            $admin = \App\Models\Advisor::find($request->current_user_id);
            if (!$admin || !$admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin khoa của admin'
                ], 403);
            }

            // Gọi service xuất file với faculty_id từ admin
            $result = $this->exportService->exportTrainingPointsByFaculty(
                $admin->unit_id,
                $request->semester_id
            );

            // Xóa tất cả output buffer trước khi download file
            if (ob_get_length()) {
                ob_end_clean();
            }

            // Trả về file download
            return response()->download(
                $result['file_path'],
                $result['file_name'],
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất điểm rèn luyện',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xuất điểm CTXH theo lớp (Tích lũy từ đầu đến giờ)
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function exportSocialPointsByClass(Request $request)
    {
        try {
            // Validate request - không cần semester_id
            $validator = Validator::make($request->all(), [
                'class_id' => 'required|integer|exists:Classes,class_id'
            ], [
                'class_id.required' => 'Mã lớp không được để trống',
                'class_id.exists' => 'Lớp không tồn tại'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Gọi service xuất file - điểm CTXH tích lũy từ đầu đến giờ
            $result = $this->exportService->exportSocialPointsByClass(
                $request->class_id
            );

            // Xóa tất cả output buffer trước khi download file
            if (ob_get_length()) {
                ob_end_clean();
            }

            // Trả về file download
            return response()->download(
                $result['file_path'],
                $result['file_name'],
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất điểm công tác xã hội',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xuất điểm CTXH theo khoa (Tích lũy từ đầu đến giờ, lấy faculty_id từ admin đang đăng nhập)
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function exportSocialPointsByFaculty(Request $request)
    {
        try {
            // Không cần validate - lấy faculty_id từ admin đang đăng nhập

            // Lấy thông tin admin đang đăng nhập để lấy faculty_id
            $admin = \App\Models\Advisor::find($request->current_user_id);
            if (!$admin || !$admin->unit_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin khoa của admin'
                ], 403);
            }

            // Gọi service xuất file - điểm CTXH tích lũy từ đầu đến giờ với faculty_id từ admin
            $result = $this->exportService->exportSocialPointsByFaculty(
                $admin->unit_id
            );

            // Xóa tất cả output buffer trước khi download file
            if (ob_get_length()) {
                ob_end_clean();
            }

            // Trả về file download
            return response()->download(
                $result['file_path'],
                $result['file_name'],
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất điểm công tác xã hội',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
