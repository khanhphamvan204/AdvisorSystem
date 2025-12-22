<?php

namespace App\Http\Controllers;

use App\Models\NotificationResponse;
use App\Models\Notification;
use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Advisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\ExcelHeaderService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class DialogueController extends Controller
{
    /**
     * Lấy danh sách ý kiến đối thoại
     * GET /api/dialogues
     * 
     * Nguồn ý kiến:
     * - meeting_feedbacks: Feedback từ cuộc họp lớp
     * - notification_responses: Phản hồi từ thông báo
     * 
     * Phân quyền:
     * - Admin: Xem tất cả ý kiến của các lớp trong khoa mình quản lý
     * - Advisor: Xem ý kiến của các lớp mình phụ trách
     * - Student: Xem ý kiến của chính mình
     */
    public function index(Request $request)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            // Xác định nguồn ý kiến (mặc định: tất cả)
            $source = $request->get('source', 'all'); // all, meeting, notification

            $dialogues = collect();

            // Lấy ý kiến từ cuộc họp
            if (in_array($source, ['all', 'meeting'])) {
                $meetingFeedbacks = $this->getMeetingFeedbacks($userId, $userRole, $request);
                $dialogues = $dialogues->merge($meetingFeedbacks);
            }

            // Lấy ý kiến từ thông báo
            if (in_array($source, ['all', 'notification'])) {
                $notificationResponses = $this->getNotificationResponses($userId, $userRole, $request);
                $dialogues = $dialogues->merge($notificationResponses);
            }

            // Sắp xếp theo thời gian
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            $dialogues = $dialogues->sortBy($sortBy, SORT_REGULAR, $sortOrder === 'desc')->values();

            return response()->json([
                'success' => true,
                'data' => $dialogues
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách ý kiến: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy ý kiến từ Meeting Feedbacks
     */
    private function getMeetingFeedbacks($userId, $userRole, Request $request)
    {
        $query = \App\Models\MeetingFeedback::with([
            'meeting.class.faculty',
            'meeting.advisor',
            'student.class'
        ]);

        // Phân quyền theo role
        if ($userRole === 'student') {
            $query->where('student_id', $userId);
        } elseif ($userRole === 'advisor') {
            $query->whereHas('meeting', function ($q) use ($userId) {
                $q->where('advisor_id', $userId);
            });
        } elseif ($userRole === 'admin') {
            $advisor = Advisor::find($userId);
            if ($advisor && $advisor->unit_id) {
                $classIds = ClassModel::where('faculty_id', $advisor->unit_id)->pluck('class_id');
                $query->whereHas('meeting', function ($q) use ($classIds) {
                    $q->whereIn('class_id', $classIds);
                });
            }
        }

        // Lọc theo lớp
        if ($request->has('class_id')) {
            $query->whereHas('meeting', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }

        // Lọc theo thời gian
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        // Tìm kiếm theo từ khóa
        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->where('feedback_content', 'like', "%{$keyword}%");
        }

        $feedbacks = $query->get()->map(function ($feedback) {
            return [
                'id' => $feedback->feedback_id,
                'source' => 'meeting',
                'source_id' => $feedback->meeting_id,
                'source_title' => $feedback->meeting->title ?? 'N/A',
                'student_id' => $feedback->student_id,
                'student_name' => $feedback->student->full_name ?? 'N/A',
                'student_code' => $feedback->student->user_code ?? 'N/A',
                'class_id' => $feedback->student->class_id ?? null,
                'class_name' => $feedback->student->class->class_name ?? 'N/A',
                'faculty_name' => $feedback->student->class->faculty->unit_name ?? 'N/A',
                'content' => $feedback->feedback_content,
                'advisor_response' => null, // Meeting feedback không có trường này
                'advisor_name' => $feedback->meeting->advisor->full_name ?? 'N/A',
                'status' => 'pending', // Mặc định là pending
                'created_at' => $feedback->created_at,
                'response_at' => null,
                'full_data' => $feedback
            ];
        });

        return $feedbacks;
    }

    /**
     * Lấy ý kiến từ Notification Responses
     */
    private function getNotificationResponses($userId, $userRole, Request $request)
    {
        $query = NotificationResponse::with([
            'notification',
            'student.class.faculty',
            'advisor'
        ]);

        // Phân quyền theo role
        if ($userRole === 'student') {
            $query->where('student_id', $userId);
        } elseif ($userRole === 'advisor') {
            $classIds = ClassModel::where('advisor_id', $userId)->pluck('class_id');
            $query->whereHas('student', function ($q) use ($classIds) {
                $q->whereIn('class_id', $classIds);
            });
        } elseif ($userRole === 'admin') {
            $advisor = Advisor::find($userId);
            if ($advisor && $advisor->unit_id) {
                $classIds = ClassModel::where('faculty_id', $advisor->unit_id)->pluck('class_id');
                $query->whereHas('student', function ($q) use ($classIds) {
                    $q->whereIn('class_id', $classIds);
                });
            }
        }

        // Lọc theo lớp
        if ($request->has('class_id')) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }

        // Lọc theo trạng thái
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Lọc theo loại thông báo
        if ($request->has('notification_type')) {
            $query->whereHas('notification', function ($q) use ($request) {
                $q->where('type', $request->notification_type);
            });
        }

        // Lọc theo thời gian
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        // Tìm kiếm theo từ khóa
        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('content', 'like', "%{$keyword}%")
                    ->orWhere('advisor_response', 'like', "%{$keyword}%");
            });
        }

        $responses = $query->get()->map(function ($response) {
            return [
                'id' => $response->response_id,
                'source' => 'notification',
                'source_id' => $response->notification_id,
                'source_title' => $response->notification->title ?? 'N/A',
                'student_id' => $response->student_id,
                'student_name' => $response->student->full_name ?? 'N/A',
                'student_code' => $response->student->user_code ?? 'N/A',
                'class_id' => $response->student->class_id ?? null,
                'class_name' => $response->student->class->class_name ?? 'N/A',
                'faculty_name' => $response->student->class->faculty->unit_name ?? 'N/A',
                'content' => $response->content,
                'advisor_response' => $response->advisor_response,
                'advisor_name' => $response->advisor->full_name ?? 'Chưa phản hồi',
                'status' => $response->status,
                'created_at' => $response->created_at,
                'response_at' => $response->response_at,
                'full_data' => $response
            ];
        });

        return $responses;
    }

    /**
     * Xem chi tiết một ý kiến đối thoại
     * GET /api/dialogues/{source}/{id}
     * source: meeting hoặc notification
     */
    public function show(Request $request, $source, $id)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            if (!in_array($source, ['meeting', 'notification'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nguồn không hợp lệ. Sử dụng: meeting hoặc notification'
                ], 400);
            }

            if ($source === 'meeting') {
                $dialogue = \App\Models\MeetingFeedback::with([
                    'meeting.class.faculty',
                    'meeting.advisor',
                    'student.class'
                ])->find($id);

                if (!$dialogue) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy ý kiến'
                    ], 404);
                }

                // Kiểm tra quyền xem
                if ($userRole === 'student' && $dialogue->student_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem ý kiến này'
                    ], 403);
                } elseif ($userRole === 'advisor') {
                    if ($dialogue->meeting->advisor_id !== $userId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem ý kiến này'
                        ], 403);
                    }
                } elseif ($userRole === 'admin') {
                    $advisor = Advisor::find($userId);
                    if ($dialogue->meeting->class->faculty_id !== $advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem ý kiến này'
                        ], 403);
                    }
                }

                $response = [
                    'source' => 'meeting',
                    'feedback_id' => $dialogue->feedback_id,
                    'meeting' => $dialogue->meeting,
                    'student' => $dialogue->student,
                    'content' => $dialogue->feedback_content,
                    'created_at' => $dialogue->created_at
                ];
            } else { // notification
                $dialogue = NotificationResponse::with([
                    'notification.attachments',
                    'student.class.faculty',
                    'advisor'
                ])->find($id);

                if (!$dialogue) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy ý kiến'
                    ], 404);
                }

                // Kiểm tra quyền xem
                if ($userRole === 'student' && $dialogue->student_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem ý kiến này'
                    ], 403);
                } elseif ($userRole === 'advisor') {
                    $classIds = ClassModel::where('advisor_id', $userId)->pluck('class_id');
                    if (!$classIds->contains($dialogue->student->class_id)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem ý kiến này'
                        ], 403);
                    }
                } elseif ($userRole === 'admin') {
                    $advisor = Advisor::find($userId);
                    if ($dialogue->student->class->faculty_id !== $advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn không có quyền xem ý kiến này'
                        ], 403);
                    }
                }

                $response = [
                    'source' => 'notification',
                    'response_id' => $dialogue->response_id,
                    'notification' => $dialogue->notification,
                    'student' => $dialogue->student,
                    'content' => $dialogue->content,
                    'advisor_response' => $dialogue->advisor_response,
                    'advisor' => $dialogue->advisor,
                    'status' => $dialogue->status,
                    'created_at' => $dialogue->created_at,
                    'response_at' => $dialogue->response_at
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $response
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy chi tiết ý kiến: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Thống kê tổng hợp ý kiến đối thoại
     * GET /api/dialogues/statistics/overview
     */
    public function getStatistics(Request $request)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem thống kê'
                ], 403);
            }

            // Lấy dữ liệu từ cả 2 nguồn
            $meetingFeedbacks = $this->getMeetingFeedbacks($userId, $userRole, $request);
            $notificationResponses = $this->getNotificationResponses($userId, $userRole, $request);

            // Tổng hợp
            $totalMeeting = $meetingFeedbacks->count();
            $totalNotification = $notificationResponses->count();
            $totalAll = $totalMeeting + $totalNotification;

            // Thống kê Notification Response
            $pendingNotification = $notificationResponses->where('status', 'pending')->count();
            $resolvedNotification = $notificationResponses->where('status', 'resolved')->count();
            $respondedNotification = $notificationResponses->whereNotNull('advisor_response')->count();

            // Thống kê theo nguồn
            $bySource = [
                ['source' => 'meeting', 'count' => $totalMeeting, 'percentage' => $totalAll > 0 ? round(($totalMeeting / $totalAll) * 100, 2) : 0],
                ['source' => 'notification', 'count' => $totalNotification, 'percentage' => $totalAll > 0 ? round(($totalNotification / $totalAll) * 100, 2) : 0]
            ];

            // Thống kê theo lớp
            $allDialogues = $meetingFeedbacks->merge($notificationResponses);
            $byClass = $allDialogues->groupBy('class_id')->map(function ($items, $classId) {
                $first = $items->first();
                return [
                    'class_id' => $classId,
                    'class_name' => $first['class_name'] ?? 'N/A',
                    'total' => $items->count(),
                    'from_meeting' => $items->where('source', 'meeting')->count(),
                    'from_notification' => $items->where('source', 'notification')->count()
                ];
            })->values();

            // Top sinh viên có nhiều ý kiến
            $topStudents = $allDialogues->groupBy('student_id')->map(function ($items, $studentId) {
                $first = $items->first();
                return [
                    'student_id' => $studentId,
                    'student_name' => $first['student_name'] ?? 'N/A',
                    'student_code' => $first['student_code'] ?? 'N/A',
                    'dialogue_count' => $items->count(),
                    'from_meeting' => $items->where('source', 'meeting')->count(),
                    'from_notification' => $items->where('source', 'notification')->count()
                ];
            })->sortByDesc('dialogue_count')->take(10)->values();

            // Xu hướng 7 ngày
            $trend = $allDialogues->filter(function ($item) {
                return $item['created_at'] >= now()->subDays(7);
            })->groupBy(function ($item) {
                return $item['created_at']->format('Y-m-d');
            })->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'count' => $items->count(),
                    'meeting' => $items->where('source', 'meeting')->count(),
                    'notification' => $items->where('source', 'notification')->count()
                ];
            })->sortBy('date')->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_all' => $totalAll,
                        'total_meeting' => $totalMeeting,
                        'total_notification' => $totalNotification,
                        'notification_pending' => $pendingNotification,
                        'notification_resolved' => $resolvedNotification,
                        'notification_responded' => $respondedNotification,
                        'notification_response_rate' => $totalNotification > 0
                            ? round(($respondedNotification / $totalNotification) * 100, 2)
                            : 0
                    ],
                    'by_source' => $bySource,
                    'by_class' => $byClass,
                    'top_students' => $topStudents,
                    'trend_7_days' => $trend
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
     * Báo cáo chi tiết ý kiến đối thoại theo lớp
     * GET /api/dialogues/reports/by-class
     */
    public function getReportByClass(Request $request)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem báo cáo'
                ], 403);
            }

            // Validate
            $validator = Validator::make($request->all(), [
                'class_id' => 'required|exists:Classes,class_id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Kiểm tra quyền xem lớp
            $class = ClassModel::find($request->class_id);

            if ($userRole === 'advisor') {
                if ($class->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem báo cáo lớp này'
                    ], 403);
                }
            } elseif ($userRole === 'admin') {
                $advisor = Advisor::find($userId);
                if ($class->faculty_id !== $advisor->unit_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Lớp này không thuộc khoa bạn quản lý'
                    ], 403);
                }
            }

            // Lấy ý kiến từ cả 2 nguồn
            $allDialogues = collect();

            // Lấy meeting feedbacks
            $meetingQuery = \App\Models\MeetingFeedback::with([
                'meeting.class',
                'student'
            ])->whereHas('student', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });

            // Lọc theo thời gian cho meeting
            if ($request->filled('from_date')) {
                $meetingQuery->where('created_at', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $meetingQuery->where('created_at', '<=', $request->to_date);
            }

            $meetingFeedbacks = $meetingQuery->get()->map(function ($feedback) {
                return [
                    'id' => $feedback->feedback_id,
                    'source' => 'meeting',
                    'source_id' => $feedback->meeting_id,
                    'source_title' => $feedback->meeting->title ?? 'N/A',
                    'student_id' => $feedback->student_id,
                    'student_name' => $feedback->student->full_name ?? 'N/A',
                    'student_code' => $feedback->student->user_code ?? 'N/A',
                    'content' => $feedback->feedback_content,
                    'advisor_response' => null,
                    'status' => 'pending',
                    'created_at' => $feedback->created_at,
                    'response_at' => null,
                    'student' => $feedback->student,
                    'meeting' => $feedback->meeting
                ];
            });

            // Lấy notification responses
            $notificationQuery = NotificationResponse::with([
                'student',
                'notification',
                'advisor'
            ])->whereHas('student', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });

            // Lọc theo thời gian cho notification
            if ($request->filled('from_date')) {
                $notificationQuery->where('created_at', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $notificationQuery->where('created_at', '<=', $request->to_date);
            }

            $notificationResponses = $notificationQuery->get()->map(function ($response) {
                return [
                    'id' => $response->response_id,
                    'source' => 'notification',
                    'source_id' => $response->notification_id,
                    'source_title' => $response->notification->title ?? 'N/A',
                    'student_id' => $response->student_id,
                    'student_name' => $response->student->full_name ?? 'N/A',
                    'student_code' => $response->student->user_code ?? 'N/A',
                    'content' => $response->content,
                    'advisor_response' => $response->advisor_response,
                    'status' => $response->status,
                    'created_at' => $response->created_at,
                    'response_at' => $response->response_at,
                    'student' => $response->student,
                    'notification' => $response->notification,
                    'advisor' => $response->advisor
                ];
            });

            // Merge cả 2 nguồn
            $allDialogues = $meetingFeedbacks->merge($notificationResponses);

            // Thống kê tổng hợp
            $totalDialogues = $allDialogues->count();
            $totalMeeting = $meetingFeedbacks->count();
            $totalNotification = $notificationResponses->count();
            $pendingDialogues = $allDialogues->where('status', 'pending')->count();
            $resolvedDialogues = $allDialogues->where('status', 'resolved')->count();

            // Thống kê theo sinh viên
            $studentStats = $allDialogues->groupBy('student_id')->map(function ($items, $studentId) {
                $first = $items->first();
                return [
                    'student_id' => $studentId,
                    'user_code' => $first['student_code'],
                    'full_name' => $first['student_name'],
                    'dialogue_count' => $items->count(),
                    'from_meeting' => $items->where('source', 'meeting')->count(),
                    'from_notification' => $items->where('source', 'notification')->count(),
                    'pending_count' => $items->where('status', 'pending')->count(),
                    'resolved_count' => $items->where('status', 'resolved')->count()
                ];
            })->sortByDesc('dialogue_count')->values();

            // Sắp xếp dialogues theo thời gian
            $dialogues = $allDialogues->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'class' => $class,
                    'summary' => [
                        'total' => $totalDialogues,
                        'total_meeting' => $totalMeeting,
                        'total_notification' => $totalNotification,
                        'pending' => $pendingDialogues,
                        'resolved' => $resolvedDialogues,
                        'response_rate' => $totalDialogues > 0
                            ? round(($resolvedDialogues / $totalDialogues) * 100, 2)
                            : 0
                    ],
                    'students' => $studentStats,
                    'dialogues' => $dialogues
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy báo cáo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xuất báo cáo ý kiến đối thoại
     * GET /api/dialogues/export
     */
    public function exportReport(Request $request)
    {
        try {
            $userRole = $request->current_role;
            $userId = $request->current_user_id;

            if (!in_array($userRole, ['advisor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xuất báo cáo'
                ], 403);
            }

            // Validate parameters
            $validator = Validator::make($request->all(), [
                'class_id' => 'nullable|exists:Classes,class_id',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date',
                'source' => 'nullable|in:meeting,notification,all'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Kiểm tra quyền truy cập class_id nếu có
            if ($request->filled('class_id')) {
                $class = ClassModel::find($request->class_id);

                if ($userRole === 'advisor' && $class->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xuất báo cáo lớp này'
                    ], 403);
                } elseif ($userRole === 'admin') {
                    $advisor = Advisor::find($userId);
                    if ($class->faculty_id !== $advisor->unit_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Lớp này không thuộc khoa bạn quản lý'
                        ], 403);
                    }
                }
            }

            // Lấy dữ liệu dialogues
            $source = $request->get('source', 'all');
            $allDialogues = collect();

            // Lấy meeting feedbacks nếu cần
            if (in_array($source, ['all', 'meeting'])) {
                $meetingDialogues = $this->getMeetingFeedbacks($userId, $userRole, $request);
                $allDialogues = $allDialogues->merge($meetingDialogues);
            }

            // Lấy notification responses nếu cần
            if (in_array($source, ['all', 'notification'])) {
                $notificationDialogues = $this->getNotificationResponses($userId, $userRole, $request);
                $allDialogues = $allDialogues->merge($notificationDialogues);
            }

            // Sắp xếp theo thời gian
            $allDialogues = $allDialogues->sortByDesc('created_at')->values();

            // Tạo file Excel
            $excelHeaderService = app(ExcelHeaderService::class);
            $spreadsheet = $excelHeaderService->createWithProfessionalHeader();
            $sheet = $spreadsheet->getActiveSheet();

            // Điền tiêu đề chính (dòng 5)
            $excelHeaderService->fillTitle($sheet, 'BÁO CÁO Ý KIẾN ĐỐI THOẠI', 5, 'K');

            // Điền thông tin chi tiết (bắt đầu từ dòng 7)
            $infoData = [];

            if ($request->filled('class_id')) {
                $class = ClassModel::with('faculty')->find($request->class_id);
                $infoData['Lớp:'] = $class->class_name;
                $infoData['Khoa:'] = $class->faculty->unit_name;
            } elseif ($userRole === 'advisor') {
                $classes = ClassModel::where('advisor_id', $userId)->pluck('class_name')->toArray();
                $infoData['Lớp:'] = implode(', ', $classes);
            } elseif ($userRole === 'admin') {
                $advisor = Advisor::with('unit')->find($userId);
                $infoData['Khoa:'] = $advisor->unit->unit_name ?? 'N/A';
            }

            $infoData['Nguồn dữ liệu:'] = $source === 'all' ? 'Tất cả' : ($source === 'meeting' ? 'Cuộc họp' : 'Thông báo');

            if ($request->filled('from_date') && $request->filled('to_date')) {
                $infoData['Thời gian:'] = date('d/m/Y', strtotime($request->from_date)) . ' - ' . date('d/m/Y', strtotime($request->to_date));
            }

            $infoData['Ngày xuất:'] = date('d/m/Y H:i');
            $infoData['Tổng số ý kiến:'] = $allDialogues->count();

            $row = $excelHeaderService->fillInfoSection($sheet, $infoData, 7, 'K');

            // Dòng trống
            $row++;

            // Header bảng dữ liệu
            $headers = ['STT', 'Nguồn', 'Tiêu đề', 'MSSV', 'Họ tên SV', 'Lớp', 'Nội dung', 'Phản hồi', 'Trạng thái', 'Ngày tạo', 'Ngày phản hồi'];
            $excelHeaderService->createTableHeader($sheet, $headers, $row);
            $row++;

            // Chuẩn bị dữ liệu
            $tableData = [];
            $stt = 1;
            foreach ($allDialogues as $dialogue) {
                $tableData[] = [
                    $stt,
                    $dialogue['source'] === 'meeting' ? 'Cuộc họp' : 'Thông báo',
                    $dialogue['source_title'],
                    $dialogue['student_code'],
                    $dialogue['student_name'],
                    $dialogue['class_name'],
                    $dialogue['content'],
                    $dialogue['advisor_response'] ?? 'Chưa phản hồi',
                    $dialogue['status'] === 'pending' ? 'Chưa xử lý' : 'Đã xử lý',
                    $dialogue['created_at'] ? $dialogue['created_at']->format('d/m/Y H:i') : '',
                    $dialogue['response_at'] ? $dialogue['response_at']->format('d/m/Y H:i') : ''
                ];
                $stt++;
            }

            // Điền dữ liệu bảng
            $lastRow = $excelHeaderService->fillTableData($sheet, $tableData, $row);

            // Auto format columns
            $excelHeaderService->autoFormatColumns(
                $sheet,
                range('A', 'K'),
                [
                    'B' => 15,  // Nguồn
                    'C' => 30,  // Tiêu đề
                    'D' => 12,  // MSSV
                    'E' => 25,  // Họ tên
                    'F' => 15,  // Lớp
                    'G' => 40,  // Nội dung
                    'H' => 40,  // Phản hồi
                    'I' => 15,  // Trạng thái
                    'J' => 18,  // Ngày tạo
                    'K' => 18   // Ngày phản hồi
                ]
            );

            // Tạo file tạm để download
            $fileName = 'BaoCao_YKienDoiThoai_' . date('YmdHis') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $fileName);

            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            // Lưu file
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save($tempPath);

            // Xóa tất cả output buffer trước khi download file
            if (ob_get_length()) {
                ob_end_clean();
            }

            // Trả về file download trực tiếp
            return response()->download($tempPath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất báo cáo: ' . $e->getMessage()
            ], 500);
        }
    }
}
