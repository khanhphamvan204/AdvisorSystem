<?php

namespace App\Http\Controllers;

use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Semester;
use MongoDB\Client as MongoClient;

class ScheduleImportController extends Controller
{
    protected $scheduleService;
    protected $mongodb;
    protected $db;

    public function __construct(ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
        $this->mongodb = new MongoClient(env('MONGODB_URI', 'mongodb://localhost:27017'));
        $this->db = $this->mongodb->selectDatabase(env('MONGODB_DATABASE', 'advisor_system'));
    }

    /**
     * Import lịch học từ Excel
     * POST /api/admin/schedules/import
     * Role: Admin only
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls|max:10240' // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'File không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Lấy role và user_id từ middleware
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            $file = $request->file('file');
            $fileName = 'schedule_import_' . time() . '.xlsx';

            // 1. Lưu file vào disk 'local' (storage/app)
            $relativePath = $file->storeAs('temp', $fileName, 'local');

            // 2. Lấy đường dẫn tuyệt đối chuẩn của hệ điều hành
            $absolutePath = Storage::disk('local')->path($relativePath);

            // Gọi Service xử lý
            $result = $this->scheduleService->importSchedulesFromExcel($absolutePath);

            // 3. Xóa file temp bằng Storage cho an toàn
            Storage::disk('local')->delete($relativePath);

            Log::info('Imported schedules', [
                'admin_id' => $currentUserId,
                'role' => $currentRole,
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Import thành công',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            // Nếu có lỗi, cố gắng xóa file nếu nó còn tồn tại
            if (isset($relativePath) && Storage::disk('local')->exists($relativePath)) {
                Storage::disk('local')->delete($relativePath);
            }

            Log::error('Failed to import schedules', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kiểm tra xung đột lịch
     * POST /api/schedules/check-conflict
     */
    public function checkConflict(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|integer|exists:Students,student_id',
            'activity_id' => 'required|integer|exists:Activities,activity_id',
            'semester_id' => 'required|integer|exists:Semesters,semester_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Lấy thông tin activity từ database
            $activity = \App\Models\Activity::find($request->activity_id);

            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hoạt động không tồn tại'
                ], 404);
            }

            $result = $this->scheduleService->checkScheduleConflict(
                $request->student_id,
                $activity->start_time,
                $activity->end_time,
                $request->semester_id
            );

            // Thêm thông tin activity vào response
            $result['activity'] = [
                'activity_id' => $activity->activity_id,
                'title' => $activity->title,
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time
            ];

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem lịch học của một sinh viên cụ thể
     * GET /api/admin/schedules/student/{student_id} (Admin, Advisor)
     * GET /api/student/schedules/my-schedule (Student)
     * Role: Admin, Advisor, Student (chỉ xem của mình)
     */
    public function getStudentSchedule(Request $request, $student_id)
    {
        try {
            // Lấy role và user_id từ middleware
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            // Kiểm tra quyền: Admin, Advisor, hoặc Student
            if (!in_array($currentRole, ['admin', 'advisor', 'student'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem lịch học'
                ], 403);
            }

            // Nếu là student, chỉ được xem lịch của chính mình
            if ($currentRole === 'student') {
                $studentInfo = Student::where('student_id', $currentUserId)->first();
                if (!$studentInfo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy thông tin sinh viên'
                    ], 404);
                }

                // Kiểm tra xem có đang cố xem lịch của người khác không
                if ($studentInfo->student_id != $student_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể xem lịch học của chính mình'
                    ], 403);
                }
            }

            // Validate
            $validator = Validator::make(['student_id' => $student_id], [
                'student_id' => 'required|integer|exists:Students,student_id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Lấy thông tin sinh viên từ MySQL
            $student = Student::with(['class.faculty', 'class.advisor'])
                ->find($student_id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sinh viên'
                ], 404);
            }

            // Nếu là advisor, kiểm tra xem có phải advisor của lớp này không
            if ($currentRole === 'advisor') {
                if (!$student->class || $student->class->advisor_id != $currentUserId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không phải cố vấn của sinh viên này'
                    ], 403);
                }
            }

            // Lấy semester_id từ query params
            $semesterId = $request->query('semester_id');

            if ($semesterId) {
                // Xem lịch học của một học kỳ cụ thể
                $semester = Semester::find($semesterId);
                if (!$semester) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy học kỳ'
                    ], 404);
                }

                // Query MongoDB
                $schedule = $this->db->student_schedules->findOne([
                    'student_code' => intval($student->user_code),
                    'semester' => trim($semester->semester_name),
                    'academic_year' => trim($semester->academic_year)
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'student' => [
                            'student_id' => $student->student_id,
                            'user_code' => $student->user_code,
                            'full_name' => $student->full_name,
                            'email' => $student->email,
                            'phone_number' => $student->phone_number,
                            'class_name' => $student->class->class_name ?? null,
                            'faculty_name' => $student->class->faculty->unit_name ?? null,
                            'advisor_name' => $student->class->advisor->full_name ?? null,
                            'status' => $student->status,
                            'position' => $student->position
                        ],
                        'semester' => [
                            'semester_id' => $semester->semester_id,
                            'semester_name' => $semester->semester_name,
                            'academic_year' => $semester->academic_year,
                            'start_date' => $semester->start_date,
                            'end_date' => $semester->end_date
                        ],
                        'schedule' => $schedule ? [
                            'total_courses' => count($schedule['registered_courses'] ?? []),
                            'registered_courses' => $this->convertDatesToString($schedule['registered_courses'] ?? []),
                            'flat_schedule' => $this->convertDatesToString($schedule['flat_schedule'] ?? []),
                            'updated_at' => $this->formatMongoDate($schedule['updated_at'] ?? null)
                        ] : null,
                        'has_schedule' => $schedule !== null
                    ]
                ], 200);

            } else {
                // Xem tất cả lịch học của sinh viên
                $schedules = $this->db->student_schedules->find([
                    'student_code' => intval($student->user_code)
                ])->toArray();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'student' => [
                            'student_id' => $student->student_id,
                            'user_code' => $student->user_code,
                            'full_name' => $student->full_name,
                            'email' => $student->email,
                            'phone_number' => $student->phone_number,
                            'class_name' => $student->class->class_name ?? null,
                            'faculty_name' => $student->class->faculty->unit_name ?? null,
                            'advisor_name' => $student->class->advisor->full_name ?? null,
                            'status' => $student->status,
                            'position' => $student->position
                        ],
                        'total_semesters' => count($schedules),
                        'schedules' => array_map(function ($schedule) {
                            return [
                                'semester' => $schedule['semester'] ?? null,
                                'academic_year' => $schedule['academic_year'] ?? null,
                                'total_courses' => count($schedule['registered_courses'] ?? []),
                                'registered_courses' => $this->convertDatesToString($schedule['registered_courses'] ?? []),
                                'flat_schedule' => $this->convertDatesToString($schedule['flat_schedule'] ?? []),
                                'updated_at' => $this->formatMongoDate($schedule['updated_at'] ?? null)
                            ];
                        }, $schedules)
                    ]
                ], 200);
            }

        } catch (\Exception $e) {
            Log::error('Get student schedule error', [
                'student_id' => $student_id,
                'user_id' => $request->current_user_id ?? null,
                'role' => $request->current_role ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy lịch học: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem lịch học của cả lớp
     * GET /api/admin/schedules/class/{class_id}
     * Role: Admin, Advisor (của lớp đó)
     */
    public function getClassSchedule(Request $request, $class_id)
    {
        try {
            // Lấy role và user_id từ middleware
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            // Kiểm tra quyền
            if (!in_array($currentRole, ['admin', 'advisor'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem lịch học của lớp'
                ], 403);
            }

            // Validate
            $validator = Validator::make(['class_id' => $class_id], [
                'class_id' => 'required|integer|exists:Classes,class_id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Lấy thông tin lớp
            $class = ClassModel::with(['advisor', 'faculty'])->find($class_id);

            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy lớp học'
                ], 404);
            }

            // Nếu là advisor, kiểm tra quyền
            if ($currentRole === 'advisor' && $class->advisor_id != $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không phải cố vấn của lớp này'
                ], 403);
            }

            // Lấy semester_id từ query params (bắt buộc)
            $semesterId = $request->query('semester_id');

            if (!$semesterId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng cung cấp semester_id'
                ], 422);
            }

            $semester = Semester::find($semesterId);
            if (!$semester) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy học kỳ'
                ], 404);
            }

            // Lấy danh sách sinh viên trong lớp
            $students = Student::where('class_id', $class_id)
                ->orderBy('user_code')
                ->get();

            // Lấy student_codes để query MongoDB
            $studentCodes = $students->pluck('user_code')->map(function ($code) {
                return intval($code);
            })->toArray();

            // Query MongoDB
            $cursor = $this->db->student_schedules->find([
                'student_code' => ['$in' => $studentCodes],
                'semester' => trim($semester->semester_name),
                'academic_year' => trim($semester->academic_year)
            ]);

            $schedules = [];
            foreach ($cursor as $schedule) {
                $schedules[] = $schedule;
            }

            // Map lịch học với sinh viên
            $scheduleData = [];
            foreach ($students as $student) {
                $studentCode = intval($student->user_code);
                $schedule = collect($schedules)->firstWhere('student_code', $studentCode);

                $scheduleData[] = [
                    'student_id' => $student->student_id,
                    'user_code' => $student->user_code,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'phone_number' => $student->phone_number,
                    'position' => $student->position,
                    'status' => $student->status,
                    'has_schedule' => $schedule !== null,
                    'total_courses' => $schedule ? count($schedule['registered_courses'] ?? []) : 0,
                    'registered_courses' => $schedule ? $this->convertDatesToString($schedule['registered_courses'] ?? []) : [],
                    'flat_schedule' => $schedule ? $this->convertDatesToString($schedule['flat_schedule'] ?? []) : []
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'class' => [
                        'class_id' => $class->class_id,
                        'class_name' => $class->class_name,
                        'description' => $class->description,
                        'advisor_name' => $class->advisor->full_name ?? null,
                        'advisor_email' => $class->advisor->email ?? null,
                        'faculty_name' => $class->faculty->unit_name ?? null
                    ],
                    'semester' => [
                        'semester_id' => $semester->semester_id,
                        'semester_name' => $semester->semester_name,
                        'academic_year' => $semester->academic_year,
                        'start_date' => $semester->start_date,
                        'end_date' => $semester->end_date
                    ],
                    'summary' => [
                        'total_students' => count($students),
                        'students_with_schedule' => count(array_filter($scheduleData, fn($s) => $s['has_schedule'])),
                        'students_without_schedule' => count(array_filter($scheduleData, fn($s) => !$s['has_schedule']))
                    ],
                    'students' => $scheduleData
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get class schedule error', [
                'class_id' => $class_id,
                'user_id' => $request->current_user_id ?? null,
                'role' => $request->current_role ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy lịch học lớp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tìm kiếm sinh viên theo lịch học
     * POST /api/admin/schedules/search
     * Role: Admin only
     */
    public function searchBySchedule(Request $request)
    {
        try {
            // Lấy role từ middleware
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            // Chỉ admin mới được search toàn bộ
            if ($currentRole !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ Admin mới có quyền tìm kiếm lịch học'
                ], 403);
            }

            // Validate
            $validator = Validator::make($request->all(), [
                'semester_id' => 'required|integer|exists:Semesters,semester_id',
                'day_of_week' => 'nullable|integer|min:2|max:8',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
                'course_code' => 'nullable|string',
                'class_id' => 'nullable|integer|exists:Classes,class_id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $semesterId = $request->semester_id;
            $semester = Semester::find($semesterId);

            // Build MongoDB query
            $query = [
                'semester' => trim($semester->semester_name),
                'academic_year' => trim($semester->academic_year)
            ];

            // Lọc theo class_id nếu có
            if ($request->has('class_id')) {
                $students = Student::where('class_id', $request->class_id)
                    ->pluck('user_code')
                    ->map(fn($code) => intval($code))
                    ->toArray();

                $query['student_code'] = ['$in' => $students];
            }

            // Lọc theo thứ
            if ($request->has('day_of_week')) {
                $query['flat_schedule.day_of_week'] = (int) $request->day_of_week;
            }

            // Lọc theo mã môn
            if ($request->has('course_code')) {
                $query['flat_schedule.course_code'] = $request->course_code;
            }

            // Tìm kiếm trong MongoDB
            $cursor = $this->db->student_schedules->find($query);

            $schedules = [];
            foreach ($cursor as $schedule) {
                $schedules[] = $schedule;
            }

            // Lọc theo thời gian nếu có
            if ($request->has('start_time') && $request->has('end_time')) {
                $startTime = $request->start_time;
                $endTime = $request->end_time;

                $schedules = array_filter($schedules, function ($schedule) use ($startTime, $endTime) {
                    foreach ($schedule['flat_schedule'] ?? [] as $slot) {
                        if ($slot['start_time_str'] < $endTime && $slot['end_time_str'] > $startTime) {
                            return true;
                        }
                    }
                    return false;
                });
            }

            // Lấy thông tin sinh viên từ MySQL
            $studentCodes = array_column($schedules, 'student_code');
            $students = Student::with(['class'])
                ->whereIn('user_code', $studentCodes)
                ->get()
                ->keyBy('user_code');

            // Map data
            $results = [];
            foreach ($schedules as $schedule) {
                $student = $students->get(strval($schedule['student_code']));
                if ($student) {
                    $results[] = [
                        'student_id' => $student->student_id,
                        'user_code' => $student->user_code,
                        'full_name' => $student->full_name,
                        'email' => $student->email,
                        'phone_number' => $student->phone_number,
                        'class_name' => $student->class->class_name ?? null,
                        'position' => $student->position,
                        'matched_schedules' => $this->convertDatesToString($schedule['flat_schedule'] ?? [])
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'semester' => [
                        'semester_id' => $semester->semester_id,
                        'semester_name' => $semester->semester_name,
                        'academic_year' => $semester->academic_year
                    ],
                    'search_criteria' => [
                        'class_id' => $request->class_id,
                        'day_of_week' => $request->day_of_week,
                        'start_time' => $request->start_time,
                        'end_time' => $request->end_time,
                        'course_code' => $request->course_code
                    ],
                    'total_found' => count($results),
                    'students' => $results
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Search schedule error', [
                'user_id' => $request->current_user_id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tìm kiếm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa lịch học của sinh viên trong một học kỳ
     * DELETE /api/admin/schedules/student/{student_id}
     * Role: Admin only
     */
    public function deleteStudentSchedule(Request $request, $student_id)
    {
        try {
            // Lấy role từ middleware
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            // Chỉ admin mới được xóa
            if ($currentRole !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ Admin mới có quyền xóa lịch học'
                ], 403);
            }

            // Validate
            $validator = Validator::make(array_merge(
                ['student_id' => $student_id],
                $request->all()
            ), [
                'student_id' => 'required|integer|exists:Students,student_id',
                'semester_id' => 'required|integer|exists:Semesters,semester_id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::find($student_id);
            $semester = Semester::find($request->semester_id);

            // Xóa trong MongoDB
            $result = $this->db->student_schedules->deleteOne([
                'student_code' => intval($student->user_code),
                'semester' => trim($semester->semester_name),
                'academic_year' => trim($semester->academic_year)
            ]);

            if ($result->getDeletedCount() > 0) {
                Log::info('Deleted student schedule', [
                    'admin_id' => $currentUserId,
                    'student_id' => $student_id,
                    'semester_id' => $request->semester_id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Đã xóa lịch học thành công'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy lịch học để xóa'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Delete schedule error', [
                'student_id' => $student_id,
                'admin_id' => $request->current_user_id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa lịch học: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Convert MongoDB Date objects sang string
     */
    /**
     * Helper: Convert MongoDB Date objects sang string (đệ quy)
     */
    private function convertDatesToString($data)
    {
        if ($data instanceof \MongoDB\BSON\UTCDateTime) {
            // Convert UTCDateTime thành string
            return $data->toDateTime()->format('Y-m-d H:i:s');
        }

        if (is_array($data) || $data instanceof \MongoDB\Model\BSONArray || $data instanceof \MongoDB\Model\BSONDocument) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->convertDatesToString($value); // Đệ quy
            }
            return $result;
        }

        if (is_object($data)) {
            // Convert BSON Document thành array rồi xử lý
            return $this->convertDatesToString((array) $data);
        }

        return $data;
    }
    /**
     * Helper: Format MongoDB Date sang string
     */
    private function formatMongoDate($date)
    {
        if ($date instanceof \MongoDB\BSON\UTCDateTime) {
            return $date->toDateTime()->format('Y-m-d H:i:s');
        }
        return $date;
    }
}