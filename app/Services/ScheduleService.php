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

    public function importSchedulesFromExcel($excelFile)
    {
        $spreadsheet = IOFactory::load($excelFile);

        // --- Sheet 1: Courses ---
        $courseSheet = $spreadsheet->getSheet(0);
        $courseData = [];

        foreach ($courseSheet->getRowIterator(2) as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }

            if (empty($cells[1]))
                continue;

            $courseCode = trim((string) $cells[1]);
            $phase = trim((string) ($cells[4] ?? 'Toàn khóa'));
            $note = trim((string) ($cells[11] ?? ''));

            // Phân loại LT / TH
            $isPractice = false;
            $keywords = ['thực hành', 'thuc hanh', '(th)', 'phòng máy', 'pm'];
            foreach ($keywords as $keyword) {
                if (mb_stripos($note, $keyword) !== false) {
                    $isPractice = true;
                    break;
                }
            }
            $scheduleType = $isPractice ? 'TH' : 'LT';

            $startDate = $this->parseExcelDate($cells[5]);
            $endDate = $this->parseExcelDate($cells[6]);

            if (!$startDate || !$endDate) {
                Log::warning('Invalid date', ['code' => $courseCode]);
                continue;
            }

            if (!isset($courseData[$courseCode])) {
                $courseData[$courseCode] = [
                    'course_code' => $courseCode,
                    'course_name' => $cells[2],
                    'instructor' => $cells[3],
                    'schedules' => []
                ];
            }

            $courseData[$courseCode]['schedules'][] = [
                'phase' => $phase,
                'type' => $scheduleType,
                'start_date' => new UTCDateTime($startDate->timestamp * 1000),
                'end_date' => new UTCDateTime($endDate->timestamp * 1000),
                'day_of_week' => $this->convertDayToNumber($cells[7]),
                'start_period' => (int) $cells[8],
                'end_period' => (int) $cells[9],
                'start_time' => $this->periodToTime((int) $cells[8], false, $scheduleType),
                'end_time' => $this->periodToTime((int) $cells[9], true, $scheduleType),
                'room' => $cells[10],
                'note' => $note
            ];
        }

        // Insert Courses
        $collection = $this->db->selectCollection('course_schedules');
        foreach ($courseData as $course) {
            $collection->updateOne(
                ['course_code' => $course['course_code']],
                ['$set' => array_merge($course, ['updated_at' => new UTCDateTime()])],
                ['upsert' => true]
            );
        }

        // --- Sheet 2: Students ---
        $studentSheet = $spreadsheet->getSheet(1);
        $studentSchedules = [];

        foreach ($studentSheet->getRowIterator(2) as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }
            if (empty($cells[1]))
                continue;

            $studentCode = trim((string) $cells[1]);
            $courseCode = trim((string) $cells[4]);

            if (!isset($studentSchedules[$studentCode])) {
                $studentSchedules[$studentCode] = [
                    'student_code' => $studentCode,
                    'student_name' => trim((string) $cells[2]),
                    'class_name' => trim((string) $cells[3]),
                    'semester' => trim((string) $cells[5]),
                    'academic_year' => trim((string) $cells[6]),
                    'registered_courses' => []
                ];
            }

            $courseSchedule = $courseData[$courseCode] ?? null;
            if ($courseSchedule) {
                $studentSchedules[$studentCode]['registered_courses'][] = [
                    'course_code' => $courseCode,
                    'course_name' => $courseSchedule['course_name'],
                    'schedules' => $courseSchedule['schedules']
                ];
            }
        }

        // Insert Students
        $collection = $this->db->selectCollection('student_schedules');
        foreach ($studentSchedules as $student) {
            $student['flat_schedule'] = $this->buildFlatSchedule($student['registered_courses']);
            $student['updated_at'] = new UTCDateTime();

            $collection->updateOne(
                [
                    'student_code' => $student['student_code'],
                    'semester' => $student['semester'],
                    'academic_year' => $student['academic_year']
                ],
                ['$set' => $student],
                ['upsert' => true]
            );
        }

        return [
            'courses_imported' => count($courseData),
            'students_imported' => count($studentSchedules)
        ];
    }

    /**
     * Check Conflict bằng cách so sánh Time String (Chính xác nhất)
     * Lấy thông tin semester từ MySQL rồi query MongoDB
     */
    public function checkScheduleConflict($studentId, $activityStartTime, $activityEndTime, $semesterId)
    {
        // Lấy thông tin semester và student từ MySQL
        $semester = \App\Models\Semester::find($semesterId);
        $student = \App\Models\Student::find($studentId);

        if (!$semester) {
            return ['has_conflict' => false, 'error' => 'Học kỳ không tồn tại'];
        }

        if (!$student) {
            return ['has_conflict' => false, 'error' => 'Sinh viên không tồn tại'];
        }

        // Query MongoDB bằng student_code (MSSV), không phải student_id
        $collection = $this->db->selectCollection('student_schedules');

        // Chuẩn hóa dữ liệu trước khi query (trim, convert string)
        $semesterName = trim((string) $semester->semester_name);
        $academicYear = trim((string) $semester->academic_year);
        $studentCode = intval(trim((string) $student->user_code));


        Log::info('Searching schedule in MongoDB', [
            'student_code' => $studentCode,
            'semester' => $semesterName,
            'academic_year' => $academicYear
        ]);

        $studentSchedule = $collection->findOne([
            'student_code' => $studentCode,  // Dùng MSSV để tìm
            'semester' => $semesterName,
            'academic_year' => $academicYear
        ]);

        if (!$studentSchedule) {
            Log::warning('No schedule found in MongoDB', [
                'student_code' => $studentCode,
                'semester' => $semesterName,
                'academic_year' => $academicYear
            ]);
            return ['has_conflict' => false];
        }

        $actStartObj = Carbon::parse($activityStartTime);
        $actEndObj = Carbon::parse($activityEndTime);

        $activityDate = $actStartObj->toDateString();
        $activityDayOfWeek = $actStartObj->dayOfWeek == 0 ? 8 : $actStartObj->dayOfWeek + 1;

        // Format H:i (ví dụ: "09:30")
        $actStartStr = $actStartObj->format('H:i');
        $actEndStr = $actEndObj->format('H:i');

        foreach ($studentSchedule['flat_schedule'] as $schedule) {
            // 1. Check Thứ
            if ($schedule['day_of_week'] != $activityDayOfWeek)
                continue;

            // 2. Check Ngày (Giai đoạn)
            $schedStart = $schedule['start_date']->toDateTime()->format('Y-m-d');
            $schedEnd = $schedule['end_date']->toDateTime()->format('Y-m-d');
            if ($activityDate < $schedStart || $activityDate > $schedEnd)
                continue;

            // 3. Check Giờ trùng (Dùng start_time_str và end_time_str từ DB)
            $classStartStr = $schedule['start_time_str'];
            $classEndStr = $schedule['end_time_str'];

            // Logic Overlap: (StartA < EndB) && (EndA > StartB)
            if ($actStartStr < $classEndStr && $actEndStr > $classStartStr) {
                return [
                    'has_conflict' => true,
                    'conflict_course' => $schedule['course_code'],
                    'conflict_phase' => $schedule['phase'] ?? 'Không xác định',
                    'conflict_time' => $schedule['time_range'],
                    'conflict_room' => $schedule['room'],
                    'conflict_periods' => $schedule['periods'],
                    'conflict_date_range' => $schedStart . ' đến ' . $schedEnd
                ];
            }
        }

        return ['has_conflict' => false];
    }

    public function getAvailableStudentsForActivity($studentIds, $activityStartTime, $activityEndTime, $semesterId)
    {
        $available = [];
        $conflicts = [];

        foreach ($studentIds as $studentId) {
            $check = $this->checkScheduleConflict($studentId, $activityStartTime, $activityEndTime, $semesterId);
            if ($check['has_conflict']) {
                $conflicts[] = [
                    'student_id' => $studentId,
                    'reason' => "Trùng môn {$check['conflict_course']} ({$check['conflict_time']})"
                ];
            } else {
                $available[] = $studentId;
            }
        }
        return ['available' => $available, 'conflicts' => $conflicts];
    }

    private function parseExcelDate($value)
    {
        if (empty($value))
            return null;
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
            } catch (\Exception $e) {
                return null;
            }
        }
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false)
                    return $date->startOfDay();
            } catch (\Exception $e) {
                continue;
            }
        }
        return null;
    }

    private function convertDayToNumber($day)
    {
        $mapping = ['2' => 2, 'T2' => 2, 'Thứ 2' => 2, '3' => 3, 'T3' => 3, 'Thứ 3' => 3, '4' => 4, 'T4' => 4, 'Thứ 4' => 4, '5' => 5, 'T5' => 5, 'Thứ 5' => 5, '6' => 6, 'T6' => 6, 'Thứ 6' => 6, '7' => 7, 'T7' => 7, 'Thứ 7' => 7, 'CN' => 8, 'Chủ nhật' => 8];
        return $mapping[$day] ?? 2;
    }

    /**
     * Map Tiết -> Giờ (Hỗ trợ LT và TH)
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

        // Ví dụ: Thực hành 
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

    /**
     * Cập nhật buildFlatSchedule để lưu thêm start_time_str, end_time_str
     */
    private function buildFlatSchedule($registeredCourses)
    {
        $flat = [];
        foreach ($registeredCourses as $course) {
            foreach ($course['schedules'] as $schedule) {
                $flat[] = [
                    'course_code' => $course['course_code'],
                    'phase' => $schedule['phase'] ?? 'Toàn khóa',
                    'start_date' => $schedule['start_date'],
                    'end_date' => $schedule['end_date'],
                    'day_of_week' => $schedule['day_of_week'],
                    'periods' => range($schedule['start_period'], $schedule['end_period']),
                    // Lưu giờ dạng string để so sánh conflict
                    'start_time_str' => $schedule['start_time'],
                    'end_time_str' => $schedule['end_time'],
                    'time_range' => $schedule['start_time'] . '-' . $schedule['end_time'],
                    'room' => $schedule['room']
                ];
            }
        }
        return $flat;
    }
}