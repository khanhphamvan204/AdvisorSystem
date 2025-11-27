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
     * @return \Illuminate\Http\JsonResponse
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
     * Xuất điểm rèn luyện theo khoa
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportTrainingPointsByFaculty(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'faculty_id' => 'required|integer|exists:Units,unit_id',
                'semester_id' => 'required|integer|exists:Semesters,semester_id'
            ], [
                'faculty_id.required' => 'Mã khoa không được để trống',
                'faculty_id.exists' => 'Khoa không tồn tại',
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
            $result = $this->exportService->exportTrainingPointsByFaculty(
                $request->faculty_id,
                $request->semester_id
            );

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
     * Xuất điểm CTXH theo lớp
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportSocialPointsByClass(Request $request)
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
            $result = $this->exportService->exportSocialPointsByClass(
                $request->class_id,
                $request->semester_id
            );

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
     * Xuất điểm CTXH theo khoa
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportSocialPointsByFaculty(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'faculty_id' => 'required|integer|exists:Units,unit_id',
                'semester_id' => 'required|integer|exists:Semesters,semester_id'
            ], [
                'faculty_id.required' => 'Mã khoa không được để trống',
                'faculty_id.exists' => 'Khoa không tồn tại',
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
            $result = $this->exportService->exportSocialPointsByFaculty(
                $request->faculty_id,
                $request->semester_id
            );

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
