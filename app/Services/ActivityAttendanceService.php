<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityRegistration;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;
use App\Services\ExcelHeaderService;

class ActivityAttendanceService
{
    protected ExcelHeaderService $excelHeaderService;

    public function __construct(ExcelHeaderService $excelHeaderService)
    {
        $this->excelHeaderService = $excelHeaderService;
    }

    /**
     * Export danh sách đăng ký hoạt động ra Excel
     * 
     * @param int $activityId
     * @param int $advisorId
     * @return array ['success' => bool, 'file_path' => string, 'file_name' => string, 'message' => string]
     */
    public function exportRegistrations(int $activityId, int $advisorId): array
    {
        try {
            $activity = Activity::with([
                'advisor:advisor_id,full_name',
                'organizerUnit:unit_id,unit_name'
            ])->find($activityId);

            if (!$activity) {
                return ['success' => false, 'message' => 'Hoạt động không tồn tại'];
            }

            if ($activity->advisor_id != $advisorId) {
                return ['success' => false, 'message' => 'Bạn không có quyền xuất danh sách hoạt động này'];
            }

            $registrations = ActivityRegistration::whereHas('role', function ($q) use ($activityId) {
                $q->where('activity_id', $activityId);
            })
                ->with([
                    'student:student_id,user_code,full_name,email,phone_number,class_id',
                    'student.class:class_id,class_name',
                    'role:activity_role_id,activity_id,role_name,points_awarded,point_type'
                ])
                ->orderBy('registration_time', 'asc')
                ->get();

            if ($registrations->isEmpty()) {
                return ['success' => false, 'message' => 'Chưa có sinh viên nào đăng ký hoạt động này'];
            }

            // Tạo spreadsheet với header đẹp từ đầu
            $spreadsheet = $this->excelHeaderService->createWithProfessionalHeader();
            $sheet = $spreadsheet->getActiveSheet();

            // Điền tiêu đề chính (dòng 5 - KHÔNG động vào 1-3)
            $this->excelHeaderService->fillTitle($sheet, 'DANH SÁCH ĐĂNG KÝ HOẠT ĐỘNG', 5, 'I');

            // Điền thông tin hoạt động (bắt đầu từ dòng 7)
            $currentRow = $this->excelHeaderService->fillInfoSection($sheet, [
                'Tên hoạt động:' => $activity->title,
                'Đơn vị tổ chức:' => $activity->organizerUnit->unit_name ?? 'N/A',
                'Thời gian:' => Carbon::parse($activity->start_time)->format('d/m/Y H:i') .
                    ' - ' .
                    Carbon::parse($activity->end_time)->format('d/m/Y H:i'),
                'Địa điểm:' => $activity->location ?? 'N/A',
                'Cố vấn phụ trách:' => $activity->advisor->full_name,
                'Ngày xuất:' => Carbon::now()->format('d/m/Y H:i'),
            ], 7, 'I');

            // Dòng trống
            $currentRow++;

            // Header bảng
            $this->excelHeaderService->createTableHeader($sheet, [
                'STT',
                'MSSV',
                'Họ và tên',
                'Lớp',
                'Vai trò',
                'Điểm',
                'Loại điểm',
                'Trạng thái',
                'Ghi chú'
            ], $currentRow);

            // Chuẩn bị dữ liệu
            $tableData = [];
            foreach ($registrations as $index => $reg) {
                $tableData[] = [
                    $index + 1,
                    $reg->student->user_code,
                    $reg->student->full_name,
                    $reg->student->class->class_name ?? 'N/A',
                    $reg->role->role_name,
                    $reg->role->points_awarded,
                    $this->formatPointType($reg->role->point_type),
                    $this->formatStatus($reg->status),
                    ''
                ];
            }

            // Điền dữ liệu
            $lastRow = $this->excelHeaderService->fillTableData($sheet, $tableData, $currentRow + 1);

            // Auto format columns
            $this->excelHeaderService->autoFormatColumns(
                $sheet,
                range('A', 'I'),
                [
                    'C' => 30, // Họ tên rộng hơn
                    'G' => 38, // Giữ nguyên width cho "CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM"
                    'H' => 18,
                    'I' => 15
                ]
            );

            // Lưu file
            $fileName = 'DanhSach_DangKy_' .
                preg_replace('/[^A-Za-z0-9_-]/', '_', $activity->title) . '_' .
                Carbon::now()->format('YmdHis') . '.xlsx';

            $filePath = storage_path('app/exports/' . $fileName);

            if (!file_exists(storage_path('app/exports'))) {
                mkdir(storage_path('app/exports'), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            Log::info('Export danh sách đăng ký thành công', [
                'activity_id' => $activityId,
                'file_name' => $fileName,
                'total_records' => $registrations->count()
            ]);

            return [
                'success' => true,
                'message' => 'Xuất danh sách thành công',
                'file_path' => $filePath,
                'file_name' => $fileName,
                'total_records' => $registrations->count()
            ];
        } catch (\Exception $e) {
            Log::error('Lỗi khi export danh sách đăng ký', [
                'activity_id' => $activityId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi khi xuất danh sách: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Export mẫu file điểm danh (template)
     */
    public function exportAttendanceTemplate(int $activityId, int $advisorId): array
    {
        try {
            $activity = Activity::with([
                'advisor:advisor_id,full_name',
                'organizerUnit:unit_id,unit_name'
            ])->find($activityId);

            if (!$activity) {
                return ['success' => false, 'message' => 'Hoạt động không tồn tại'];
            }

            if ($activity->advisor_id != $advisorId) {
                return ['success' => false, 'message' => 'Bạn không có quyền xuất file mẫu cho hoạt động này'];
            }

            $registrations = ActivityRegistration::whereHas('role', function ($q) use ($activityId) {
                $q->where('activity_id', $activityId);
            })
                ->whereIn('status', ['registered', 'attended', 'absent'])
                ->with([
                    'student:student_id,user_code,full_name,class_id',
                    'student.class:class_id,class_name',
                    'role:activity_role_id,role_name'
                ])
                ->orderBy('registration_time', 'asc')
                ->get();

            if ($registrations->isEmpty()) {
                return ['success' => false, 'message' => 'Không có sinh viên nào để điểm danh'];
            }

            // Tạo spreadsheet với header đẹp từ đầu
            $spreadsheet = $this->excelHeaderService->createWithProfessionalHeader();
            $sheet = $spreadsheet->getActiveSheet();

            // Điền tiêu đề chính (dòng 5 - KHÔNG động vào 1-3)
            $this->excelHeaderService->fillTitle($sheet, 'FILE ĐIỂM DANH HOẠT ĐỘNG', 5, 'I');

            // Điền thông tin hoạt động (bắt đầu từ dòng 7)
            $currentRow = $this->excelHeaderService->fillInfoSection($sheet, [
                'Tên hoạt động:' => $activity->title,
                'Đơn vị tổ chức:' => $activity->organizerUnit->unit_name ?? 'N/A',
                'Thời gian:' => Carbon::parse($activity->start_time)->format('d/m/Y H:i') .
                    ' - ' .
                    Carbon::parse($activity->end_time)->format('d/m/Y H:i'),
                'Địa điểm:' => $activity->location ?? 'N/A',
                'Cố vấn phụ trách:' => $activity->advisor->full_name,
                'Ngày xuất:' => Carbon::now()->format('d/m/Y H:i'),
            ], 7, 'I');

            // Dòng trống
            $currentRow++;

            // Hướng dẫn
            $sheet->setCellValue('A' . $currentRow, 'HƯỚNG DẪN:');
            $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->getColor()->setRGB('FF0000');
            $currentRow++;

            $instructions = [
                '1. KHÔNG thay đổi cột STT, Registration ID, MSSV, Họ tên, Vai trò',
                '2. Cột "Trạng thái điểm danh" chỉ điền: "Có mặt" hoặc "Vắng mặt"',
                '3. Sau khi điền xong, lưu file và import lại vào hệ thống'
            ];

            foreach ($instructions as $instruction) {
                $sheet->setCellValue('A' . $currentRow, $instruction);
                $sheet->getStyle('A' . $currentRow)->getFont()->setItalic(true);
                $currentRow++;
            }

            // Dòng trống
            $currentRow++;

            // Header bảng
            $this->excelHeaderService->createTableHeader($sheet, [
                'STT',
                'Registration ID',
                'MSSV',
                'Họ và tên',
                'Vai trò',
                'Trạng thái điểm danh'
            ], $currentRow);

            // Chuẩn bị dữ liệu
            $tableData = [];
            foreach ($registrations as $index => $reg) {
                $tableData[] = [
                    $index + 1,
                    $reg->registration_id,
                    $reg->student->user_code,
                    $reg->student->full_name,
                    $reg->role->role_name,
                    '' // Trống để advisor điền
                ];
            }

            // Điền dữ liệu
            $dataStartRow = $currentRow + 1;
            $lastRow = $this->excelHeaderService->fillTableData($sheet, $tableData, $dataStartRow);

            // Highlight cột điểm danh (cột F - để dễ nhận biết)
            for ($row = $dataStartRow; $row < $lastRow; $row++) {
                $sheet->getStyle('F' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFFF00'); // Màu vàng
            }

            // Auto format columns - specify TẤT CẢ widths để không bị autoSize ghi đè
            $this->excelHeaderService->autoFormatColumns(
                $sheet,
                range('A', 'I'),
                [
                    'A' => 8,  // STT
                    'B' => 18, // Registration ID / Thông tin trường
                    'C' => 30, // MSSV / Thông tin trường
                    'D' => 30, // Họ tên
                    'E' => 25, // Vai trò
                    'F' => 25, // Trạng thái điểm danh
                    'G' => 38, // "CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM"
                    'H' => 18,
                    'I' => 15
                ]
            );

            // Lưu file
            $fileName = 'DiemDanh_' .
                preg_replace('/[^A-Za-z0-9_-]/', '_', $activity->title) . '_' .
                Carbon::now()->format('YmdHis') . '.xlsx';

            $filePath = storage_path('app/exports/' . $fileName);

            if (!file_exists(storage_path('app/exports'))) {
                mkdir(storage_path('app/exports'), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            Log::info('Export file mẫu điểm danh thành công', [
                'activity_id' => $activityId,
                'file_name' => $fileName,
                'total_records' => $registrations->count()
            ]);

            return [
                'success' => true,
                'message' => 'Xuất file mẫu điểm danh thành công',
                'file_path' => $filePath,
                'file_name' => $fileName,
                'total_records' => $registrations->count()
            ];
        } catch (\Exception $e) {
            Log::error('Lỗi khi export file mẫu điểm danh', [
                'activity_id' => $activityId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi khi xuất file mẫu: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Import file điểm danh và cập nhật trạng thái
     */
    public function importAttendance(string $filePath, int $activityId, int $advisorId): array
    {
        try {
            if (!file_exists($filePath)) {
                return ['success' => false, 'message' => 'File không tồn tại'];
            }

            $activity = Activity::find($activityId);

            if (!$activity) {
                return ['success' => false, 'message' => 'Hoạt động không tồn tại'];
            }

            if ($activity->advisor_id != $advisorId) {
                return ['success' => false, 'message' => 'Bạn không có quyền cập nhật điểm danh cho hoạt động này'];
            }

            if (in_array($activity->status, ['cancelled', 'upcoming'])) {
                return ['success' => false, 'message' => 'Không thể điểm danh cho hoạt động chưa diễn ra hoặc đã bị hủy'];
            }

            // Đọc file Excel
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // Tìm dòng bắt đầu dữ liệu (sau header "STT", "Registration ID", ...)
            $dataStartRow = null;
            for ($row = 1; $row <= 20; $row++) {
                $cellValue = trim($sheet->getCell('A' . $row)->getValue());
                if (strtoupper($cellValue) === 'STT') {
                    $dataStartRow = $row + 1;
                    break;
                }
            }

            if (!$dataStartRow) {
                return ['success' => false, 'message' => 'Không tìm thấy header bảng dữ liệu trong file'];
            }

            $updated = [];
            $skipped = [];
            $errors = [];

            DB::beginTransaction();
            try {
                for ($row = $dataStartRow; $row <= $highestRow; $row++) {
                    $registrationId = $sheet->getCell('B' . $row)->getCalculatedValue();
                    $mssv = $sheet->getCell('C' . $row)->getCalculatedValue();
                    $studentName = $sheet->getCell('D' . $row)->getCalculatedValue();
                    $status = trim(strtolower($sheet->getCell('F' . $row)->getCalculatedValue()));

                    // Bỏ qua dòng trống
                    if (empty($registrationId)) {
                        continue;
                    }

                    // Chuyển đổi tiếng Việt sang tiếng Anh
                    $statusMapping = [
                        'có mặt' => 'attended',
                        'co mat' => 'attended',
                        'vắng mặt' => 'absent',
                        'vang mat' => 'absent',
                        'attended' => 'attended', // Vẫn chấp nhận tiếng Anh
                        'absent' => 'absent'
                    ];

                    $normalizedStatus = strtolower(trim($status));
                    if (!isset($statusMapping[$normalizedStatus])) {
                        $errors[] = [
                            'row' => $row,
                            'registration_id' => $registrationId,
                            'mssv' => $mssv,
                            'student_name' => $studentName,
                            'reason' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: "Có mặt" hoặc "Vắng mặt". Giá trị: "' . $status . '"'
                        ];
                        continue;
                    }

                    // Convert sang English để lưu vào database
                    $status = $statusMapping[$normalizedStatus];

                    // Tìm registration
                    $registration = ActivityRegistration::with(['role', 'student'])->find($registrationId);

                    if (!$registration) {
                        $skipped[] = [
                            'row' => $row,
                            'registration_id' => $registrationId,
                            'mssv' => $mssv,
                            'student_name' => $studentName,
                            'reason' => 'Không tìm thấy đăng ký'
                        ];
                        continue;
                    }

                    if ($registration->role->activity_id != $activityId) {
                        $skipped[] = [
                            'row' => $row,
                            'registration_id' => $registrationId,
                            'mssv' => $mssv,
                            'student_name' => $studentName,
                            'reason' => 'Đăng ký không thuộc hoạt động này'
                        ];
                        continue;
                    }

                    if (!in_array($registration->status, ['registered', 'attended', 'absent'])) {
                        $skipped[] = [
                            'row' => $row,
                            'registration_id' => $registrationId,
                            'mssv' => $registration->student->user_code,
                            'student_name' => $registration->student->full_name,
                            'reason' => 'Trạng thái hiện tại không cho phép cập nhật: ' . $registration->status
                        ];
                        continue;
                    }

                    // Cập nhật
                    $oldStatus = $registration->status;
                    $registration->update(['status' => $status]);

                    $updated[] = [
                        'row' => $row,
                        'registration_id' => $registration->registration_id,
                        'mssv' => $registration->student->user_code,
                        'student_name' => $registration->student->full_name,
                        'old_status' => $oldStatus,
                        'new_status' => $status
                    ];
                }

                DB::commit();

                Log::info('Import điểm danh thành công', [
                    'activity_id' => $activityId,
                    'total_updated' => count($updated),
                    'total_skipped' => count($skipped),
                    'total_errors' => count($errors)
                ]);

                return [
                    'success' => true,
                    'message' => 'Import điểm danh thành công',
                    'data' => [
                        'total_updated' => count($updated),
                        'total_skipped' => count($skipped),
                        'total_errors' => count($errors),
                        'updated' => $updated,
                        'skipped' => $skipped,
                        'errors' => $errors
                    ]
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Lỗi khi import điểm danh', [
                'activity_id' => $activityId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi khi import điểm danh: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format loại điểm
     */
    private function formatPointType(string $type): string
    {
        return match ($type) {
            'ctxh' => 'Công tác xã hội',
            'ren_luyen' => 'Rèn luyện',
            default => $type
        };
    }

    /**
     * Format trạng thái
     */
    private function formatStatus(string $status): string
    {
        return match ($status) {
            'registered' => 'Đã đăng ký',
            'attended' => 'Đã tham gia',
            'absent' => 'Vắng mặt',
            'cancelled' => 'Đã hủy',
            default => $status
        };
    }
}
