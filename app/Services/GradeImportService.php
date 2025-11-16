<?php

namespace App\Services;

use App\Models\CourseGrade;
use App\Models\Student;
use App\Models\Course;
use App\Models\Semester;
use App\Models\Advisor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class GradeImportService
{
    /**
     * Import điểm từ file Excel
     * 
     * @param string $filePath Đường dẫn file Excel
     * @param int $adminId ID của admin
     * @return array Kết quả import
     */
    public function importFromExcel($filePath, $adminId)
    {
        $results = [
            'success' => [],
            'errors' => [],
            'updated' => [],
            'summary' => [
                'total_rows' => 0,
                'success_count' => 0,
                'updated_count' => 0,
                'error_count' => 0
            ]
        ];

        try {
            // Kiểm tra file tồn tại
            if (!file_exists($filePath)) {
                throw new \Exception("File không tồn tại: {$filePath}");
            }

            // Đọc file Excel
            $spreadsheet = IOFactory::load($filePath);

            // Validate cấu trúc file
            $validation = $this->validateExcelStructure($spreadsheet);
            if (!$validation['valid']) {
                throw new \Exception($validation['message']);
            }

            // Lấy thông tin chung
            $generalInfo = $this->getGeneralInfo($spreadsheet);

            // Validate thông tin chung
            $generalValidation = $this->validateGeneralInfo($generalInfo, $adminId);
            if (!$generalValidation['valid']) {
                throw new \Exception($generalValidation['message']);
            }

            // Lấy danh sách điểm
            $gradesData = $this->getGradesData($spreadsheet);
            $results['summary']['total_rows'] = count($gradesData);

            if (empty($gradesData)) {
                throw new \Exception("Không có dữ liệu điểm trong file");
            }

            // Bắt đầu transaction
            DB::beginTransaction();

            // Xử lý từng dòng điểm
            foreach ($gradesData as $index => $gradeRow) {
                $rowNumber = $index + 2; // +2 vì bắt đầu từ dòng 2 (dòng 1 là header)

                $processResult = $this->processSingleGrade(
                    $gradeRow,
                    $generalInfo,
                    $adminId,
                    $rowNumber
                );

                if ($processResult['success']) {
                    if ($processResult['action'] === 'created') {
                        $results['success'][] = $processResult['data'];
                        $results['summary']['success_count']++;
                    } else if ($processResult['action'] === 'updated') {
                        $results['updated'][] = $processResult['data'];
                        $results['summary']['updated_count']++;
                    }
                } else {
                    $results['errors'][] = [
                        'row' => $rowNumber,
                        'user_code' => $gradeRow['user_code'] ?? 'N/A',
                        'error' => $processResult['error']
                    ];
                    $results['summary']['error_count']++;
                }
            }

            DB::commit();

            Log::info('Excel grades import completed', [
                'admin_id' => $adminId,
                'semester_id' => $generalInfo['semester_id'],
                'course_code' => $generalInfo['course_code'],
                'summary' => $results['summary']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Excel import failed', [
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Validate cấu trúc file Excel
     */
    private function validateExcelStructure(Spreadsheet $spreadsheet)
    {
        $sheetNames = $spreadsheet->getSheetNames();

        // Kiểm tra có đủ 2 sheet
        if (count($sheetNames) < 2) {
            return [
                'valid' => false,
                'message' => 'File Excel phải có ít nhất 2 sheet: "ThongTinChung" và "DanhSachDiem"'
            ];
        }

        // Kiểm tra tên sheet
        $requiredSheets = ['ThongTinChung', 'DanhSachDiem'];
        foreach ($requiredSheets as $sheetName) {
            if (!in_array($sheetName, $sheetNames)) {
                return [
                    'valid' => false,
                    'message' => "Thiếu sheet \"{$sheetName}\". Vui lòng sử dụng đúng template."
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Lấy thông tin chung từ sheet ThongTinChung
     */
    private function getGeneralInfo(Spreadsheet $spreadsheet)
    {
        $sheet = $spreadsheet->getSheetByName('ThongTinChung');

        return [
            'semester_id' => trim($sheet->getCell('C2')->getValue()),
            'course_code' => trim($sheet->getCell('C3')->getValue())
        ];
    }

    /**
     * Validate thông tin chung
     */
    private function validateGeneralInfo($generalInfo, $adminId)
    {
        // Kiểm tra semester_id
        if (empty($generalInfo['semester_id'])) {
            return [
                'valid' => false,
                'message' => 'Thiếu thông tin học kỳ (semester_id) trong sheet ThongTinChung'
            ];
        }

        $semester = Semester::find($generalInfo['semester_id']);
        if (!$semester) {
            return [
                'valid' => false,
                'message' => "Không tìm thấy học kỳ với ID: {$generalInfo['semester_id']}"
            ];
        }

        // Kiểm tra course_code
        if (empty($generalInfo['course_code'])) {
            return [
                'valid' => false,
                'message' => 'Thiếu mã môn học trong sheet ThongTinChung'
            ];
        }

        $course = Course::where('course_code', $generalInfo['course_code'])->first();
        if (!$course) {
            return [
                'valid' => false,
                'message' => "Không tìm thấy môn học với mã: {$generalInfo['course_code']}"
            ];
        }

        // Kiểm tra admin có quyền với khoa của môn học này không
        $admin = Advisor::with('unit')->find($adminId);
        if (!$admin || !$admin->unit_id) {
            return [
                'valid' => false,
                'message' => 'Admin chưa được gán vào khoa nào'
            ];
        }

        if ($course->unit_id != $admin->unit_id) {
            return [
                'valid' => false,
                'message' => "Môn học {$generalInfo['course_code']} không thuộc khoa bạn quản lý"
            ];
        }

        return [
            'valid' => true,
            'semester' => $semester,
            'course' => $course,
            'admin' => $admin
        ];
    }

    /**
     * Lấy danh sách điểm từ sheet DanhSachDiem
     */
    private function getGradesData(Spreadsheet $spreadsheet)
    {
        $sheet = $spreadsheet->getSheetByName('DanhSachDiem');
        $highestRow = $sheet->getHighestRow();
        $gradesData = [];

        // Bắt đầu từ dòng 2 (dòng 1 là header)
        for ($row = 2; $row <= $highestRow; $row++) {
            $userCode = trim($sheet->getCell("B{$row}")->getValue());
            // Sử dụng getCalculatedValue() để lấy giá trị đã tính toán từ công thức Excel
            $gradeValue = $sheet->getCell("E{$row}")->getCalculatedValue();

            // Bỏ qua dòng trống
            if (empty($userCode) && empty($gradeValue)) {
                continue;
            }

            $gradesData[] = [
                'stt' => trim($sheet->getCell("A{$row}")->getValue()),
                'user_code' => $userCode,
                'full_name' => trim($sheet->getCell("C{$row}")->getValue()),
                'class_name' => trim($sheet->getCell("D{$row}")->getValue()),
                'grade_value' => $gradeValue,
                'note' => trim($sheet->getCell("I{$row}")->getValue())
            ];
        }

        return $gradesData;
    }

    /**
     * Xử lý một dòng điểm
     */
    private function processSingleGrade($gradeRow, $generalInfo, $adminId, $rowNumber)
    {
        try {
            // Validate dữ liệu dòng
            if (empty($gradeRow['user_code'])) {
                return [
                    'success' => false,
                    'error' => 'Thiếu mã sinh viên'
                ];
            }

            if (!is_numeric($gradeRow['grade_value'])) {
                return [
                    'success' => false,
                    'error' => 'Điểm không hợp lệ (phải là số)'
                ];
            }

            $gradeValue = floatval($gradeRow['grade_value']);
            if ($gradeValue < 0 || $gradeValue > 10) {
                return [
                    'success' => false,
                    'error' => 'Điểm phải nằm trong khoảng 0-10'
                ];
            }

            // Tìm sinh viên
            $student = Student::with('class.faculty')
                ->where('user_code', $gradeRow['user_code'])
                ->first();

            if (!$student) {
                return [
                    'success' => false,
                    'error' => "Không tìm thấy sinh viên với mã: {$gradeRow['user_code']}"
                ];
            }

            // Kiểm tra sinh viên thuộc khoa admin quản lý
            $admin = Advisor::find($adminId);
            if (!$student->class || $student->class->faculty_id != $admin->unit_id) {
                return [
                    'success' => false,
                    'error' => 'Sinh viên không thuộc khoa bạn quản lý'
                ];
            }

            // Lấy thông tin course
            $course = Course::where('course_code', $generalInfo['course_code'])->first();

            // Kiểm tra điểm đã tồn tại chưa
            $existingGrade = CourseGrade::where('student_id', $student->student_id)
                ->where('course_id', $course->course_id)
                ->where('semester_id', $generalInfo['semester_id'])
                ->first();

            // Quy đổi điểm
            $converted = AcademicMonitoringService::convertGrade($gradeValue);

            if ($existingGrade) {
                // Cập nhật
                $existingGrade->grade_value = $gradeValue;
                $existingGrade->grade_letter = $converted['letter'];
                $existingGrade->grade_4_scale = $converted['scale4'];
                $existingGrade->status = $gradeValue >= 4.0 ? 'passed' : 'failed';
                $existingGrade->save();

                return [
                    'success' => true,
                    'action' => 'updated',
                    'data' => [
                        'row' => $rowNumber,
                        'user_code' => $student->user_code,
                        'full_name' => $student->full_name,
                        'class_name' => $student->class->class_name,
                        'grade_value' => $gradeValue,
                        'status' => $existingGrade->status
                    ]
                ];
            } else {
                // Tạo mới
                $grade = CourseGrade::create([
                    'student_id' => $student->student_id,
                    'course_id' => $course->course_id,
                    'semester_id' => $generalInfo['semester_id'],
                    'grade_value' => $gradeValue,
                    'grade_letter' => $converted['letter'],
                    'grade_4_scale' => $converted['scale4'],
                    'status' => $gradeValue >= 4.0 ? 'passed' : 'failed'
                ]);

                return [
                    'success' => true,
                    'action' => 'created',
                    'data' => [
                        'row' => $rowNumber,
                        'user_code' => $student->user_code,
                        'full_name' => $student->full_name,
                        'class_name' => $student->class->class_name,
                        'grade_value' => $gradeValue,
                        'status' => $grade->status
                    ]
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Tạo file Excel mẫu để download
     */
    public function generateTemplate($adminId)
    {
        try {
            $admin = Advisor::with('unit')->find($adminId);

            $spreadsheet = new Spreadsheet();

            // Sheet 1: Thông tin chung
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('ThongTinChung');

            // Header
            $sheet1->setCellValue('A1', 'STT');
            $sheet1->setCellValue('B1', 'Trường');
            $sheet1->setCellValue('C1', 'Giá trị');
            $sheet1->setCellValue('D1', 'Ghi chú');

            // Dữ liệu mẫu
            $sheet1->setCellValue('A2', '1');
            $sheet1->setCellValue('B2', 'Học kỳ (semester_id)');
            $sheet1->setCellValue('C2', '');
            $sheet1->setCellValue('D2', 'VD: 1, 2, 3...');

            $sheet1->setCellValue('A3', '2');
            $sheet1->setCellValue('B3', 'Mã môn học');
            $sheet1->setCellValue('C3', '');
            $sheet1->setCellValue('D3', 'VD: IT001, IT002...');

            $sheet1->setCellValue('A4', '3');
            $sheet1->setCellValue('B4', 'Khoa');
            $sheet1->setCellValue('C4', $admin->unit ? $admin->unit->unit_name : '');
            $sheet1->setCellValue('D4', 'Tự động điền');

            // Format
            $sheet1->getStyle('A1:D1')->getFont()->setBold(true);
            $sheet1->getColumnDimension('A')->setWidth(8);
            $sheet1->getColumnDimension('B')->setWidth(25);
            $sheet1->getColumnDimension('C')->setWidth(25);
            $sheet1->getColumnDimension('D')->setWidth(30);

            // Sheet 2: Danh sách điểm
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('DanhSachDiem');

            // Header
            $headers = ['STT', 'Mã SV', 'Họ tên', 'Lớp', 'Điểm 10', 'Điểm chữ', 'Điểm 4', 'Trạng thái', 'Ghi chú'];
            $column = 'A';
            foreach ($headers as $header) {
                $sheet2->setCellValue($column . '1', $header);
                $column++;
            }

            // Dữ liệu mẫu
            $sheet2->setCellValue('A2', '1');
            $sheet2->setCellValue('B2', '210001');
            $sheet2->setCellValue('C2', 'Nguyễn Văn A');
            $sheet2->setCellValue('D2', 'DH21CNTT');
            $sheet2->setCellValue('E2', '8.5');
            $sheet2->setCellValue('I2', 'Điểm tốt');

            $sheet2->setCellValue('A3', '2');
            $sheet2->setCellValue('B3', '210002');
            $sheet2->setCellValue('C3', 'Trần Thị B');
            $sheet2->setCellValue('D3', 'DH21CNTT');
            $sheet2->setCellValue('E3', '7.0');

            // Format
            $sheet2->getStyle('A1:I1')->getFont()->setBold(true);
            $sheet2->getStyle('A1:I1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('CCE5FF');

            $columnWidths = [8, 12, 25, 15, 10, 10, 10, 15, 20];
            $column = 'A';
            foreach ($columnWidths as $width) {
                $sheet2->getColumnDimension($column)->setWidth($width);
                $column++;
            }

            // Thêm ghi chú
            $lastRow = $sheet2->getHighestRow() + 2;
            $sheet2->setCellValue('A' . $lastRow, 'LƯU Ý:');
            $sheet2->setCellValue('A' . ($lastRow + 1), '- Chỉ cần điền: STT, Mã SV, Điểm 10');
            $sheet2->setCellValue('A' . ($lastRow + 2), '- Các cột khác hệ thống sẽ tự động tính');
            $sheet2->setCellValue('A' . ($lastRow + 3), '- Điểm 10 phải từ 0.0 đến 10.0');
            $sheet2->getStyle('A' . $lastRow)->getFont()->setBold(true);

            // Set active sheet
            $spreadsheet->setActiveSheetIndex(0);

            return $spreadsheet;

        } catch (\Exception $e) {
            Log::error('Failed to generate template', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}