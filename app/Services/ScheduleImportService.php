<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Log;

class ScheduleImportService
{
    /**
     * Tạo file Excel template để import lịch học
     * 
     * @return Spreadsheet
     */
    public function generateTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet();

            // ==================== SHEET 1: DANH SÁCH MÔN HỌC ====================
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('DanhSachMonHoc');

            // Header
            $headers1 = [
                'STT',
                'Mã môn',
                'Tên môn',
                'Giảng viên',
                'Giai đoạn',
                'Ngày BĐ',
                'Ngày KT',
                'Thứ',
                'Tiết BĐ',
                'Tiết KT',
                'Phòng',
                'Ghi chú'
            ];

            $column = 'A';
            foreach ($headers1 as $header) {
                $sheet1->setCellValue($column . '1', $header);
                $column++;
            }

            // Dữ liệu mẫu
            $sampleData1 = [
                [1, 'IT001', 'Nhập môn Lập trình', 'GV. Nguyễn Văn A', 'Toàn khóa', '01/09/2024', '15/12/2024', 'T2', 1, 3, 'A1.01', 'Lý thuyết'],
                [2, 'IT001', 'Nhập môn Lập trình', 'GV. Nguyễn Văn A', 'Toàn khóa', '01/09/2024', '15/12/2024', 'T4', 7, 9, 'PM.01', 'Thực hành'],
                [3, 'IT002', 'Cấu trúc dữ liệu', 'GV. Trần Thị B', 'Toàn khóa', '01/09/2024', '15/12/2024', 'T3', 4, 6, 'A1.02', 'Lý thuyết'],
            ];

            $row = 2;
            foreach ($sampleData1 as $data) {
                $column = 'A';
                foreach ($data as $value) {
                    $sheet1->setCellValue($column . $row, $value);
                    $column++;
                }
                $row++;
            }

            // Format header
            $sheet1->getStyle('A1:L1')->getFont()->setBold(true);
            $sheet1->getStyle('A1:L1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('CCE5FF');

            // Set column widths
            $columnWidths1 = [6, 10, 25, 20, 12, 12, 12, 8, 9, 9, 10, 20];
            $column = 'A';
            foreach ($columnWidths1 as $width) {
                $sheet1->getColumnDimension($column)->setWidth($width);
                $column++;
            }

            // Thêm ghi chú hướng dẫn
            $lastRow = 6;
            $sheet1->setCellValue('A' . $lastRow, 'HƯỚNG DẪN:');
            $sheet1->setCellValue('A' . ($lastRow + 1), '1. Điền thông tin lịch học của các môn học');
            $sheet1->setCellValue('A' . ($lastRow + 2), '2. Mỗi dòng là 1 buổi học (1 môn có thể có nhiều buổi)');
            $sheet1->setCellValue('A' . ($lastRow + 3), '3. Thứ: T2, T3, T4, T5, T6, T7, CN');
            $sheet1->setCellValue('A' . ($lastRow + 4), '4. Tiết: từ 1-17');
            $sheet1->setCellValue('A' . ($lastRow + 5), '5. Ghi chú "Thực hành" hoặc "TH" để phân biệt lịch thực hành');
            $sheet1->setCellValue('A' . ($lastRow + 6), '6. Ngày theo định dạng: dd/mm/yyyy (VD: 01/09/2024)');
            $sheet1->getStyle('A' . $lastRow)->getFont()->setBold(true);

            // ==================== SHEET 2: ĐĂNG KÝ MÔN HỌC ====================
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('DangKyMonHoc');

            // Header
            $headers2 = [
                'STT',
                'Mã SV',
                'Họ tên',
                'Lớp',
                'Mã môn',
                'Học kỳ',
                'Năm học'
            ];

            $column = 'A';
            foreach ($headers2 as $header) {
                $sheet2->setCellValue($column . '1', $header);
                $column++;
            }

            // Dữ liệu mẫu
            $sampleData2 = [
                [1, '210001', 'Nguyễn Văn A', 'DH21CNTT', 'IT001', 'Học kỳ 1', '2024-2025'],
                [2, '210001', 'Nguyễn Văn A', 'DH21CNTT', 'IT002', 'Học kỳ 1', '2024-2025'],
                [3, '210002', 'Trần Thị B', 'DH21CNTT', 'IT001', 'Học kỳ 1', '2024-2025'],
                [4, '210002', 'Trần Thị B', 'DH21CNTT', 'IT002', 'Học kỳ 1', '2024-2025'],
            ];

            $row = 2;
            foreach ($sampleData2 as $data) {
                $column = 'A';
                foreach ($data as $value) {
                    $sheet2->setCellValue($column . $row, $value);
                    $column++;
                }
                $row++;
            }

            // Format header
            $sheet2->getStyle('A1:G1')->getFont()->setBold(true);
            $sheet2->getStyle('A1:G1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('CCE5FF');

            // Set column widths
            $columnWidths2 = [6, 12, 25, 15, 10, 12, 12];
            $column = 'A';
            foreach ($columnWidths2 as $width) {
                $sheet2->getColumnDimension($column)->setWidth($width);
                $column++;
            }

            // Thêm ghi chú
            $lastRow = 7;
            $sheet2->setCellValue('A' . $lastRow, 'LƯU Ý:');
            $sheet2->setCellValue('A' . ($lastRow + 1), '- Mỗi dòng là 1 môn học mà sinh viên đăng ký');
            $sheet2->setCellValue('A' . ($lastRow + 2), '- Mã môn phải khớp với Sheet "DanhSachMonHoc"');
            $sheet2->setCellValue('A' . ($lastRow + 3), '- Một sinh viên có thể đăng ký nhiều môn (nhiều dòng)');
            $sheet2->setCellValue('A' . ($lastRow + 4), '- Học kỳ: "Học kỳ 1", "Học kỳ 2", "Học kỳ hè"');
            $sheet2->setCellValue('A' . ($lastRow + 5), '- Năm học theo định dạng: yyyy-yyyy (VD: 2024-2025)');
            $sheet2->getStyle('A' . $lastRow)->getFont()->setBold(true);

            // Set active sheet về sheet đầu tiên
            $spreadsheet->setActiveSheetIndex(0);

            return $spreadsheet;

        } catch (\Exception $e) {
            Log::error('Failed to generate schedule template', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
