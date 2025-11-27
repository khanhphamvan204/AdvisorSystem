<?php

namespace App\Services;

use MongoDB\Client as MongoClient;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use MongoDB\BSON\UTCDateTime;

class ScheduleService
{
    protected $mongodb;
    protected $db;

    public function __construct()
    {
        $this->mongodb = new MongoClient(env('MONGODB_URI', 'mongodb://localhost:27017'));
        $this->db = $this->mongodb->selectDatabase(env('MONGODB_DATABASE', 'advisor_system'));
    }

    /**
     * Import lịch học từ Excel
     * * CẤU TRÚC FILE THỰC TẾ (Dựa trên file mẫu):
     * - Dòng 6: Mã SV (E6), Họ tên (J6)
     * - Dòng 7: Lớp (E7), Ngành (J7)   
     * - Dòng 8: Hệ đào tạo (E8), Loại đào tạo (J8)
     * - Dòng 9: Học kỳ (E9), Năm học (J9)
     * - Dòng 10: HEADER BẢNG
     * - Dòng 11+: DỮ LIỆU CHI TIẾT
     */
    public function importSchedulesFromExcel($excelFile)
    {
        $spreadsheet = IOFactory::load($excelFile);
        $sheet = $spreadsheet->getActiveSheet();

        // ==================== ĐỌC THÔNG TIN SINH VIÊN ====================
        // Dòng 6: Mã SV và Họ tên
        $studentCode = $this->getCellValue($sheet, 'E', 6);
        $studentName = $this->getCellValue($sheet, 'J', 6);

        // Dòng 7: Lớp và Ngành
        $className = $this->getCellValue($sheet, 'E', 7);
        $major = $this->getCellValue($sheet, 'J', 7);

        // Dòng 8: Hệ đào tạo và Loại đào tạo
        $educationType = $this->getCellValue($sheet, 'E', 8);
        $educationMode = $this->getCellValue($sheet, 'J', 8);

        // Dòng 9: Học kỳ và Năm học (ĐỌC TỪ FILE)
        $semesterFromFile = $this->getCellValue($sheet, 'E', 9);
        $academicYearFromFile = $this->getCellValue($sheet, 'J', 9);

        // Chuẩn hóa mã sinh viên
        $studentCode = trim(str_replace("'", "", $studentCode));

        if (empty($studentCode)) {
            throw new \Exception('Không tìm thấy mã sinh viên ở ô E6. Vui lòng kiểm tra lại cấu trúc file.');
        }

        Log::info('Reading student info from Excel', [
            'student_code' => $studentCode,
            'student_name' => $studentName,
            'class_name' => $className,
            'major' => $major,
            'education_type' => $educationType,
            'education_mode' => $educationMode,
            'semester_from_file' => $semesterFromFile,
            'academic_year_from_file' => $academicYearFromFile
        ]);

        // ==================== ĐỌC LỊCH HỌC ====================
        $studentSchedule = [
            'student_code' => $studentCode,
            'student_name' => trim($studentName ?? ''),
            'class_name' => trim($className ?? ''),
            'education_type' => trim($educationType ?? 'Đại học'),
            'education_mode' => trim($educationMode ?? 'Chính quy'),
            'major' => trim($major ?? ''),
            'flat_schedule' => []
        ];

        // Dòng 10: Header
        // Dòng 11+: Dữ liệu bắt đầu
        $rowIndex = 11;
        $flatSchedule = [];
        $semester = null;
        $academicYear = null;

        // Biến lưu tên môn học dòng trước để xử lý trường hợp merge cell hoặc ghi tắt
        $lastCourseName = null;

        while (true) {
            $stt = $this->getCellValue($sheet, 'A', $rowIndex);

            // Dừng khi gặp dòng trống hoặc STT không phải số
            if (empty($stt) || !is_numeric($stt)) {
                // Kiểm tra thêm dòng tiếp theo đề phòng dòng trống ngẫu nhiên
                $nextStt = $this->getCellValue($sheet, 'A', $rowIndex + 1);
                if (empty($nextStt)) {
                    break;
                }
            }

            // Đọc dữ liệu từng cột (A -> L)
            $courseClassCode = $this->getCellValue($sheet, 'B', $rowIndex);
            $courseName = $this->getCellValue($sheet, 'C', $rowIndex);
            $courseType = $this->getCellValue($sheet, 'D', $rowIndex);
            $dayOfWeek = $this->getCellValue($sheet, 'E', $rowIndex);
            $startPeriod = $this->getCellValue($sheet, 'F', $rowIndex);
            $endPeriod = $this->getCellValue($sheet, 'G', $rowIndex);
            $startDate = $this->getCellValue($sheet, 'H', $rowIndex);
            $endDate = $this->getCellValue($sheet, 'I', $rowIndex);
            $instructor = $this->getCellValue($sheet, 'J', $rowIndex);
            $room = $this->getCellValue($sheet, 'K', $rowIndex);
            $scheduleType = $this->getCellValue($sheet, 'L', $rowIndex);

            // Bỏ qua nếu thiếu thông tin quan trọng
            if (empty($courseClassCode)) {
                $rowIndex++;
                continue;
            }

            // Parse ngày
            $parsedStartDate = $this->parseExcelDate($startDate);
            $parsedEndDate = $this->parseExcelDate($endDate);

            if (!$parsedStartDate || !$parsedEndDate) {
                Log::warning('Invalid date in row', [
                    'row' => $rowIndex,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]);
                $rowIndex++;
                continue;
            }

            // Xác định học kỳ và năm học (ƯU TIÊN ĐỌC TỪ FILE)
            if (!$semester || !$academicYear) {
                if (!empty($semesterFromFile) && !empty($academicYearFromFile)) {
                    // Đọc từ file Excel (dòng 9)
                    $semester = trim($semesterFromFile);
                    $academicYear = trim($academicYearFromFile);
                } else {
                    // Fallback: Tự động tính từ ngày
                    $semesterInfo = $this->determineSemesterFromDate($parsedStartDate);
                    $semester = $semesterInfo['semester'];
                    $academicYear = $semesterInfo['academic_year'];

                    Log::warning('Học kỳ không có trong file, tự động tính từ ngày', [
                        'calculated_semester' => $semester,
                        'calculated_academic_year' => $academicYear
                    ]);
                }
            }

            // Convert thứ sang số
            $dayNumber = $this->convertDayToNumber($dayOfWeek);

            // Xác định loại lịch (LT/TH)
            $isPractice = (stripos($courseType, 'thực hành') !== false) ||
                (stripos($courseType, 'thuc hanh') !== false);
            $scheduleTypeCode = $isPractice ? 'TH' : 'LT';

            // Convert tiết sang giờ
            $startTime = $this->periodToTime((int) $startPeriod, false, $scheduleTypeCode);
            $endTime = $this->periodToTime((int) $endPeriod, true, $scheduleTypeCode);

            // Xử lý tên môn học đặc biệt (cùng môn)
            $actualCourseName = $courseName;
            if (
                stripos($courseName, 'cùng môn') !== false ||
                stripos($courseName, '(cùng môn trên)') !== false ||
                empty(trim($courseName))
            ) {
                if ($lastCourseName) {
                    $actualCourseName = $lastCourseName;
                }
            } else {
                $lastCourseName = $courseName;
            }

            // Lưu trực tiếp vào flat schedule
            $flatSchedule[] = [
                'course_class_code' => $courseClassCode,
                'course_name' => $actualCourseName,
                'type' => $scheduleTypeCode,
                'start_date' => new UTCDateTime($parsedStartDate->timestamp * 1000),
                'end_date' => new UTCDateTime($parsedEndDate->timestamp * 1000),
                'day_of_week' => $dayNumber,
                'start_period' => (int) $startPeriod,
                'end_period' => (int) $endPeriod,
                'start_time_str' => $startTime,
                'end_time_str' => $endTime,
                'time_range' => $startTime . '-' . $endTime,
                'periods' => range((int) $startPeriod, (int) $endPeriod),
                'room' => trim($room ?? ''),
                'instructor' => trim($instructor ?? ''),
                'note' => $courseType,
                'schedule_type' => $scheduleType
            ];

            $rowIndex++;
        }

        if (empty($flatSchedule)) {
            throw new \Exception('Không có dữ liệu lịch học nào được tìm thấy (kiểm tra từ dòng 11 trở đi)');
        }

        // ==================== LƯU VÀO MONGODB ====================
        $studentSchedule['semester'] = $semester;
        $studentSchedule['academic_year'] = $academicYear;
        $studentSchedule['flat_schedule'] = $flatSchedule;
        $studentSchedule['updated_at'] = new UTCDateTime();

        $collection = $this->db->selectCollection('student_schedules');

        $result = $collection->updateOne(
            [
                'student_code' => $studentCode,
                'semester' => $semester,
                'academic_year' => $academicYear
            ],
            ['$set' => $studentSchedule],
            ['upsert' => true]
        );

        Log::info('Imported schedule for student', [
            'student_code' => $studentCode,
            'student_name' => $studentName,
            'semester' => $semester,
            'academic_year' => $academicYear,
            'total_schedules' => count($studentSchedule['flat_schedule'])
        ]);

        return [
            'student_code' => $studentCode,
            'student_name' => $studentName,
            'semester' => $semester,
            'academic_year' => $academicYear,
            'schedules_count' => count($studentSchedule['flat_schedule'])
        ];
    }

    /**
     * Kiểm tra xung đột lịch học - chỉ dựa vào thời gian
     */
    public function checkScheduleConflict($studentId, $activityStartTime, $activityEndTime)
    {
        $student = \App\Models\Student::find($studentId);

        if (!$student) {
            return ['has_conflict' => false, 'error' => 'Sinh viên không tồn tại'];
        }

        $collection = $this->db->selectCollection('student_schedules');
        $studentCode = strval(trim((string) $student->user_code));

        // Lấy tất cả lịch học của sinh viên (không filter theo học kỳ)
        $cursor = $collection->find(['student_code' => $studentCode]);

        // Convert cursor thành array để tránh lỗi "Cursors cannot rewind"
        $studentSchedules = iterator_to_array($cursor);

        $actStartObj = Carbon::parse($activityStartTime);
        $actEndObj = Carbon::parse($activityEndTime);

        $actStartDateStr = $actStartObj->toDateString();
        $actEndDateStr = $actEndObj->toDateString();
        $actStartTime = $actStartObj->format('H:i');
        $actEndTime = $actEndObj->format('H:i');

        $currentDate = $actStartObj->copy()->startOfDay();
        $endDate = $actEndObj->copy()->startOfDay();

        // Duyệt qua từng ngày của hoạt động
        while ($currentDate <= $endDate) {
            $activityDate = $currentDate->toDateString();
            $activityDayOfWeek = $currentDate->dayOfWeek == 0 ? 8 : $currentDate->dayOfWeek + 1;

            $dayStartTime = $actStartTime;
            $dayEndTime = $actEndTime;

            // Điều chỉnh thời gian cho các ngày đầu/cuối nếu hoạt động kéo dài nhiều ngày
            if ($actStartDateStr !== $actEndDateStr) {
                if ($activityDate === $actStartDateStr) {
                    $dayEndTime = '23:59';
                } elseif ($activityDate === $actEndDateStr) {
                    $dayStartTime = '00:00';
                } else {
                    $dayStartTime = '00:00';
                    $dayEndTime = '23:59';
                }
            }

            // Kiểm tra xung đột với tất cả lịch học của sinh viên
            foreach ($studentSchedules as $studentSchedule) {
                foreach ($studentSchedule['flat_schedule'] as $schedule) {
                    // Kiểm tra thứ
                    if ($schedule['day_of_week'] != $activityDayOfWeek) {
                        continue;
                    }

                    // Kiểm tra ngày
                    $schedStart = $schedule['start_date']->toDateTime()->format('Y-m-d');
                    $schedEnd = $schedule['end_date']->toDateTime()->format('Y-m-d');

                    if ($activityDate < $schedStart || $activityDate > $schedEnd) {
                        continue;
                    }

                    // Kiểm tra thời gian
                    $classStartStr = $schedule['start_time_str'];
                    $classEndStr = $schedule['end_time_str'];

                    if ($dayStartTime < $classEndStr && $dayEndTime > $classStartStr) {
                        return [
                            'has_conflict' => true,
                            'conflict_course' => $schedule['course_name'],
                            'conflict_course_class' => $schedule['course_class_code'],
                            'conflict_time' => $schedule['time_range'],
                            'conflict_room' => $schedule['room'],
                            'conflict_instructor' => $schedule['instructor'],
                            'conflict_periods' => $schedule['periods'],
                            'conflict_date_range' => $schedStart . ' đến ' . $schedEnd,
                            'conflict_date' => $activityDate,
                            'conflict_type' => $schedule['type'],
                            'conflict_schedule_type' => $schedule['schedule_type'] ?? 'Lịch học',
                            'conflict_semester' => $studentSchedule['semester'] ?? null,
                            'conflict_academic_year' => $studentSchedule['academic_year'] ?? null
                        ];
                    }
                }
            }

            $currentDate->addDay();
        }

        return ['has_conflict' => false];
    }

    /**
     * Lấy giá trị cell
     */
    private function getCellValue($sheet, $column, $row)
    {
        try {
            $cell = $sheet->getCell($column . $row);
            $value = $cell->getCalculatedValue();

            if (is_array($value)) {
                return (string) ($value[0] ?? '');
            }

            return trim((string) $value);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Parse ngày từ Excel
     */
    private function parseExcelDate($value)
    {
        if (empty($value)) {
            return null;
        }

        // Nếu là số Excel date
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
            } catch (\Exception $e) {
                return null;
            }
        }

        // Thử các định dạng khác
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->startOfDay();
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Tự động xác định học kỳ từ ngày
     */
    private function determineSemesterFromDate($date)
    {
        $month = $date->month;
        $year = $date->year;

        // Học kỳ 1: Tháng 8 -> Tháng 12
        if ($month >= 8 && $month <= 12) {
            return [
                'semester' => 'Học kỳ 1',
                'academic_year' => $year . '-' . ($year + 1)
            ];
        }
        // Học kỳ 2: Tháng 1 -> Tháng 5
        elseif ($month >= 1 && $month <= 5) {
            return [
                'semester' => 'Học kỳ 2',
                'academic_year' => ($year - 1) . '-' . $year
            ];
        }
        // Học kỳ hè: Tháng 6 -> Tháng 7
        else {
            return [
                'semester' => 'Học kỳ hè',
                'academic_year' => $year . '-' . ($year + 1)
            ];
        }
    }

    /**
     * Convert thứ sang số
     */
    private function convertDayToNumber($day)
    {
        $day = trim($day);
        $mapping = [
            '2' => 2,
            'T2' => 2,
            'Thứ 2' => 2,
            '3' => 3,
            'T3' => 3,
            'Thứ 3' => 3,
            '4' => 4,
            'T4' => 4,
            'Thứ 4' => 4,
            '5' => 5,
            'T5' => 5,
            'Thứ 5' => 5,
            '6' => 6,
            'T6' => 6,
            'Thứ 6' => 6,
            '7' => 7,
            'T7' => 7,
            'Thứ 7' => 7,
            'CN' => 8,
            'Chủ nhật' => 8
        ];
        return $mapping[$day] ?? 2;
    }

    /**
     * Map tiết -> giờ
     */
    private function periodToTime($period, $isEnd = false, $type = 'LT')
    {
        $theoryMap = [
            1 => ['07:00', '07:45'],
            2 => ['07:45', '08:30'],
            3 => ['08:30', '09:15'],
            4 => ['09:40', '10:25'],
            5 => ['10:25', '11:10'],
            6 => ['11:10', '11:55'],
            7 => ['12:30', '13:15'],
            8 => ['13:15', '14:00'],
            9 => ['14:00', '14:45'],
            10 => ['15:10', '15:55'],
            11 => ['15:55', '16:40'],
            12 => ['16:40', '17:25'],
            13 => ['18:00', '18:45'],
            14 => ['18:45', '19:30'],
            15 => ['19:30', '20:15'],
            16 => ['20:15', '21:00'],
            17 => ['21:00', '21:45']
        ];

        $practiceMap = [
            1 => ['07:00', '07:45'],
            2 => ['07:45', '08:30'],
            3 => ['08:30', '09:15'],
            4 => ['09:15', '10:00'],
            5 => ['10:00', '10:45'],
            6 => ['10:45', '11:30'],
            7 => ['12:30', '13:15'],
            8 => ['13:15', '14:00'],
            9 => ['14:00', '14:45'],
            10 => ['14:45', '15:30'],
            11 => ['15:30', '16:15'],
            12 => ['16:15', '17:00'],
            13 => ['18:00', '18:45'],
            14 => ['18:45', '19:30'],
            15 => ['19:30', '20:15'],
            16 => ['20:15', '21:00'],
            17 => ['21:00', '21:45']
        ];

        $map = ($type === 'TH') ? $practiceMap : $theoryMap;
        return $map[$period][$isEnd ? 1 : 0] ?? '07:00';
    }
}
