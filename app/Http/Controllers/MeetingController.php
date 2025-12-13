<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingStudent;
use App\Models\MeetingFeedback;
use App\Models\ClassModel;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use Carbon\Carbon;
use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Log;

class MeetingController extends Controller
{
    /**
     * Lấy danh sách cuộc họp
     * GET /api/meetings
     */
    protected $googleCalendarService;
    public function __construct(GoogleCalendarService $googleCalendarService)
    {
        $this->googleCalendarService = $googleCalendarService;
    }
    public function index(Request $request)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            $query = Meeting::with(['advisor', 'class', 'attendees.student']);

            // Phân quyền lọc dữ liệu
            if ($userRole === 'student') {
                // Sinh viên chỉ xem cuộc họp của lớp mình
                $student = Student::find($userId);
                if (!$student) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy thông tin sinh viên'
                    ], 404);
                }
                $query->where('class_id', $student->class_id);
            } elseif ($userRole === 'advisor') {
                // CVHT chỉ xem cuộc họp của các lớp mình phụ trách
                $query->where('advisor_id', $userId);
            }
            // Admin xem tất cả

            // Lọc thêm theo tham số (nếu có quyền)
            if ($request->has('class_id') && in_array($userRole, ['advisor', 'admin'])) {
                $query->where('class_id', $request->class_id);
            }

            // Lọc theo trạng thái
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Lọc theo thời gian
            if ($request->has('from_date')) {
                $query->where('meeting_time', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->where('meeting_time', '<=', $request->to_date);
            }

            // Sắp xếp
            $query->orderBy('meeting_time', 'desc');

            // Phân trang
            $meetings = $query->get();

            return response()->json([
                'success' => true,
                'data' => $meetings
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách cuộc họp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo cuộc họp mới
     * POST /api/meetings
     * Chỉ CVHT và Admin mới có quyền tạo
     */
    /**
     * Tạo cuộc họp mới với tùy chọn tạo Google Meet tự động
     * POST /api/meetings
     */
    public function store(Request $request)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền tạo cuộc họp'
                ], 403);
            }

            // Validate
            $validator = Validator::make($request->all(), [
                'class_id' => 'required|exists:Classes,class_id',
                'title' => 'required|string|max:255',
                'summary' => 'nullable|string',
                'meeting_link' => 'nullable|url|max:2083',
                'location' => 'nullable|string|max:255',
                'meeting_time' => 'required|date',
                'end_time' => 'nullable|date|after:meeting_time',
                'auto_create_meet' => 'nullable|boolean' // Tham số mới
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Kiểm tra xung đột với các cuộc họp khác
            $endTime = $request->end_time ?: Carbon::parse($request->meeting_time)->addHour();
            $conflictingMeeting = $this->checkMeetingTimeConflict(
                $request->class_id,
                $request->meeting_time,
                $endTime
            );

            if ($conflictingMeeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thời gian họp bị trùng với cuộc họp khác',
                    'conflicting_meeting' => [
                        'meeting_id' => $conflictingMeeting->meeting_id,
                        'title' => $conflictingMeeting->title,
                        'meeting_time' => $conflictingMeeting->meeting_time,
                        'end_time' => $conflictingMeeting->end_time,
                        'status' => $conflictingMeeting->status
                    ]
                ], 422);
            }

            // CVHT chỉ được tạo họp cho lớp mình phụ trách
            if ($userRole === 'advisor') {
                $class = \App\Models\ClassModel::where('class_id', $request->class_id)
                    ->where('advisor_id', $userId)
                    ->first();

                if (!$class) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không phải CVHT của lớp này'
                    ], 403);
                }
            }

            // Tạo cuộc họp trong database
            $meeting = Meeting::create([
                'advisor_id' => $userId,
                'class_id' => $request->class_id,
                'title' => $request->title,
                'summary' => $request->summary,
                'meeting_link' => $request->meeting_link,
                'location' => $request->location,
                'meeting_time' => $request->meeting_time,
                'end_time' => $request->end_time ?: Carbon::parse($request->meeting_time)->addHour(),
                'status' => 'scheduled'
            ]);

            // Lưu meeting_id để sử dụng
            $meetingId = $meeting->meeting_id;

            // Tự động gán cuộc họp cho tất cả sinh viên trong lớp
            $students = Student::where('class_id', $request->class_id)->get();
            foreach ($students as $student) {
                MeetingStudent::create([
                    'meeting_id' => $meeting->meeting_id,
                    'student_id' => $student->student_id,
                    'attended' => false
                ]);
            }

            $googleMeetData = null;

            // Nếu yêu cầu tạo Google Meet tự động
            if ($request->auto_create_meet === true) {
                // Lấy danh sách email sinh viên
                $studentEmails = $students->pluck('email')->filter()->toArray();

                // Tạo mô tả chi tiết
                $description = "Cuộc họp lớp: {$meeting->class->class_name}\n";
                $description .= "ID Hệ thống: {$meetingId}\n\n";
                $description .= $meeting->summary ?? 'Không có mô tả';

                // Gọi service tạo Google Meet
                $result = $this->googleCalendarService->createMeeting(
                    $meetingId,
                    $meeting->title,
                    $description,
                    Carbon::parse($meeting->meeting_time),
                    Carbon::parse($meeting->end_time),
                    $studentEmails
                );

                if ($result['success']) {
                    // Cập nhật meeting_link vào database (không cần thêm cột mới)
                    $meeting->update([
                        'meeting_link' => $result['meet_link']
                    ]);

                    $googleMeetData = [
                        'meet_link' => $result['meet_link'],
                        'calendar_link' => $result['html_link'],
                        'attendees_invited' => $result['attendees_count'],
                        'google_event_id' => $result['event_id']
                    ];
                } else {
                    // Log lỗi nhưng vẫn tạo meeting thành công
                    Log::warning('Failed to create Google Meet: ' . $result['error']);
                }
            }

            // Load relationships
            $meeting->load(['advisor', 'class', 'attendees.student']);

            return response()->json([
                'success' => true,
                'message' => 'Tạo cuộc họp thành công',
                'data' => $meeting,
                'google_meet' => $googleMeetData
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo cuộc họp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem chi tiết cuộc họp
     * GET /api/meetings/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            $meeting = Meeting::with(['advisor', 'class', 'attendees.student', 'feedbacks.student'])
                ->find($id);

            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            // Kiểm tra quyền xem
            if ($userRole === 'student') {
                $student = Student::find($userId);
                if ($student->class_id !== $meeting->class_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem cuộc họp này'
                    ], 403);
                }
            } elseif ($userRole === 'advisor') {
                if ($meeting->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem cuộc họp này'
                    ], 403);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $meeting
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin cuộc họp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật cuộc họp
     * PUT /api/meetings/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền cập nhật cuộc họp'
                ], 403);
            }

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            if ($userRole === 'advisor' && $meeting->advisor_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền sửa cuộc họp này'
                ], 403);
            }

            // Validate
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'summary' => 'nullable|string',
                'meeting_link' => 'nullable|url|max:2083',
                'location' => 'nullable|string|max:255',
                'meeting_time' => 'sometimes|date',
                'end_time' => 'nullable|date|after:meeting_time',
                'status' => 'sometimes|in:scheduled,completed,cancelled',
                'sync_to_google' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Kiểm tra xung đột nếu có thay đổi thời gian
            if ($request->has('meeting_time') || $request->has('end_time')) {
                $newMeetingTime = $request->meeting_time ?? $meeting->meeting_time;
                $newEndTime = $request->end_time ?? $meeting->end_time;

                $conflictingMeeting = $this->checkMeetingTimeConflict(
                    $meeting->class_id,
                    $newMeetingTime,
                    $newEndTime,
                    $meeting->meeting_id  // Loại trừ meeting hiện tại
                );

                if ($conflictingMeeting) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Thời gian họp mới bị trùng với cuộc họp khác',
                        'conflicting_meeting' => [
                            'meeting_id' => $conflictingMeeting->meeting_id,
                            'title' => $conflictingMeeting->title,
                            'meeting_time' => $conflictingMeeting->meeting_time,
                            'end_time' => $conflictingMeeting->end_time,
                            'status' => $conflictingMeeting->status
                        ]
                    ], 422);
                }
            }

            // Cập nhật database
            $meeting->update($request->only([
                'title',
                'summary',
                'meeting_link',
                'location',
                'meeting_time',
                'end_time',
                'status'
            ]));

            // Đồng bộ với Google Calendar nếu có yêu cầu
            if ($request->sync_to_google === true && $meeting->meeting_link) {
                // Kiểm tra xem link có phải là Google Meet không
                if (strpos($meeting->meeting_link, 'meet.google.com') !== false) {
                    $students = Student::where('class_id', $meeting->class_id)->get();
                    $studentEmails = $students->pluck('email')->filter()->toArray();

                    $result = $this->googleCalendarService->updateMeeting(
                        $meeting->meeting_id,
                        $request->title,
                        $request->summary,
                        $request->meeting_time ? Carbon::parse($request->meeting_time) : null,
                        $request->end_time ? Carbon::parse($request->end_time) : null,
                        $studentEmails
                    );

                    if (!$result['success']) {
                        Log::warning('Failed to update Google Meet: ' . $result['error']);
                    }
                }
            }

            $meeting->load(['advisor', 'class', 'attendees.student']);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật cuộc họp thành công',
                'data' => $meeting
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật cuộc họp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa cuộc họp
     * DELETE /api/meetings/{id}
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa cuộc họp'
                ], 403);
            }

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            if ($userRole === 'advisor' && $meeting->advisor_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa cuộc họp này'
                ], 403);
            }

            // Xóa trên Google Calendar nếu có Google Meet link
            if ($meeting->meeting_link && strpos($meeting->meeting_link, 'meet.google.com') !== false) {
                $result = $this->googleCalendarService->deleteMeeting($meeting->meeting_id);
                if (!$result['success']) {
                    Log::warning('Failed to delete Google Meet: ' . $result['error']);
                }
            }

            // Xóa biên bản nếu có
            if ($meeting->minutes_file_path && Storage::exists('public/' . $meeting->minutes_file_path)) {
                Storage::delete('public/' . $meeting->minutes_file_path);
            }

            $meeting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa cuộc họp thành công'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa cuộc họp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kiểm tra xung đột thời gian với các cuộc họp khác
     * 
     * @param int $classId ID của lớp
     * @param string $meetingTime Thời gian bắt đầu họp mới
     * @param string $endTime Thời gian kết thúc họp mới
     * @param int|null $excludeMeetingId ID của meeting cần loại trừ (khi update)
     * @return Meeting|null Trả về meeting bị conflict hoặc null nếu không có conflict
     */
    private function checkMeetingTimeConflict($classId, $meetingTime, $endTime, $excludeMeetingId = null)
    {
        // Query meetings của cùng lớp
        $query = Meeting::where('class_id', $classId)
            ->where('status', '!=', 'cancelled');

        // Loại trừ meeting hiện tại nếu đang update
        if ($excludeMeetingId) {
            $query->where('meeting_id', '!=', $excludeMeetingId);
        }

        // Kiểm tra overlap: hai khoảng thời gian [A_start, A_end] và [B_start, B_end] overlap khi:
        // A_start < B_end AND A_end > B_start
        $conflictingMeeting = $query->where(function ($q) use ($meetingTime, $endTime) {
            $q->where(function ($subQ) use ($meetingTime, $endTime) {
                // Trường hợp 1: Meeting mới bắt đầu trong khoảng meeting cũ
                $subQ->where('meeting_time', '<=', $meetingTime)
                    ->where('end_time', '>', $meetingTime);
            })->orWhere(function ($subQ) use ($meetingTime, $endTime) {
                // Trường hợp 2: Meeting mới kết thúc trong khoảng meeting cũ
                $subQ->where('meeting_time', '<', $endTime)
                    ->where('end_time', '>=', $endTime);
            })->orWhere(function ($subQ) use ($meetingTime, $endTime) {
                // Trường hợp 3: Meeting mới bao trùm meeting cũ
                $subQ->where('meeting_time', '>=', $meetingTime)
                    ->where('end_time', '<=', $endTime);
            });
        })->first();

        return $conflictingMeeting;
    }

    /**
     * Điểm danh sinh viên
     * POST /api/meetings/{id}/attendance
     * Chỉ CVHT và Admin mới có quyền điểm danh
     */
    public function updateAttendance(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            // Kiểm tra quyền
            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền điểm danh'
                ], 403);
            }

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            // CVHT chỉ điểm danh cuộc họp của mình
            if ($userRole === 'advisor' && $meeting->advisor_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền điểm danh cuộc họp này'
                ], 403);
            }

            // Validate
            $validator = Validator::make($request->all(), [
                'attendances' => 'required|array',
                'attendances.*.student_id' => 'required|integer',
                'attendances.*.attended' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Lấy danh sách student_id hợp lệ trong cuộc họp này
            $validStudentIds = MeetingStudent::where('meeting_id', $id)
                ->pluck('student_id')
                ->toArray();

            // Cập nhật điểm danh
            $invalidStudents = [];
            foreach ($request->attendances as $attendance) {
                $studentId = $attendance['student_id'];

                // Kiểm tra sinh viên có thuộc cuộc họp này không
                if (!in_array($studentId, $validStudentIds)) {
                    $invalidStudents[] = $studentId;
                    continue;
                }

                // Cập nhật điểm danh
                MeetingStudent::where('meeting_id', $id)
                    ->where('student_id', $studentId)
                    ->update(['attended' => $attendance['attended']]);
            }

            // Nếu có sinh viên không hợp lệ, trả về cảnh báo
            if (!empty($invalidStudents)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Một số sinh viên không thuộc cuộc họp này',
                    'invalid_student_ids' => $invalidStudents
                ], 422);
            }

            // Cập nhật trạng thái cuộc họp
            if ($meeting->status === 'scheduled') {
                $meeting->update(['status' => 'completed']);
            }

            $meeting->load(['attendees.student']);

            return response()->json([
                'success' => true,
                'message' => 'Điểm danh thành công',
                'data' => $meeting
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi điểm danh: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xuất biên bản họp lớp
     * GET /api/meetings/{id}/export-minutes
     * Chỉ CVHT và Admin mới có quyền xuất biên bản
     */
    public function exportMinutes(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            // Kiểm tra quyền
            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xuất biên bản họp'
                ], 403);
            }

            $meeting = Meeting::with([
                'advisor',
                'class.faculty',
                'class.students' => function ($query) {
                    $query->whereIn('position', ['leader', 'vice_leader', 'secretary']);
                },
                'attendees.student',
                'feedbacks.student' // Load feedbacks từ sinh viên
            ])->find($id);

            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            // CVHT chỉ xuất biên bản cuộc họp của mình
            if ($userRole === 'advisor' && $meeting->advisor_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xuất biên bản cuộc họp này'
                ], 403);
            }

            // Kiểm tra file template
            $templatePath = storage_path('app/templates/meeting_minutes_template.docx');
            if (!file_exists($templatePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy file template biên bản'
                ], 500);
            }

            // Load template
            $templateProcessor = new TemplateProcessor($templatePath);

            // Lấy thông tin lớp trưởng và lớp phó (kiêm bí thư)
            $leader = $meeting->class->students->where('position', 'leader')->first();
            $viceLeader = $meeting->class->students->where('position', 'vice_leader')->first();

            // Đếm số sinh viên tham dự
            $attendedCount = $meeting->attendees->where('attended', true)->count();
            $totalStudents = $meeting->class->students()->count();

            // Parse thời gian họp
            $meetingTime = Carbon::parse($meeting->meeting_time);
            $endTime = $meeting->end_time ? Carbon::parse($meeting->end_time) : null;

            // Lấy tên khoa
            $facultyName = $meeting->class->faculty ? $meeting->class->faculty->unit_name : '';

            // Thay thế các biến trong template
            $templateProcessor->setValue('FACULTY_NAME', mb_strtoupper($facultyName, 'UTF-8'));
            $templateProcessor->setValue('HOUR', $meetingTime->format('H'));
            $templateProcessor->setValue('MINUTE', $meetingTime->format('i'));
            $templateProcessor->setValue('DAY', $meetingTime->day);
            $templateProcessor->setValue('MONTH', $meetingTime->month);
            $templateProcessor->setValue('YEAR', $meetingTime->year);

            // Địa điểm
            $location = $meeting->location ?: 'Họp Online';
            if ($meeting->meeting_link && strpos($meeting->location, 'Online') === false) {
                $location = 'Họp Online trên ' . $this->extractPlatformName($meeting->meeting_link);
            }
            $templateProcessor->setValue('LOCATION', $location);

            // Thông tin GVCV
            $templateProcessor->setValue('ADVISOR_NAME', $meeting->advisor->full_name);
            $templateProcessor->setValue('CLASS_NAME', $meeting->class->class_name);

            // Thông tin ban cán sự lớp
            $templateProcessor->setValue('LEADER_NAME', $leader ? $leader->full_name : '................................');
            $templateProcessor->setValue('VICE_LEADER_NAME', $viceLeader ? $viceLeader->full_name : '................................');

            // Số lượng sinh viên
            $templateProcessor->setValue('ATTENDED_COUNT', $attendedCount);
            $templateProcessor->setValue('TOTAL_COUNT', $totalStudents);

            // Nội dung họp
            $summary = $meeting->summary ?: 'Nội dung họp chưa được cập nhật.';
            $templateProcessor->setValue('MEETING_SUMMARY', $summary);

            // Ý kiến đóng góp của lớp - lấy từ tất cả feedback của sinh viên
            $feedbacks = $meeting->feedbacks;
            if ($feedbacks->isEmpty()) {
                $classFeedback = 'Lớp không có ý kiến.';
            } else {
                $feedbackList = [];
                foreach ($feedbacks as $index => $feedback) {
                    $feedbackList[] = ($index + 1) . '. ' . $feedback->student->full_name . ': ' . $feedback->feedback_content;
                }
                $classFeedback = implode("\n", $feedbackList);
            }
            $templateProcessor->setValue('CLASS_FEEDBACK', $classFeedback);

            // Thời gian kết thúc
            if ($endTime) {
                $templateProcessor->setValue('END_HOUR', $endTime->format('H'));
                $templateProcessor->setValue('END_MINUTE', $endTime->format('i'));
            } else {
                $templateProcessor->setValue('END_HOUR', '......');
                $templateProcessor->setValue('END_MINUTE', '......');
            }

            // Tên file output
            $fileName = 'BienBan_' . $meeting->class->class_name . '_' . $meetingTime->format('dmY') . '.docx';
            $outputPath = storage_path('app/public/meetings/' . $fileName);

            // Tạo thư mục nếu chưa có
            $directory = dirname($outputPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Lưu file
            $templateProcessor->saveAs($outputPath);

            // Cập nhật đường dẫn biên bản vào database
            $relativePath = 'meetings/' . $fileName;
            $meeting->update(['minutes_file_path' => $relativePath]);

            // Xóa tất cả output buffer trước khi download file
            if (ob_get_length()) {
                ob_end_clean();
            }

            // Trả về file để download
            return response()->download($outputPath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])->deleteFileAfterSend(false);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất biên bản: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tải file biên bản đã lưu
     * GET /api/meetings/{id}/download-minutes
     */
    public function downloadMinutes(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            // Kiểm tra quyền xem
            if ($userRole === 'student') {
                $student = Student::find($userId);
                if ($student->class_id !== $meeting->class_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền tải biên bản này'
                    ], 403);
                }
            } elseif ($userRole === 'advisor') {
                if ($meeting->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền tải biên bản này'
                    ], 403);
                }
            }

            // Kiểm tra file có tồn tại không
            if (!$meeting->minutes_file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Biên bản chưa được tạo'
                ], 404);
            }

            $filePath = storage_path('app/public/' . $meeting->minutes_file_path);
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy file biên bản'
                ], 404);
            }

            // Xóa tất cả output buffer trước khi download file
            if (ob_get_length()) {
                ob_end_clean();
            }

            $fileName = basename($filePath);
            return response()->download($filePath, $fileName);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải biên bản: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload biên bản họp thủ công
     * POST /api/meetings/{id}/upload-minutes
     * Chỉ CVHT và Admin mới có quyền upload
     */
    public function uploadMinutes(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            // Kiểm tra quyền
            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền upload biên bản'
                ], 403);
            }

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            // CVHT chỉ upload cho cuộc họp của mình
            if ($userRole === 'advisor' && $meeting->advisor_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền upload biên bản cho cuộc họp này'
                ], 403);
            }

            // Validate
            $validator = Validator::make($request->all(), [
                'minutes_file' => 'required|file|mimes:doc,docx,pdf|max:10240' // Max 10MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Xóa file cũ nếu có
            if ($meeting->minutes_file_path && Storage::exists('public/' . $meeting->minutes_file_path)) {
                Storage::delete('public/' . $meeting->minutes_file_path);
            }

            // Lưu file mới
            $file = $request->file('minutes_file');
            $fileName = 'BienBan_' . $meeting->class->class_name . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('meetings', $fileName, 'public');

            // Cập nhật database
            $meeting->update(['minutes_file_path' => $filePath]);

            return response()->json([
                'success' => true,
                'message' => 'Upload biên bản thành công',
                'data' => [
                    'file_path' => $filePath,
                    'file_url' => Storage::url($filePath)
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi upload biên bản: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa biên bản
     * DELETE /api/meetings/{id}/minutes
     */
    public function deleteMinutes(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa biên bản'
                ], 403);
            }

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            if ($userRole === 'advisor' && $meeting->advisor_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa biên bản này'
                ], 403);
            }

            if (!$meeting->minutes_file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có biên bản để xóa'
                ], 404);
            }

            // Xóa file
            if (Storage::exists('public/' . $meeting->minutes_file_path)) {
                Storage::delete('public/' . $meeting->minutes_file_path);
            }

            // Xóa đường dẫn trong database
            $meeting->update(['minutes_file_path' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa biên bản thành công'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa biên bản: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật nội dung họp và ý kiến lớp
     * PUT /api/meetings/{id}/summary
     */
    public function updateSummary(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền cập nhật nội dung họp'
                ], 403);
            }

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            if ($userRole === 'advisor' && $meeting->advisor_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền cập nhật cuộc họp này'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'summary' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $meeting->update($request->only(['summary']));

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật nội dung họp thành công',
                'data' => $meeting
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sinh viên gửi feedback về cuộc họp
     * POST /api/meetings/{id}/feedbacks
     */
    public function storeFeedback(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            // Chỉ sinh viên mới được gửi feedback
            if ($userRole !== 'student') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ sinh viên mới có thể gửi feedback'
                ], 403);
            }

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            // Kiểm tra sinh viên có thuộc lớp không
            $student = Student::find($userId);
            if ($student->class_id !== $meeting->class_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không thuộc lớp này'
                ], 403);
            }

            // Validate
            $validator = Validator::make($request->all(), [
                'feedback_content' => 'required|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nội dung feedback không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Tạo feedback
            $feedback = MeetingFeedback::create([
                'meeting_id' => $id,
                'student_id' => $userId,
                'feedback_content' => $request->feedback_content
            ]);

            $feedback->load('student');

            return response()->json([
                'success' => true,
                'message' => 'Gửi feedback thành công',
                'data' => $feedback
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi gửi feedback: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem danh sách feedback của cuộc họp
     * GET /api/meetings/{id}/feedbacks
     */
    public function getFeedbacks(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            // Kiểm tra quyền xem
            if ($userRole === 'student') {
                $student = Student::find($userId);
                if ($student->class_id !== $meeting->class_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem feedback'
                    ], 403);
                }
            } elseif ($userRole === 'advisor') {
                if ($meeting->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem feedback'
                    ], 403);
                }
            }

            $feedbacks = MeetingFeedback::where('meeting_id', $id)
                ->with('student')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $feedbacks
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách feedback: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Thống kê cuộc họp
     * GET /api/meetings/statistics/overview
     */
    public function getStatistics(Request $request)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            $query = Meeting::query();

            // Phân quyền
            if ($userRole === 'advisor') {
                $query->where('advisor_id', $userId);
            }

            // Lọc theo thời gian
            if ($request->has('from_date')) {
                $query->where('meeting_time', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->where('meeting_time', '<=', $request->to_date);
            }

            // Lọc theo lớp
            if ($request->has('class_id')) {
                $query->where('class_id', $request->class_id);
            }

            // Thống kê theo trạng thái
            $totalMeetings = $query->count();
            $scheduledMeetings = (clone $query)->where('status', 'scheduled')->count();
            $completedMeetings = (clone $query)->where('status', 'completed')->count();
            $cancelledMeetings = (clone $query)->where('status', 'cancelled')->count();

            // Thống kê biên bản
            $meetingsWithMinutes = (clone $query)->whereNotNull('minutes_file_path')->count();

            // Thống kê điểm danh
            $attendanceStats = MeetingStudent::whereIn(
                'meeting_id',
                (clone $query)->pluck('meeting_id')
            )->selectRaw('
                COUNT(*) as total_attendees,
                SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended_count,
                AVG(CASE WHEN attended = 1 THEN 1 ELSE 0 END) * 100 as attendance_rate
            ')->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_meetings' => $totalMeetings,
                    'scheduled' => $scheduledMeetings,
                    'completed' => $completedMeetings,
                    'cancelled' => $cancelledMeetings,
                    'with_minutes' => $meetingsWithMinutes,
                    'attendance' => [
                        'total_attendees' => $attendanceStats->total_attendees ?? 0,
                        'attended_count' => $attendanceStats->attended_count ?? 0,
                        'attendance_rate' => round($attendanceStats->attendance_rate ?? 0, 2)
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thống kê: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper function: Trích xuất tên nền tảng từ link họp
     */
    private function extractPlatformName($link)
    {
        if (strpos($link, 'meet.google.com') !== false) {
            return 'Google Meet';
        } elseif (strpos($link, 'zoom.us') !== false) {
            return 'Zoom';
        } elseif (strpos($link, 'teams.microsoft.com') !== false) {
            return 'Microsoft Teams';
        }
        return 'Platform Online';
    }
    /**
     * Kiểm tra trạng thái phản hồi từ Google Calendar
     * GET /api/meetings/{id}/google-attendance
     */
    public function checkGoogleAttendance(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem trạng thái này'
                ], 403);
            }

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            if ($userRole === 'advisor' && $meeting->advisor_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem cuộc họp này'
                ], 403);
            }

            if (!$meeting->meeting_link || strpos($meeting->meeting_link, 'meet.google.com') === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cuộc họp này không có Google Meet'
                ], 400);
            }

            // Lấy trạng thái từ Google Calendar
            $result = $this->googleCalendarService->getAttendanceStatus($meeting->meeting_id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi khi lấy dữ liệu từ Google Calendar',
                    'error' => $result['error']
                ], 500);
            }

            // Map email với thông tin sinh viên
            $students = Student::where('class_id', $meeting->class_id)->get()->keyBy('email');

            $attendanceData = collect($result['attendees'])->map(function ($attendee) use ($students) {
                $student = $students->get($attendee['email']);

                return [
                    'email' => $attendee['email'],
                    'student_id' => $student ? $student->student_id : null,
                    'student_name' => $student ? $student->full_name : $attendee['display_name'],
                    'response_status' => $attendee['response_status'],
                    'status_text' => $this->getStatusText($attendee['response_status']),
                    'comment' => $attendee['comment']
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'meeting_id' => $meeting->meeting_id,
                    'meeting_title' => $meeting->title,
                    'attendees' => $attendanceData,
                    'summary' => $result['summary'],
                    'event_link' => $result['event_link']
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi kiểm tra điểm danh: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Đồng bộ điểm danh từ Google Calendar về hệ thống
     * POST /api/meetings/{id}/sync-google-attendance
     */
    public function syncGoogleAttendance(Request $request, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền thực hiện đồng bộ'
                ], 403);
            }

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy cuộc họp'
                ], 404);
            }

            if ($userRole === 'advisor' && $meeting->advisor_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền đồng bộ cuộc họp này'
                ], 403);
            }

            // Lấy trạng thái từ Google
            $result = $this->googleCalendarService->getAttendanceStatus($meeting->meeting_id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể lấy dữ liệu từ Google Calendar'
                ], 500);
            }

            // Map email với student_id
            $students = Student::where('class_id', $meeting->class_id)->get()->keyBy('email');

            $syncedCount = 0;
            foreach ($result['attendees'] as $attendee) {
                $student = $students->get($attendee['email']);

                if ($student) {
                    // Chỉ coi là có mặt nếu status là 'accepted'
                    $attended = ($attendee['response_status'] === 'accepted');

                    MeetingStudent::where('meeting_id', $meeting->meeting_id)
                        ->where('student_id', $student->student_id)
                        ->update(['attended' => $attended]);

                    $syncedCount++;
                }
            }

            // Cập nhật trạng thái meeting
            if ($meeting->status === 'scheduled') {
                $meeting->update(['status' => 'completed']);
            }

            return response()->json([
                'success' => true,
                'message' => "Đã đồng bộ điểm danh cho {$syncedCount} sinh viên",
                'data' => [
                    'synced_count' => $syncedCount,
                    'accepted' => $result['summary']['accepted'],
                    'declined' => $result['summary']['declined'],
                    'tentative' => $result['summary']['tentative'],
                    'no_response' => $result['summary']['needsAction']
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi đồng bộ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Chuyển đổi response status sang text tiếng Việt
     */
    private function getStatusText($status)
    {
        $statusMap = [
            'accepted' => 'Đã chấp nhận',
            'declined' => 'Từ chối',
            'tentative' => 'Chưa chắc chắn',
            'needsAction' => 'Chưa phản hồi'
        ];

        return $statusMap[$status] ?? 'Không xác định';
    }
}
