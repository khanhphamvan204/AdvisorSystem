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
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

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

            // Cập nhật semester_id vào generalInfo
            $generalInfo['semester_id'] = $generalValidation['semester']->semester_id;

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
                'semester' => $generalInfo['semester_display'],
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

        $semesterDisplay = trim($sheet->getCell('C2')->getValue()); // VD: "Học kỳ 1 - 2024-2025"
        $courseDisplay = trim($sheet->getCell('C3')->getValue());   // VD: "IT001 - Nhập môn Lập trình"

        return [
            'semester_display' => $semesterDisplay,
            'course_display' => $courseDisplay,
            'course_code' => $this->extractCourseCode($courseDisplay)
        ];
    }

    /**
     * Trích xuất mã môn học từ chuỗi dropdown
     * VD: "IT001 - Nhập môn Lập trình" -> "IT001"
     */
    private function extractCourseCode($courseDisplay)
    {
        if (empty($courseDisplay)) {
            return null;
        }

        // Nếu có dấu " - ", lấy phần trước
        if (strpos($courseDisplay, ' - ') !== false) {
            return trim(explode(' - ', $courseDisplay)[0]);
        }

        // Nếu không có, coi như đã nhập đúng mã
        return trim($courseDisplay);
    }

    /**
     * Parse semester display thành semester_name và academic_year
     * VD: "Học kỳ 1 - 2024-2025" -> ['Học kỳ 1', '2024-2025']
     */
    private function parseSemesterDisplay($semesterDisplay)
    {
        if (empty($semesterDisplay)) {
            return [null, null];
        }

        // Format: "Học kỳ 1 - 2024-2025"
        if (strpos($semesterDisplay, ' - ') !== false) {
            $parts = explode(' - ', $semesterDisplay);
            return [
                'semester_name' => trim($parts[0]),
                'academic_year' => trim($parts[1])
            ];
        }

        return [null, null];
    }

    /**
     * Validate thông tin chung
     */
    private function validateGeneralInfo($generalInfo, $adminId)
    {
        // Parse semester display
        $semesterParts = $this->parseSemesterDisplay($generalInfo['semester_display']);

        if (!$semesterParts['semester_name'] || !$semesterParts['academic_year']) {
            return [
                'valid' => false,
                'message' => 'Thông tin học kỳ không đúng định dạng. Vui lòng chọn từ dropdown.'
            ];
        }

        // Tìm semester theo tên và năm học
        $semester = Semester::where('semester_name', $semesterParts['semester_name'])
            ->where('academic_year', $semesterParts['academic_year'])
            ->first();

        if (!$semester) {
            return [
                'valid' => false,
                'message' => "Không tìm thấy học kỳ: {$generalInfo['semester_display']}"
            ];
        }

        // Lưu semester_id vào generalInfo để dùng sau
        $generalInfo['semester_id'] = $semester->semester_id;

        // Kiểm tra course_code
        if (empty($generalInfo['course_code'])) {
            return [
                'valid' => false,
                'message' => 'Thiếu mã môn học. Vui lòng chọn từ dropdown.'
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
     * Tạo file Excel mẫu với dropdown để download
     */
    public function generateTemplate($adminId)
    {
        try {
            $admin = Advisor::with('unit')->find($adminId);

            $spreadsheet = new Spreadsheet();

            // ==================== SHEET 1: THÔNG TIN CHUNG ====================
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('ThongTinChung');

            // Header
            $sheet1->setCellValue('A1', 'STT');
            $sheet1->setCellValue('B1', 'Trường');
            $sheet1->setCellValue('C1', 'Giá trị');
            $sheet1->setCellValue('D1', 'Ghi chú');

            // Dữ liệu
            $sheet1->setCellValue('A2', '1');
            $sheet1->setCellValue('B2', 'Học kỳ');
            $sheet1->setCellValue('C2', '');
            $sheet1->setCellValue('D2', 'Chọn từ dropdown');

            $sheet1->setCellValue('A3', '2');
            $sheet1->setCellValue('B3', 'Môn học');
            $sheet1->setCellValue('C3', '');
            $sheet1->setCellValue('D3', 'Chọn từ dropdown');

            $sheet1->setCellValue('A4', '3');
            $sheet1->setCellValue('B4', 'Khoa');
            $sheet1->setCellValue('C4', $admin->unit ? $admin->unit->unit_name : '');
            $sheet1->setCellValue('D4', 'Tự động điền');

            // Format
            $sheet1->getStyle('A1:D1')->getFont()->setBold(true);
            $sheet1->getStyle('A1:D1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('CCE5FF');

            $sheet1->getColumnDimension('A')->setWidth(8);
            $sheet1->getColumnDimension('B')->setWidth(25);
            $sheet1->getColumnDimension('C')->setWidth(30);
            $sheet1->getColumnDimension('D')->setWidth(30);

            // ==================== DROPDOWN 1: HỌC KỲ ====================
            $semesters = Semester::orderBy('academic_year', 'desc')
                ->orderBy('semester_name', 'desc')
                ->get();

            $semesterOptions = $semesters->map(function ($s) {
                return $s->semester_name . ' - ' . $s->academic_year;
            })->toArray();

            if (!empty($semesterOptions)) {
                $validation1 = $sheet1->getCell('C2')->getDataValidation();
                $validation1->setType(DataValidation::TYPE_LIST);
                $validation1->setFormula1('"' . implode(',', $semesterOptions) . '"');
                $validation1->setShowDropDown(true);
                $validation1->setErrorTitle('Lỗi nhập liệu');
                $validation1->setError('Vui lòng chọn học kỳ từ danh sách dropdown');
                $validation1->setPromptTitle('Chọn học kỳ');
                $validation1->setPrompt('Chọn học kỳ bạn muốn nhập điểm');
            }

            // ==================== DROPDOWN 2: MÔN HỌC ====================
            $courses = Course::where('unit_id', $admin->unit_id)
                ->orderBy('course_code')
                ->get();

            $courseOptions = $courses->map(function ($c) {
                return $c->course_code . ' - ' . $c->course_name;
            })->toArray();

            if (!empty($courseOptions)) {
                $validation2 = $sheet1->getCell('C3')->getDataValidation();
                $validation2->setType(DataValidation::TYPE_LIST);
                $validation2->setFormula1('"' . implode(',', $courseOptions) . '"');
                $validation2->setShowDropDown(true);
                $validation2->setErrorTitle('Lỗi nhập liệu');
                $validation2->setError('Vui lòng chọn môn học từ danh sách dropdown');
                $validation2->setPromptTitle('Chọn môn học');
                $validation2->setPrompt('Chọn môn học bạn muốn nhập điểm');
            }

            // Thêm ghi chú hướng dẫn
            $lastRow = 6;
            $sheet1->setCellValue('A' . $lastRow, 'HƯỚNG DẪN:');
            $sheet1->setCellValue('A' . ($lastRow + 1), '1. Click vào ô C2, chọn học kỳ từ dropdown');
            $sheet1->setCellValue('A' . ($lastRow + 2), '2. Click vào ô C3, chọn môn học từ dropdown');
            $sheet1->setCellValue('A' . ($lastRow + 3), '3. Chuyển sang sheet "DanhSachDiem" để nhập điểm');
            $sheet1->getStyle('A' . $lastRow)->getFont()->setBold(true);

            // ==================== SHEET 2: DANH SÁCH ĐIỂM ====================
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

            // Format header
            $sheet2->getStyle('A1:I1')->getFont()->setBold(true);
            $sheet2->getStyle('A1:I1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('CCE5FF');

            // Set column widths
            $columnWidths = [8, 12, 25, 15, 10, 10, 10, 15, 20];
            $column = 'A';
            foreach ($columnWidths as $width) {
                $sheet2->getColumnDimension($column)->setWidth($width);
                $column++;
            }

            // Thêm ghi chú
            $lastRow = $sheet2->getHighestRow() + 2;
            $sheet2->setCellValue('A' . $lastRow, 'LƯU Ý:');
            $sheet2->setCellValue('A' . ($lastRow + 1), '- CHỈ CẦN ĐIỀN: STT, Mã SV, Điểm 10');
            $sheet2->setCellValue('A' . ($lastRow + 2), '- Các cột khác (Điểm chữ, Điểm 4, Trạng thái) hệ thống sẽ tự động tính');
            $sheet2->setCellValue('A' . ($lastRow + 3), '- Điểm 10 phải từ 0.0 đến 10.0');
            $sheet2->setCellValue('A' . ($lastRow + 4), '- Cột Họ tên, Lớp chỉ để tham khảo, hệ thống lấy từ database');
            $sheet2->getStyle('A' . $lastRow)->getFont()->setBold(true);

            // Set active sheet về sheet đầu tiên
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