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
     * Xuất điểm CTXH theo lớp
     */
    public function exportSocialPointsByClass(int $classId, int $semesterId)
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

        // Tính điểm CTXH cho từng sinh viên (tích lũy từ đầu khóa đến học kỳ này)
        $studentsWithPoints = $students->map(function ($student) use ($semesterId) {
            try {
                $socialPoints = PointCalculationService::calculateSocialPoints(
                    $student->student_id,
                    $semesterId
                );

                // Lấy chi tiết hoạt động CTXH
                $activities = PointCalculationService::getSocialActivitiesDetail(
                    $student->student_id,
                    $semesterId
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

        return $this->generateSocialPointsExcel($studentsWithPoints, $class, $semester, 'class');
    }

    /**
     * Xuất điểm CTXH theo khoa
     */
    public function exportSocialPointsByFaculty(int $facultyId, int $semesterId)
    {
        // Lấy thông tin khoa
        $faculty = Unit::where('type', 'faculty')->findOrFail($facultyId);

        // Lấy thông tin học kỳ
        $semester = Semester::findOrFail($semesterId);

        // Lấy danh sách sinh viên thuộc các lớp của khoa
        $students = Student::whereHas('class', function ($query) use ($facultyId) {
            $query->where('faculty_id', $facultyId);
        })
            ->where('status', 'studying')
            ->with('class')
            ->orderBy('full_name')
            ->get();

        // Tính điểm CTXH cho từng sinh viên
        $studentsWithPoints = $students->map(function ($student) use ($semesterId) {
            try {
                $socialPoints = PointCalculationService::calculateSocialPoints(
                    $student->student_id,
                    $semesterId
                );

                $activities = PointCalculationService::getSocialActivitiesDetail(
                    $student->student_id,
                    $semesterId
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

        return $this->generateSocialPointsExcel($studentsWithPoints, $faculty, $semester, 'faculty');
    }

    /**
     * Tạo file Excel điểm rèn luyện
     */
    private function generateTrainingPointsExcel($studentsWithPoints, $entity, $semester, $type)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Thiết lập tiêu đề
        $row = 1;

        // Header: Tên trường
        $sheet->setCellValueExplicit('A' . $row, 'TRƯỜNG ĐẠI HỌC CÔNG THƯƠNG TP.HCM', DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':I' . $row, 14, true);
        $row++;

        // Header: Tên khoa/lớp
        if ($type === 'faculty') {
            $sheet->setCellValueExplicit('A' . $row, strtoupper($entity->unit_name), DataType::TYPE_STRING);
        } else {
            $sheet->setCellValueExplicit('A' . $row, strtoupper($entity->faculty->unit_name), DataType::TYPE_STRING);
        }
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':I' . $row, 13, true);
        $row++;

        // Dòng trống
        $row++;

        // Tiêu đề báo cáo
        $sheet->setCellValueExplicit('A' . $row, 'BẢNG ĐIỂM RÈN LUYỆN', DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':I' . $row, 16, true, 'FF0000');
        $row++;

        // Thông tin học kỳ và lớp
        if ($type === 'class') {
            $sheet->setCellValueExplicit('A' . $row, 'Lớp: ' . $entity->class_name, DataType::TYPE_STRING);
        }
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':I' . $row, 12);
        $row++;

        $sheet->setCellValueExplicit('A' . $row, 'Học kỳ: ' . $semester->semester_name . ' - Năm học: ' . $semester->academic_year, DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':I' . $row, 12);
        $row++;

        // Dòng trống
        $row++;

        // Header bảng dữ liệu
        $headers = ['STT', 'MSSV', 'Họ và tên', 'Lớp', 'Điểm ban đầu', 'Số HĐ tham dự', 'Số HĐ vắng', 'Điểm rèn luyện', 'Xếp loại'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit($columns[$index] . $row, $header, DataType::TYPE_STRING);
        }

        $this->styleTableHeader($sheet, 'A' . $row . ':I' . $row);
        $row++;

        // Dữ liệu sinh viên
        $stt = 1;
        foreach ($studentsWithPoints as $data) {
            $student = $data['student'];
            $trainingPoints = $data['training_points'];
            $classification = $this->classifyTrainingPoints($trainingPoints);

            $sheet->setCellValue('A' . $row, $stt);
            $sheet->setCellValueExplicit('B' . $row, $student->user_code, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $row, $student->full_name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D' . $row, $type === 'faculty' ? $student->class->class_name : $entity->class_name, DataType::TYPE_STRING);
            $sheet->setCellValue('E' . $row, 70); // Điểm ban đầu
            $sheet->setCellValue('F' . $row, $data['attended_count']);
            $sheet->setCellValue('G' . $row, $data['absent_count']);
            $sheet->setCellValue('H' . $row, $trainingPoints);
            $sheet->setCellValueExplicit('I' . $row, $classification, DataType::TYPE_STRING);

            $this->styleDataRow($sheet, 'A' . $row . ':I' . $row);
            $row++;
            $stt++;
        }

        // Thống kê
        $row++;
        $totalStudents = $studentsWithPoints->count();
        $avgPoints = $studentsWithPoints->avg('training_points');

        $sheet->setCellValueExplicit('A' . $row, 'THỐNG KÊ CHUNG', DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':I' . $row, 12, true);
        $row++;

        $sheet->setCellValueExplicit('A' . $row, 'Tổng số sinh viên: ' . $totalStudents, DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $row++;

        $sheet->setCellValueExplicit('A' . $row, 'Điểm trung bình: ' . number_format($avgPoints, 2), DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $row++;

        // Thống kê xếp loại
        $classifications = [
            'Xuất sắc' => $studentsWithPoints->filter(fn($s) => $s['training_points'] >= 90)->count(),
            'Tốt' => $studentsWithPoints->filter(fn($s) => $s['training_points'] >= 80 && $s['training_points'] < 90)->count(),
            'Khá' => $studentsWithPoints->filter(fn($s) => $s['training_points'] >= 65 && $s['training_points'] < 80)->count(),
            'Trung bình' => $studentsWithPoints->filter(fn($s) => $s['training_points'] >= 50 && $s['training_points'] < 65)->count(),
            'Yếu' => $studentsWithPoints->filter(fn($s) => $s['training_points'] >= 35 && $s['training_points'] < 50)->count(),
            'Kém' => $studentsWithPoints->filter(fn($s) => $s['training_points'] < 35)->count(),
        ];

        $sheet->setCellValue('A' . $row, 'Phân bổ xếp loại:');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $row++;

        foreach ($classifications as $class => $count) {
            if ($count > 0) {
                $percentage = ($count / $totalStudents) * 100;
                $sheet->setCellValue('A' . $row, '  - ' . $class . ': ' . $count . ' SV (' . number_format($percentage, 1) . '%)');
                $sheet->mergeCells('A' . $row . ':D' . $row);
                $row++;
            }
        }

        // Thiết lập độ rộng cột
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(15);

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
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Thiết lập tiêu đề
        $row = 1;

        // Header: Tên trường
        $sheet->setCellValueExplicit('A' . $row, 'TRƯỜNG ĐẠI HỌC CÔNG THƯƠNG TP.HCM', DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':G' . $row, 14, true);
        $row++;

        // Header: Tên khoa/lớp
        if ($type === 'faculty') {
            $sheet->setCellValueExplicit('A' . $row, strtoupper($entity->unit_name), DataType::TYPE_STRING);
        } else {
            $sheet->setCellValueExplicit('A' . $row, strtoupper($entity->faculty->unit_name), DataType::TYPE_STRING);
        }
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':G' . $row, 13, true);
        $row++;

        // Dòng trống
        $row++;

        // Tiêu đề báo cáo
        $sheet->setCellValueExplicit('A' . $row, 'BẢNG ĐIỂM CÔNG TÁC XÃ HỘI (TÍCH LŨY)', DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':G' . $row, 16, true, 'FF0000');
        $row++;

        // Thông tin học kỳ và lớp
        if ($type === 'class') {
            $sheet->setCellValueExplicit('A' . $row, 'Lớp: ' . $entity->class_name, DataType::TYPE_STRING);
        }
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':G' . $row, 12);
        $row++;

        $sheet->setCellValueExplicit('A' . $row, 'Tính đến: ' . $semester->semester_name . ' - Năm học: ' . $semester->academic_year, DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':G' . $row, 12);
        $row++;

        $sheet->setCellValueExplicit('A' . $row, '(Điểm CTXH được tích lũy từ đầu khóa học)', DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':G' . $row, 10, false, '666666');
        $row++;

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

        // Dữ liệu sinh viên
        $stt = 1;
        foreach ($studentsWithPoints as $data) {
            $student = $data['student'];
            $socialPoints = $data['social_points'];
            $classification = $this->classifySocialPoints($socialPoints);

            $sheet->setCellValue('A' . $row, $stt);
            $sheet->setCellValueExplicit('B' . $row, $student->user_code, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $row, $student->full_name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D' . $row, $type === 'faculty' ? $student->class->class_name : $entity->class_name, DataType::TYPE_STRING);
            $sheet->setCellValue('E' . $row, $data['total_activities']);
            $sheet->setCellValue('F' . $row, $socialPoints);
            $sheet->setCellValueExplicit('G' . $row, $classification, DataType::TYPE_STRING);

            $this->styleDataRow($sheet, 'A' . $row . ':G' . $row);
            $row++;
            $stt++;
        }

        // Thống kê
        $row++;
        $totalStudents = $studentsWithPoints->count();
        $avgPoints = $studentsWithPoints->avg('social_points');

        $sheet->setCellValueExplicit('A' . $row, 'THỐNG KÊ CHUNG', DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $this->styleHeader($sheet, 'A' . $row . ':G' . $row, 12, true);
        $row++;

        $sheet->setCellValueExplicit('A' . $row, 'Tổng số sinh viên: ' . $totalStudents, DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $row++;

        $sheet->setCellValueExplicit('A' . $row, 'Điểm trung bình: ' . number_format($avgPoints, 2), DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $row++;

        // Thống kê xếp loại
        $classifications = [
            'Xuất sắc' => $studentsWithPoints->filter(fn($s) => $s['social_points'] >= 20)->count(),
            'Tốt' => $studentsWithPoints->filter(fn($s) => $s['social_points'] >= 15 && $s['social_points'] < 20)->count(),
            'Khá' => $studentsWithPoints->filter(fn($s) => $s['social_points'] >= 10 && $s['social_points'] < 15)->count(),
            'Trung bình' => $studentsWithPoints->filter(fn($s) => $s['social_points'] >= 5 && $s['social_points'] < 10)->count(),
            'Yếu' => $studentsWithPoints->filter(fn($s) => $s['social_points'] < 5)->count(),
        ];

        $sheet->setCellValueExplicit('A' . $row, 'Phân bổ xếp loại:', DataType::TYPE_STRING);
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $row++;

        foreach ($classifications as $class => $count) {
            if ($count > 0) {
                $percentage = ($count / $totalStudents) * 100;
                $sheet->setCellValue('A' . $row, '  - ' . $class . ': ' . $count . ' SV (' . number_format($percentage, 1) . '%)');
                $sheet->mergeCells('A' . $row . ':D' . $row);
                $row++;
            }
        }

        // Thiết lập độ rộng cột
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(15);

        // Tạo file với encoding UTF-8
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $fileName = 'DiemCTXH_' . ($type === 'class' ? $entity->class_name : $entity->unit_name) . '_' . $semester->semester_name . '_' . date('YmdHis') . '.xlsx';
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
        if ($point >= 20)
            return 'Xuất sắc';
        if ($point >= 15)
            return 'Tốt';
        if ($point >= 10)
            return 'Khá';
        if ($point >= 5)
            return 'Trung bình';
        return 'Yếu';
    }
}
