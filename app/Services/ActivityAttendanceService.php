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

class ActivityAttendanceService
{
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
                return [
                    'success' => false,
                    'message' => 'Hoạt động không tồn tại'
                ];
            }

            // Kiểm tra quyền
            if ($activity->advisor_id != $advisorId) {
                return [
                    'success' => false,
                    'message' => 'Bạn không có quyền xuất danh sách hoạt động này'
                ];
            }

            // Lấy danh sách đăng ký
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
                return [
                    'success' => false,
                    'message' => 'Chưa có sinh viên nào đăng ký hoạt động này'
                ];
            }

            // Tạo Spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // ===== HEADER THÔNG TIN HOẠT ĐỘNG =====
            $sheet->setCellValue('A1', 'DANH SÁCH ĐĂNG KÝ HOẠT ĐỘNG');
            $sheet->mergeCells('A1:I1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('A2', 'Tên hoạt động:');
            $sheet->setCellValue('B2', $activity->title);
            $sheet->mergeCells('B2:I2');

            $sheet->setCellValue('A3', 'Đơn vị tổ chức:');
            $sheet->setCellValue('B3', $activity->organizerUnit->unit_name ?? 'N/A');

            $sheet->setCellValue('A4', 'Thời gian:');
            $sheet->setCellValue(
                'B4',
                Carbon::parse($activity->start_time)->format('d/m/Y H:i') .
                ' - ' .
                Carbon::parse($activity->end_time)->format('d/m/Y H:i')
            );
            $sheet->mergeCells('B4:I4');

            $sheet->setCellValue('A5', 'Địa điểm:');
            $sheet->setCellValue('B5', $activity->location ?? 'N/A');
            $sheet->mergeCells('B5:I5');

            $sheet->setCellValue('A6', 'Cố vấn phụ trách:');
            $sheet->setCellValue('B6', $activity->advisor->full_name);

            $sheet->setCellValue('A7', 'Ngày xuất:');
            $sheet->setCellValue('B7', Carbon::now()->format('d/m/Y H:i'));

            // Style cho phần header
            $sheet->getStyle('A2:A7')->getFont()->setBold(true);
            $sheet->getStyle('A2:B7')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            // ===== HEADER BẢNG =====
            $headerRow = 9;
            $headers = [
                'A' => 'STT',
                'B' => 'MSSV',
                'C' => 'Họ và tên',
                'D' => 'Lớp',
                'E' => 'Vai trò',
                'F' => 'Điểm',
                'G' => 'Loại điểm',
                'H' => 'Trạng thái',
                'I' => 'Ghi chú'
            ];

            foreach ($headers as $col => $header) {
                $sheet->setCellValue($col . $headerRow, $header);
            }

            // Style cho header bảng
            $headerStyle = $sheet->getStyle('A' . $headerRow . ':I' . $headerRow);
            $headerStyle->getFont()->setBold(true)->setSize(12);
            $headerStyle->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF4472C4');
            $headerStyle->getFont()->getColor()->setARGB('FFFFFFFF');
            $headerStyle->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $headerStyle->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            // ===== DỮ LIỆU =====
            $row = $headerRow + 1;
            $stt = 1;

            foreach ($registrations as $registration) {
                $sheet->setCellValue('A' . $row, $stt);
                $sheet->setCellValue('B' . $row, $registration->student->user_code);
                $sheet->setCellValue('C' . $row, $registration->student->full_name);
                $sheet->setCellValue('D' . $row, $registration->student->class->class_name ?? 'N/A');
                $sheet->setCellValue('E' . $row, $registration->role->role_name);
                $sheet->setCellValue('F' . $row, $registration->role->points_awarded);
                $sheet->setCellValue('G' . $row, $this->formatPointType($registration->role->point_type));
                $sheet->setCellValue('H' . $row, $this->formatStatus($registration->status));
                $sheet->setCellValue('I' . $row, ''); // Ghi chú để trống

                // Style cho dữ liệu
                $sheet->getStyle('A' . $row . ':I' . $row)
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle('A' . $row)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Border
                $sheet->getStyle('A' . $row . ':I' . $row)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $row++;
                $stt++;
            }

            // ===== TỔNG KẾT =====
            $summaryRow = $row + 1;
            $sheet->setCellValue('A' . $summaryRow, 'TỔNG SỐ SINH VIÊN:');
            $sheet->setCellValue('B' . $summaryRow, $registrations->count());
            $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true);

            // Thống kê theo trạng thái
            $summaryRow++;
            $statusCounts = $registrations->groupBy('status')->map(function ($items) {
                return $items->count();
            });

            $sheet->setCellValue('A' . $summaryRow, 'Đã đăng ký:');
            $sheet->setCellValue('B' . $summaryRow, $statusCounts->get('registered', 0));

            $summaryRow++;
            $sheet->setCellValue('A' . $summaryRow, 'Đã tham gia:');
            $sheet->setCellValue('B' . $summaryRow, $statusCounts->get('attended', 0));

            $summaryRow++;
            $sheet->setCellValue('A' . $summaryRow, 'Vắng mặt:');
            $sheet->setCellValue('B' . $summaryRow, $statusCounts->get('absent', 0));

            $summaryRow++;
            $sheet->setCellValue('A' . $summaryRow, 'Đã hủy:');
            $sheet->setCellValue('B' . $summaryRow, $statusCounts->get('cancelled', 0));

            // ===== AUTO SIZE COLUMNS =====
            foreach (range('A', 'I') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Đảm bảo cột C (Họ tên) đủ rộng
            $sheet->getColumnDimension('C')->setWidth(30);

            // ===== LƯU FILE =====
            $fileName = 'DanhSach_DangKy_' .
                preg_replace('/[^A-Za-z0-9_-]/', '_', $activity->title) . '_' .
                Carbon::now()->format('YmdHis') . '.xlsx';

            $filePath = storage_path('app/exports/' . $fileName);

            // Tạo thư mục nếu chưa tồn tại
            if (!file_exists(storage_path('app/exports'))) {
                mkdir(storage_path('app/exports'), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            Log::info('Export danh sách đăng ký thành công', [
                'activity_id' => $activityId,
                'advisor_id' => $advisorId,
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
     * 
     * @param int $activityId
     * @param int $advisorId
     * @return array
     */
    public function exportAttendanceTemplate(int $activityId, int $advisorId): array
    {
        try {
            $activity = Activity::with([
                'advisor:advisor_id,full_name',
                'organizerUnit:unit_id,unit_name'
            ])->find($activityId);

            if (!$activity) {
                return [
                    'success' => false,
                    'message' => 'Hoạt động không tồn tại'
                ];
            }

            if ($activity->advisor_id != $advisorId) {
                return [
                    'success' => false,
                    'message' => 'Bạn không có quyền xuất file mẫu cho hoạt động này'
                ];
            }

            // Lấy danh sách đăng ký với status registered hoặc attended
            $registrations = ActivityRegistration::whereHas('role', function ($q) use ($activityId) {
                $q->where('activity_id', $activityId);
            })
                ->whereIn('status', ['registered', 'attended'])
                ->with([
                    'student:student_id,user_code,full_name,class_id',
                    'student.class:class_id,class_name',
                    'role:activity_role_id,role_name'
                ])
                ->orderBy('registration_time', 'asc')
                ->get();

            if ($registrations->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Không có sinh viên nào để điểm danh'
                ];
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // ===== HEADER =====
            $sheet->setCellValue('A1', 'FILE ĐIỂM DANH HOẠT ĐỘNG');
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('A2', 'Tên hoạt động:');
            $sheet->setCellValue('B2', $activity->title);
            $sheet->mergeCells('B2:F2');

            $sheet->setCellValue('A3', 'Thời gian:');
            $sheet->setCellValue(
                'B3',
                Carbon::parse($activity->start_time)->format('d/m/Y H:i') .
                ' - ' .
                Carbon::parse($activity->end_time)->format('d/m/Y H:i')
            );
            $sheet->mergeCells('B3:F3');

            // ===== HƯỚNG DẪN =====
            $sheet->setCellValue('A5', 'HƯỚNG DẪN:');
            $sheet->getStyle('A5')->getFont()->setBold(true)->getColor()->setARGB('FFFF0000');

            $sheet->setCellValue('A6', '1. KHÔNG thay đổi cột STT, Registration ID, MSSV, Họ tên, Vai trò');
            $sheet->setCellValue('A7', '2. Cột "Trạng thái điểm danh" chỉ điền: attended (có mặt) hoặc absent (vắng mặt)');
            $sheet->setCellValue('A8', '3. Sau khi điền xong, lưu file và import lại vào hệ thống');
            $sheet->getStyle('A6:A8')->getFont()->setItalic(true);

            // ===== HEADER BẢNG =====
            $headerRow = 10;
            $headers = [
                'A' => 'STT',
                'B' => 'Registration ID',
                'C' => 'MSSV',
                'D' => 'Họ và tên',
                'E' => 'Vai trò',
                'F' => 'Trạng thái điểm danh'
            ];

            foreach ($headers as $col => $header) {
                $sheet->setCellValue($col . $headerRow, $header);
            }

            // Style header
            $headerStyle = $sheet->getStyle('A' . $headerRow . ':F' . $headerRow);
            $headerStyle->getFont()->setBold(true)->setSize(12);
            $headerStyle->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF4472C4');
            $headerStyle->getFont()->getColor()->setARGB('FFFFFFFF');
            $headerStyle->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $headerStyle->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            // ===== DỮ LIỆU =====
            $row = $headerRow + 1;
            $stt = 1;

            foreach ($registrations as $registration) {
                $sheet->setCellValue('A' . $row, $stt);
                $sheet->setCellValue('B' . $row, $registration->registration_id);
                $sheet->setCellValue('C' . $row, $registration->student->user_code);
                $sheet->setCellValue('D' . $row, $registration->student->full_name);
                $sheet->setCellValue('E' . $row, $registration->role->role_name);
                $sheet->setCellValue('F' . $row, ''); // Để trống cho advisor điền

                // Màu nền cho cột F (để dễ nhận biết)
                $sheet->getStyle('F' . $row)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFFFFF00'); // Màu vàng nhạt

                // Border
                $sheet->getStyle('A' . $row . ':F' . $row)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Center align
                $sheet->getStyle('A' . $row . ':F' . $row)
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $row++;
                $stt++;
            }

            // ===== AUTO SIZE =====
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->getColumnDimension('D')->setWidth(30);

            // ===== LƯU FILE =====
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
                'advisor_id' => $advisorId,
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
     * 
     * @param string $filePath
     * @param int $activityId
     * @param int $advisorId
     * @return array
     */
    public function importAttendance(string $filePath, int $activityId, int $advisorId): array
    {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'message' => 'File không tồn tại'
                ];
            }

            $activity = Activity::find($activityId);

            if (!$activity) {
                return [
                    'success' => false,
                    'message' => 'Hoạt động không tồn tại'
                ];
            }

            if ($activity->advisor_id != $advisorId) {
                return [
                    'success' => false,
                    'message' => 'Bạn không có quyền cập nhật điểm danh cho hoạt động này'
                ];
            }

            if (in_array($activity->status, ['cancelled', 'upcoming'])) {
                return [
                    'success' => false,
                    'message' => 'Không thể điểm danh cho hoạt động chưa diễn ra hoặc đã bị hủy'
                ];
            }

            // Đọc file Excel
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // Bắt đầu từ row 11 (row 10 là header, 1-9 là thông tin và hướng dẫn)
            $dataStartRow = 11;

            $updated = [];
            $skipped = [];
            $errors = [];

            DB::beginTransaction();
            try {
                for ($row = $dataStartRow; $row <= $highestRow; $row++) {
                    // Sử dụng getCalculatedValue() để lấy giá trị thực tế (kể cả khi cell chứa formula)
                    $registrationId = $sheet->getCell('B' . $row)->getCalculatedValue();
                    $mssv = $sheet->getCell('C' . $row)->getCalculatedValue();
                    $studentName = $sheet->getCell('D' . $row)->getCalculatedValue();
                    $status = trim(strtolower($sheet->getCell('F' . $row)->getCalculatedValue()));

                    // Bỏ qua dòng trống
                    if (empty($registrationId)) {
                        continue;
                    }

                    // Validate status
                    if (!in_array($status, ['attended', 'absent'])) {
                        $errors[] = [
                            'row' => $row,
                            'registration_id' => $registrationId,
                            'mssv' => $mssv,
                            'student_name' => $studentName,
                            'reason' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: attended hoặc absent. Giá trị hiện tại: "' . $status . '"'
                        ];
                        continue;
                    }

                    // Tìm registration
                    $registration = ActivityRegistration::with(['role', 'student'])
                        ->find($registrationId);

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

                    // Kiểm tra registration có thuộc activity này không
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

                    // Kiểm tra trạng thái hiện tại
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
                    'advisor_id' => $advisorId,
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