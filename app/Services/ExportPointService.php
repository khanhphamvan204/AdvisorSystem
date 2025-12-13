<?php

namespace App\Services;

use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Unit;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ExportPointService
{
    protected ExcelHeaderService $excelHeaderService;

    public function __construct(ExcelHeaderService $excelHeaderService)
    {
        $this->excelHeaderService = $excelHeaderService;
    }
    /**
     * Xuất điểm rèn luyện theo lớp
     */
    public function exportTrainingPointsByClass(int $classId, int $semesterId)
    {
        // Lấy thông tin lớp
        $class = ClassModel::with('faculty')->findOrFail($classId);

        // Lấy thông tin học kỳ
        $semester = Semester::findOrFail($semesterId);

        // Lấy danh sách sinh viên và sắp xếp theo tên (chữ cuối cùng)
        $students = Student::where('class_id', $classId)
            ->where('status', 'studying')
            ->get()
            ->sortBy(function ($student) {
                $nameParts = explode(' ', trim($student->full_name));
                return end($nameParts); // Lấy tên (phần cuối)
            })
            ->values(); // Reset lại keys sau khi sort

        // Tính điểm cho từng sinh viên sử dụng PointCalculationService
        $studentsWithPoints = $students->map(function ($student) use ($semesterId) {
            try {
                $trainingPoints = PointCalculationService::calculateTrainingPoints(
                    $student->student_id,
                    $semesterId
                );

                // Lấy chi tiết hoạt động
                $activities = PointCalculationService::getTrainingActivitiesDetail(
                    $student->student_id,
                    $semesterId
                );

                $attendedCount = collect($activities)->where('status', 'attended')->count();
                $absentCount = collect($activities)->where('status', 'absent')->count();

                return [
                    'student' => $student,
                    'training_points' => $trainingPoints,
                    'attended_count' => $attendedCount,
                    'absent_count' => $absentCount,
                    'total_activities' => count($activities)
                ];
            } catch (\Exception $e) {
                return [
                    'student' => $student,
                    'training_points' => 0,
                    'attended_count' => 0,
                    'absent_count' => 0,
                    'total_activities' => 0,
                    'error' => $e->getMessage()
                ];
            }
        });

        return $this->generateTrainingPointsExcel($studentsWithPoints, $class, $semester, 'class');
    }

    /**
     * Xuất điểm rèn luyện theo khoa
     */
    public function exportTrainingPointsByFaculty(int $facultyId, int $semesterId)
    {
        // Lấy thông tin khoa
        $faculty = Unit::where('type', 'faculty')->findOrFail($facultyId);

        // Lấy thông tin học kỳ
        $semester = Semester::findOrFail($semesterId);

        // Lấy danh sách sinh viên thuộc các lớp của khoa và sắp xếp theo tên
        $students = Student::whereHas('class', function ($query) use ($facultyId) {
            $query->where('faculty_id', $facultyId);
        })
            ->where('status', 'studying')
            ->with('class')
            ->get()
            ->sortBy(function ($student) {
                $nameParts = explode(' ', trim($student->full_name));
                return end($nameParts); // Lấy tên (phần cuối)
            })
            ->values(); // Reset lại keys sau khi sort

        // Tính điểm cho từng sinh viên
        $studentsWithPoints = $students->map(function ($student) use ($semesterId) {
            try {
                $trainingPoints = PointCalculationService::calculateTrainingPoints(
                    $student->student_id,
                    $semesterId
                );

                $activities = PointCalculationService::getTrainingActivitiesDetail(
                    $student->student_id,
                    $semesterId
                );

                $attendedCount = collect($activities)->where('status', 'attended')->count();
                $absentCount = collect($activities)->where('status', 'absent')->count();

                return [
                    'student' => $student,
                    'training_points' => $trainingPoints,
                    'attended_count' => $attendedCount,
                    'absent_count' => $absentCount,
                    'total_activities' => count($activities)
                ];
            } catch (\Exception $e) {
                return [
                    'student' => $student,
                    'training_points' => 0,
                    'attended_count' => 0,
                    'absent_count' => 0,
                    'total_activities' => 0,
                    'error' => $e->getMessage()
                ];
            }
        });

        return $this->generateTrainingPointsExcel($studentsWithPoints, $faculty, $semester, 'faculty');
    }

    /**
     * Xuất điểm CTXH theo lớp (Tích lũy từ đầu đến giờ)
     */
    public function exportSocialPointsByClass(int $classId)
    {
        // Lấy thông tin lớp
        $class = ClassModel::with('faculty')->findOrFail($classId);

        // Lấy danh sách sinh viên và sắp xếp theo tên (chữ cuối cùng)
        $students = Student::where('class_id', $classId)
            ->where('status', 'studying')
            ->get()
            ->sortBy(function ($student) {
                $nameParts = explode(' ', trim($student->full_name));
                return end($nameParts); // Lấy tên (phần cuối)
            })
            ->values(); // Reset lại keys sau khi sort

        // Tính điểm CTXH cho từng sinh viên (tích lũy từ đầu đến giờ)
        $studentsWithPoints = $students->map(function ($student) {
            try {
                // Không truyền $semesterId => tính tích lũy từ đầu đến giờ
                $socialPoints = PointCalculationService::calculateSocialPoints(
                    $student->student_id
                );

                // Lấy chi tiết hoạt động CTXH từ đầu đến giờ
                $activities = PointCalculationService::getSocialActivitiesDetail(
                    $student->student_id
                );

                return [
                    'student' => $student,
                    'social_points' => $socialPoints,
                    'total_activities' => count($activities)
                ];
            } catch (\Exception $e) {
                return [
                    'student' => $student,
                    'social_points' => 0,
                    'total_activities' => 0,
                    'error' => $e->getMessage()
                ];
            }
        });

        return $this->generateSocialPointsExcel($studentsWithPoints, $class, null, 'class');
    }

    /**
     * Xuất điểm CTXH theo khoa (Tích lũy từ đầu đến giờ)
     */
    public function exportSocialPointsByFaculty(int $facultyId)
    {
        // Lấy thông tin khoa
        $faculty = Unit::where('type', 'faculty')->findOrFail($facultyId);

        // Lấy danh sách sinh viên thuộc các lớp của khoa
        $students = Student::whereHas('class', function ($query) use ($facultyId) {
            $query->where('faculty_id', $facultyId);
        })
            ->where('status', 'studying')
            ->with('class')
            ->orderBy('full_name')
            ->get();

        // Tính điểm CTXH cho từng sinh viên (tích lũy từ đầu đến giờ)
        $studentsWithPoints = $students->map(function ($student) {
            try {
                // Không truyền $semesterId => tính tích lũy từ đầu đến giờ
                $socialPoints = PointCalculationService::calculateSocialPoints(
                    $student->student_id
                );

                $activities = PointCalculationService::getSocialActivitiesDetail(
                    $student->student_id
                );

                return [
                    'student' => $student,
                    'social_points' => $socialPoints,
                    'total_activities' => count($activities)
                ];
            } catch (\Exception $e) {
                return [
                    'student' => $student,
                    'social_points' => 0,
                    'total_activities' => 0,
                    'error' => $e->getMessage()
                ];
            }
        });

        return $this->generateSocialPointsExcel($studentsWithPoints, $faculty, null, 'faculty');
    }

    /**
     * Tạo file Excel điểm rèn luyện
     */
    private function generateTrainingPointsExcel($studentsWithPoints, $entity, $semester, $type)
    {
        // Tạo spreadsheet với header chuyên nghiệp
        $spreadsheet = $this->excelHeaderService->createWithProfessionalHeader();
        $sheet = $spreadsheet->getActiveSheet();

        // Điền tiêu đề chính (dòng 5)
        $this->excelHeaderService->fillTitle($sheet, 'BẢNG ĐIỂM RÈN LUYỆN', 5, 'I');

        // Điền thông tin chi tiết (bắt đầu từ dòng 7)
        $infoData = [];

        if ($type === 'faculty') {
            $infoData['Khoa:'] = $entity->unit_name;
        } else {
            $infoData['Khoa:'] = $entity->faculty->unit_name;
            $infoData['Lớp:'] = $entity->class_name;
        }

        $infoData['Học kỳ:'] = $semester->semester_name . ' - Năm học: ' . $semester->academic_year;
        $infoData['Ngày xuất:'] = date('d/m/Y H:i');

        $row = $this->excelHeaderService->fillInfoSection($sheet, $infoData, 7, 'I');

        // Dòng trống
        $row++;

        // Header bảng dữ liệu
        $headers = ['STT', 'MSSV', 'Họ và tên', 'Lớp', 'Điểm ban đầu', 'Số HĐ tham dự', 'Số HĐ vắng', 'Điểm rèn luyện', 'Xếp loại'];
        $this->excelHeaderService->createTableHeader($sheet, $headers, $row);
        $row++;

        // Chuẩn bị dữ liệu sinh viên
        $tableData = [];
        $stt = 1;
        foreach ($studentsWithPoints as $data) {
            $student = $data['student'];
            $trainingPoints = $data['training_points'];
            $classification = $this->classifyTrainingPoints($trainingPoints);

            $tableData[] = [
                $stt,
                $student->user_code,
                $student->full_name,
                $type === 'faculty' ? $student->class->class_name : $entity->class_name,
                70, // Điểm ban đầu
                $data['attended_count'],
                $data['absent_count'],
                $trainingPoints,
                $classification
            ];
            $stt++;
        }

        // Điền dữ liệu bảng
        $lastRow = $this->excelHeaderService->fillTableData($sheet, $tableData, $row);
        $row = $lastRow;

        // Auto format columns
        $this->excelHeaderService->autoFormatColumns(
            $sheet,
            range('A', 'I'),
            [
                'C' => 30, // Họ tên rộng hơn
                'D' => 18, // Lớp
                'E' => 18, // Điểm ban đầu
                'F' => 18, // Số HĐ tham dự
                'G' => 15, // Số HĐ vắng
                'H' => 18, // Điểm rèn luyện
                'I' => 15  // Xếp loại
            ]
        );

        // Tạo file với encoding UTF-8
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $fileName = 'DiemRenLuyen_' . ($type === 'class' ? $entity->class_name : $entity->unit_name) . '_' . $semester->semester_name . '_' . date('YmdHis') . '.xlsx';
        $filePath = storage_path('app/public/exports/' . $fileName);

        if (!file_exists(storage_path('app/public/exports'))) {
            mkdir(storage_path('app/public/exports'), 0755, true);
        }

        $writer->save($filePath);

        return [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'download_url' => url('storage/exports/' . $fileName)
        ];
    }

    /**
     * Tạo file Excel điểm CTXH
     */
    private function generateSocialPointsExcel($studentsWithPoints, $entity, $semester, $type)
    {
        // Tạo spreadsheet với header chuyên nghiệp
        $spreadsheet = $this->excelHeaderService->createWithProfessionalHeader();
        $sheet = $spreadsheet->getActiveSheet();

        // Điền tiêu đề chính (dòng 5)
        $this->excelHeaderService->fillTitle($sheet, 'BẢNG ĐIỂM CÔNG TÁC XÃ HỘI (TÍCH LŨY)', 5, 'G');

        // Điền thông tin chi tiết (bắt đầu từ dòng 7)
        $infoData = [];

        if ($type === 'faculty') {
            $infoData['Khoa:'] = $entity->unit_name;
        } else {
            $infoData['Khoa:'] = $entity->faculty->unit_name;
            $infoData['Lớp:'] = $entity->class_name;
        }

        $infoData['Tính đến:'] = date('d/m/Y H:i');
        $infoData['Ghi chú:'] = '(Điểm CTXH được tích lũy từ đầu khóa học đến thời điểm hiện tại)';

        $row = $this->excelHeaderService->fillInfoSection($sheet, $infoData, 7, 'G');

        // Dòng trống
        $row++;

        // Header bảng dữ liệu
        $headers = ['STT', 'MSSV', 'Họ và tên', 'Lớp', 'Số HĐ CTXH', 'Điểm CTXH', 'Xếp loại'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit($columns[$index] . $row, $header, DataType::TYPE_STRING);
        }

        $this->styleTableHeader($sheet, 'A' . $row . ':G' . $row);
        $row++;

        // Chuẩn bị dữ liệu sinh viên
        $tableData = [];
        $stt = 1;
        foreach ($studentsWithPoints as $data) {
            $student = $data['student'];
            $socialPoints = $data['social_points'];
            $classification = $this->classifySocialPoints($socialPoints);

            $tableData[] = [
                $stt,
                $student->user_code,
                $student->full_name,
                $type === 'faculty' ? $student->class->class_name : $entity->class_name,
                $data['total_activities'],
                $socialPoints,
                $classification
            ];
            $stt++;
        }

        // Điền dữ liệu bảng
        $lastRow = $this->excelHeaderService->fillTableData($sheet, $tableData, $row);
        $row = $lastRow;

        // Auto format columns
        $this->excelHeaderService->autoFormatColumns(
            $sheet,
            range('A', 'G'),
            [
                'C' => 30, // Họ tên rộng hơn
                'D' => 18, // Lớp
                'E' => 15, // Số HĐ CTXH
                'F' => 15, // Điểm CTXH
                'G' => 15  // Xếp loại
            ]
        );

        // Tạo file với encoding UTF-8
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $fileName = 'DiemCTXH_TichLuy_' . ($type === 'class' ? $entity->class_name : $entity->unit_name) . '_' . date('YmdHis') . '.xlsx';
        $filePath = storage_path('app/public/exports/' . $fileName);

        if (!file_exists(storage_path('app/public/exports'))) {
            mkdir(storage_path('app/public/exports'), 0755, true);
        }

        $writer->save($filePath);

        return [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'download_url' => url('storage/exports/' . $fileName)
        ];
    }

    /**
     * Style cho header
     */
    private function styleHeader($sheet, $range, $fontSize = 12, $bold = false, $color = '000000')
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => $bold,
                'size' => $fontSize,
                'color' => ['rgb' => $color]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    /**
     * Style cho header bảng
     */
    private function styleTableHeader($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);
    }

    /**
     * Style cho dòng dữ liệu
     */
    private function styleDataRow($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Center align cho các cột số
        $cells = explode(':', $range);
        $row = preg_replace('/[A-Z]/', '', $cells[0]);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /**
     * Xếp loại điểm rèn luyện
     */
    private function classifyTrainingPoints($point)
    {
        if ($point >= 90)
            return 'Xuất sắc';
        if ($point >= 80)
            return 'Tốt';
        if ($point >= 65)
            return 'Khá';
        if ($point >= 50)
            return 'Trung bình';
        if ($point >= 35)
            return 'Yếu';
        return 'Kém';
    }

    /**
     * Xếp loại điểm CTXH
     */
    private function classifySocialPoints($point)
    {
        if ($point >= 170)
            return 'Đạt';
        return 'Không đạt';
    }
}
