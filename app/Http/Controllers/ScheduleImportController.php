<?php

namespace App\Http\Controllers;

use App\Services\ScheduleService;
use App\Services\ScheduleImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Semester;
use MongoDB\Client as MongoClient;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ScheduleImportController extends Controller
{
    protected $scheduleService;
    protected $scheduleImportService;
    protected $mongodb;
    protected $db;

    public function __construct(ScheduleService $scheduleService, ScheduleImportService $scheduleImportService)
    {
        $this->scheduleService = $scheduleService;
        $this->scheduleImportService = $scheduleImportService;
        $this->mongodb = new MongoClient(env('MONGODB_URI', 'mongodb://localhost:27017'));
        $this->db = $this->mongodb->selectDatabase(env('MONGODB_DATABASE', 'advisor_system'));
    }

    /**
     * Import lịch học từ Excel
     * POST /api/admin/schedules/import
     * Role: Admin, Advisor
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls|max:10240'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'File không hợp lệ. Vui lòng upload file Excel (.xlsx hoặc .xls)',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            // Admin hoặc Advisor đều có thể import
            if (!in_array($currentRole, ['admin', 'advisor'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền import lịch học'
                ], 403);
            }

            $file = $request->file('file');
            $fileName = 'schedule_import_' . time() . '_' . uniqid() . '.xlsx';
            $relativePath = $file->storeAs('temp', $fileName, 'local');
            $absolutePath = Storage::disk('local')->path($relativePath);

            // Xử lý import - đọc học kỳ từ file Excel
            $result = $this->scheduleService->importSchedulesFromExcel($absolutePath);

            // Xóa file tạm
            Storage::disk('local')->delete($relativePath);

            // Kiểm tra sinh viên có tồn tại trong MySQL không
            $student = Student::where('user_code', $result['student_code'])->first();

            if (!$student) {
                Log::warning('Schedule imported but student not found in MySQL', [
                    'student_code' => $result['student_code'],
                    'imported_by' => $currentUserId,
                    'role' => $currentRole
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Import thành công nhưng sinh viên chưa tồn tại trong hệ thống',
                    'warning' => 'Vui lòng thêm sinh viên có mã ' . $result['student_code'] . ' vào hệ thống',
                    'data' => $result
                ], 200);
            }

            // Nếu là advisor, kiểm tra quyền
            if ($currentRole === 'advisor') {
                if (!$student->class || $student->class->advisor_id != $currentUserId) {
                    Storage::disk('local')->delete($relativePath);
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ có thể import lịch cho sinh viên trong lớp mình phụ trách'
                    ], 403);
                }
            }

            Log::info('Schedule imported successfully', [
                'imported_by' => $currentUserId,
                'role' => $currentRole,
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Import lịch học thành công',
                'data' => [
                    'student_code' => $result['student_code'],
                    'student_name' => $result['student_name'],
                    'student_id' => $student->student_id,
                    'class_name' => $student->class->class_name ?? null,
                    'semester' => $result['semester'],
                    'academic_year' => $result['academic_year'],
                    'total_schedules' => $result['schedules_count']
                ]
            ], 200);
        } catch (\Exception $e) {
            if (isset($relativePath) && Storage::disk('local')->exists($relativePath)) {
                Storage::disk('local')->delete($relativePath);
            }

            Log::error('Failed to import schedule', [
                'user_id' => $request->current_user_id ?? null,
                'role' => $request->current_role ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import lịch học',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import hàng loạt (nhiều file)
     * POST /api/admin/schedules/import-batch
     * Role: Admin only
     */
    public function importBatch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|min:1|max:50',
            'files.*' => 'required|file|mimes:xlsx,xls|max:10240'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            if ($currentRole !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ Admin mới có quyền import hàng loạt'
                ], 403);
            }

            $results = [
                'success' => [],
                'failed' => [],
                'warnings' => []
            ];

            $files = $request->file('files');

            foreach ($files as $index => $file) {
                try {
                    $fileName = 'batch_' . time() . '_' . $index . '.xlsx';
                    $relativePath = $file->storeAs('temp', $fileName, 'local');
                    $absolutePath = Storage::disk('local')->path($relativePath);

                    $result = $this->scheduleService->importSchedulesFromExcel($absolutePath);
                    Storage::disk('local')->delete($relativePath);

                    // Kiểm tra sinh viên
                    $student = Student::where('user_code', $result['student_code'])->first();

                    if (!$student) {
                        $results['warnings'][] = [
                            'file_index' => $index + 1,
                            'file_name' => $file->getClientOriginalName(),
                            'student_code' => $result['student_code'],
                            'message' => 'Sinh viên chưa tồn tại trong hệ thống'
                        ];
                    } else {
                        $results['success'][] = [
                            'file_index' => $index + 1,
                            'file_name' => $file->getClientOriginalName(),
                            'student_code' => $result['student_code'],
                            'student_name' => $result['student_name'],
                            'schedules_count' => $result['schedules_count']
                        ];
                    }
                } catch (\Exception $e) {
                    if (isset($relativePath) && Storage::disk('local')->exists($relativePath)) {
                        Storage::disk('local')->delete($relativePath);
                    }

                    $results['failed'][] = [
                        'file_index' => $index + 1,
                        'file_name' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ];
                }
            }

            Log::info('Batch import completed', [
                'admin_id' => $currentUserId,
                'total_files' => count($files),
                'success' => count($results['success']),
                'failed' => count($results['failed']),
                'warnings' => count($results['warnings'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hoàn thành import hàng loạt',
                'summary' => [
                    'total_files' => count($files),
                    'success_count' => count($results['success']),
                    'failed_count' => count($results['failed']),
                    'warning_count' => count($results['warnings'])
                ],
                'details' => $results
            ], 200);
        } catch (\Exception $e) {
            Log::error('Batch import failed', [
                'admin_id' => $request->current_user_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import hàng loạt',
                'error' => $e->getMessage()
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
            'activity_id' => 'required|integer|exists:Activities,activity_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $activity = \App\Models\Activity::find($request->activity_id);

            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy hoạt động'
                ], 404);
            }

            $result = $this->scheduleService->checkScheduleConflict(
                $request->student_id,
                $activity->start_time,
                $activity->end_time
            );

            $result['activity'] = [
                'activity_id' => $activity->activity_id,
                'title' => $activity->title,
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time
            ];

            return response()->json([
                'success' => true,
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            Log::error('Check conflict error', [
                'student_id' => $request->student_id,
                'activity_id' => $request->activity_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi kiểm tra xung đột',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem lịch học sinh viên
     * GET /api/schedules/student/{student_id}
     */
    public function getStudentSchedule(Request $request, $student_id)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            // Kiểm tra quyền
            if (!in_array($currentRole, ['admin', 'advisor', 'student'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem lịch học'
                ], 403);
            }

            // Nếu là student, chỉ xem được lịch của mình
            if ($currentRole === 'student' && $currentUserId != $student_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ có thể xem lịch học của chính mình'
                ], 403);
            }

            $student = Student::with(['class.faculty', 'class.advisor'])->find($student_id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sinh viên'
                ], 404);
            }

            // Nếu là advisor, kiểm tra quyền
            if ($currentRole === 'advisor') {
                if (!$student->class || $student->class->advisor_id != $currentUserId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không phải cố vấn của sinh viên này'
                    ], 403);
                }
            }

            $semesterId = $request->query('semester_id');

            if ($semesterId) {
                // Xem lịch 1 học kỳ cụ thể
                $semester = Semester::find($semesterId);
                if (!$semester) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy học kỳ'
                    ], 404);
                }

                $schedule = $this->db->student_schedules->findOne([
                    'student_code' => strval($student->user_code),
                    'semester' => trim($semester->semester_name),
                    'academic_year' => trim($semester->academic_year)
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'student' => $this->formatStudentInfo($student),
                        'semester' => $this->formatSemesterInfo($semester),
                        'schedule' => $schedule ? $this->formatSchedule($schedule) : null,
                        'has_schedule' => $schedule !== null
                    ]
                ], 200);
            } else {
                // Xem tất cả lịch học
                $cursor = $this->db->student_schedules->find([
                    'student_code' => strval($student->user_code)
                ]);

                $schedules = [];
                foreach ($cursor as $schedule) {
                    $schedules[] = $this->formatSchedule($schedule);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'student' => $this->formatStudentInfo($student),
                        'total_semesters' => count($schedules),
                        'schedules' => $schedules
                    ]
                ], 200);
            }
        } catch (\Exception $e) {
            Log::error('Get student schedule error', [
                'student_id' => $student_id,
                'user_id' => $request->current_user_id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy lịch học',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem lịch học cả lớp
     * GET /api/schedules/class/{class_id}
     */
    public function getClassSchedule(Request $request, $class_id)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            if (!in_array($currentRole, ['admin', 'advisor'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem lịch học lớp'
                ], 403);
            }

            $class = ClassModel::with(['advisor', 'faculty'])->find($class_id);

            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy lớp học'
                ], 404);
            }

            if ($currentRole === 'advisor' && $class->advisor_id != $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không phải cố vấn của lớp này'
                ], 403);
            }

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

            $students = Student::where('class_id', $class_id)
                ->orderBy('user_code')
                ->get();

            if ($students->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'class' => $this->formatClassInfo($class),
                        'semester' => $this->formatSemesterInfo($semester),
                        'summary' => [
                            'total_students' => 0,
                            'students_with_schedule' => 0,
                            'students_without_schedule' => 0
                        ],
                        'students' => []
                    ]
                ], 200);
            }

            $studentCodes = $students->pluck('user_code')->map(fn($code) => strval($code))->toArray();

            $cursor = $this->db->student_schedules->find([
                'student_code' => ['$in' => $studentCodes],
                'semester' => trim($semester->semester_name),
                'academic_year' => trim($semester->academic_year)
            ]);

            $schedules = [];
            foreach ($cursor as $schedule) {
                $schedules[strval($schedule['student_code'])] = $schedule;
            }

            $scheduleData = [];
            foreach ($students as $student) {
                $studentCode = strval($student->user_code);
                $schedule = $schedules[$studentCode] ?? null;

                $scheduleData[] = [
                    'student_id' => $student->student_id,
                    'user_code' => $student->user_code,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'phone_number' => $student->phone_number,
                    'position' => $student->position,
                    'status' => $student->status,
                    'has_schedule' => $schedule !== null,
                    'schedule' => $schedule ? $this->formatSchedule($schedule) : null
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'class' => $this->formatClassInfo($class),
                    'semester' => $this->formatSemesterInfo($semester),
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
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy lịch học lớp',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa lịch học
     * DELETE /api/admin/schedules/student/{student_id}
     */
    public function deleteStudentSchedule(Request $request, $student_id)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            if ($currentRole !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ Admin mới có quyền xóa lịch học'
                ], 403);
            }

            $validator = Validator::make(
                array_merge(['student_id' => $student_id], $request->all()),
                [
                    'student_id' => 'required|integer|exists:Students,student_id',
                    'semester_id' => 'required|integer|exists:Semesters,semester_id'
                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::find($student_id);
            $semester = Semester::find($request->semester_id);

            $result = $this->db->student_schedules->deleteOne([
                'student_code' => strval($student->user_code),
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
            }

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy lịch học để xóa'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Delete schedule error', [
                'student_id' => $student_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa lịch học',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download template
     * GET /api/admin/schedules/download-template
     */
    public function downloadTemplate(Request $request)
    {
        if (!in_array($request->current_role, ['admin', 'advisor'])) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền tải template'
            ], 403);
        }

        try {
            $spreadsheet = $this->scheduleImportService->generateTemplate();

            $fileName = 'Mau_lich_hoc_sinh_vien_' . date('YmdHis') . '.xlsx';
            $tempFile = storage_path('app/temp/' . $fileName);

            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile);

            Log::info('Template downloaded', [
                'user_id' => $request->current_user_id,
                'role' => $request->current_role
            ]);

            // Xóa tất cả output buffer trước khi download file
            if (ob_get_length()) {
                ob_end_clean();
            }

            return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Download template error', [
                'user_id' => $request->current_user_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ========== HELPER METHODS ==========

    private function formatStudentInfo($student)
    {
        return [
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
        ];
    }

    private function formatClassInfo($class)
    {
        return [
            'class_id' => $class->class_id,
            'class_name' => $class->class_name,
            'description' => $class->description,
            'advisor_name' => $class->advisor->full_name ?? null,
            'advisor_email' => $class->advisor->email ?? null,
            'faculty_name' => $class->faculty->unit_name ?? null
        ];
    }

    private function formatSemesterInfo($semester)
    {
        return [
            'semester_id' => $semester->semester_id,
            'semester_name' => $semester->semester_name,
            'academic_year' => $semester->academic_year,
            'start_date' => $semester->start_date,
            'end_date' => $semester->end_date
        ];
    }

    private function formatSchedule($schedule)
    {
        return [
            'semester' => $schedule['semester'] ?? null,
            'academic_year' => $schedule['academic_year'] ?? null,
            'education_type' => $schedule['education_type'] ?? null,
            'major' => $schedule['major'] ?? null,
            'total_schedules' => count($schedule['flat_schedule'] ?? []),
            'flat_schedule' => $this->convertDatesToString($schedule['flat_schedule'] ?? []),
            'updated_at' => $this->formatMongoDate($schedule['updated_at'] ?? null)
        ];
    }

    private function convertDatesToString($data)
    {
        if ($data instanceof \MongoDB\BSON\UTCDateTime) {
            return $data->toDateTime()->format('Y-m-d H:i:s');
        }

        if (is_array($data) || $data instanceof \MongoDB\Model\BSONArray || $data instanceof \MongoDB\Model\BSONDocument) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->convertDatesToString($value);
            }
            return $result;
        }

        if (is_object($data)) {
            return $this->convertDatesToString((array) $data);
        }

        return $data;
    }

    private function formatMongoDate($date)
    {
        if ($date instanceof \MongoDB\BSON\UTCDateTime) {
            return $date->toDateTime()->format('Y-m-d H:i:s');
        }
        return $date;
    }
}
