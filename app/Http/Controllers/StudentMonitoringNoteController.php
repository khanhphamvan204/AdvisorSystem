<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StudentMonitoringNote;
use App\Models\Student;
use App\Models\Advisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * StudentMonitoringNoteController
 * 
 * Quản lý ghi chú theo dõi sinh viên của cố vấn
 * 
 * Middleware áp dụng:
 * - auth:api - Yêu cầu JWT token hợp lệ
 * - Middleware tự động inject current_role và current_user_id vào request
 * 
 * Luồng nghiệp vụ:
 * 1. Cố vấn tạo ghi chú theo dõi sinh viên (học tập, cá nhân, chuyên cần)
 * 2. Sinh viên có thể xem các ghi chú về mình
 * 
 * Categories: academic, personal, attendance, other
 */
class StudentMonitoringNoteController extends Controller
{
    /**
     * GET /api/monitoring-notes
     * 
     * Lấy danh sách ghi chú theo dõi
     * 
     * Quyền:
     * - Student: Chỉ xem ghi chú về mình
     * - Advisor: Xem ghi chú của sinh viên trong lớp mình phụ trách + ghi chú do mình tạo
     * 
     * Query params:
     * - student_id (optional): Lọc theo sinh viên
     * - semester_id (optional): Lọc theo học kỳ
     * - category (optional): academic|personal|attendance|other
     */
    public function index(Request $request)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            $query = StudentMonitoringNote::with([
                'student:student_id,user_code,full_name,email,class_id',
                'student.class:class_id,class_name',
                'advisor:advisor_id,full_name,email',
                'semester:semester_id,semester_name,academic_year'
            ]);

            // Kiểm tra quyền và lọc dữ liệu
            if ($currentRole === 'student') {
                // Sinh viên chỉ xem ghi chú về mình
                $query->where('student_id', $currentUserId);
            } elseif ($currentRole === 'advisor') {
                // Cố vấn xem ghi chú của sinh viên trong lớp mình phụ trách
                $advisor = Advisor::find($currentUserId);
                $advisorClasses = $advisor->classes()->pluck('class_id');

                $query->where(function ($q) use ($advisorClasses, $currentUserId) {
                    // Ghi chú của sinh viên trong lớp mình phụ trách
                    $q->whereHas('student', function ($sq) use ($advisorClasses) {
                        $sq->whereIn('class_id', $advisorClasses);
                    })
                        // HOẶC ghi chú do mình tạo (có thể tạo cho sinh viên khác khi cần)
                        ->orWhere('advisor_id', $currentUserId);
                });
            } elseif ($currentRole !== 'advisor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có quyền truy cập'
                ], 403);
            }

            // Áp dụng filters
            if ($request->has('student_id') && $currentRole === 'advisor') {
                $query->where('student_id', $request->student_id);
            }

            if ($request->has('semester_id')) {
                $query->where('semester_id', $request->semester_id);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            $notes = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $notes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách ghi chú: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/monitoring-notes/{id}
     * 
     * Xem chi tiết một ghi chú
     * 
     * Quyền:
     * - Student: Chỉ xem ghi chú về mình
     * - Advisor: Xem nếu sinh viên thuộc lớp mình phụ trách hoặc ghi chú do mình tạo
     */
    public function show(Request $request, $id)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            $note = StudentMonitoringNote::with([
                'student:student_id,user_code,full_name,email,phone_number,class_id',
                'student.class:class_id,class_name,advisor_id',
                'advisor:advisor_id,full_name,email',
                'semester:semester_id,semester_name,academic_year'
            ])->find($id);

            if (!$note) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy ghi chú'
                ], 404);
            }

            // Kiểm tra quyền truy cập
            if ($currentRole === 'student') {
                if ($note->student_id !== $currentUserId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem ghi chú này'
                    ], 403);
                }
            } elseif ($currentRole === 'advisor') {
                $advisor = Advisor::find($currentUserId);
                $advisorClasses = $advisor->classes()->pluck('class_id');

                // Kiểm tra: sinh viên trong lớp mình phụ trách HOẶC ghi chú do mình tạo
                $isAuthorized = $advisorClasses->contains($note->student->class_id)
                    || $note->advisor_id === $currentUserId;

                if (!$isAuthorized) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem ghi chú này'
                    ], 403);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $note
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin ghi chú: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/monitoring-notes
     * 
     * Cố vấn tạo ghi chú theo dõi mới
     * 
     * Quyền: Advisor
     * 
     * Body:
     * - user_code: required|exists:Students,user_code (Mã số sinh viên)
     * - semester_id: required|exists:Semesters,semester_id
     * - category: required|in:academic,personal,attendance,other
     * - title: required|string|max:255
     * - content: required|string|min:10
     */
    public function store(Request $request)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            // Chỉ advisor mới được tạo ghi chú
            if ($currentRole !== 'advisor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ cố vấn mới được tạo ghi chú theo dõi'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'user_code' => 'required|exists:Students,user_code',
                'semester_id' => 'required|exists:Semesters,semester_id',
                'category' => 'required|in:academic,personal,attendance,other',
                'title' => 'required|string|max:255',
                'content' => 'required|string|min:10|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Tìm sinh viên bằng mã số sinh viên (user_code)
            $student = Student::where('user_code', $request->user_code)->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sinh viên với mã số này'
                ], 404);
            }

            // Advisor chỉ được tạo ghi chú cho sinh viên trong lớp mình phụ trách
            $advisor = Advisor::find($currentUserId);
            $advisorClasses = $advisor->classes()->pluck('class_id');

            if (!$advisorClasses->contains($student->class_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ được tạo ghi chú cho sinh viên trong lớp mình phụ trách'
                ], 403);
            }

            $note = StudentMonitoringNote::create([
                'student_id' => $student->student_id,
                'advisor_id' => $currentUserId,
                'semester_id' => $request->semester_id,
                'category' => $request->category,
                'title' => $request->title,
                'content' => $request->input('content')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo ghi chú theo dõi thành công',
                'data' => $note->load(['student', 'advisor', 'semester'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo ghi chú: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/monitoring-notes/{id}
     * 
     * Cập nhật ghi chú theo dõi
     * 
     * Quyền: Advisor (chỉ cập nhật ghi chú do mình tạo)
     */
    public function update(Request $request, $id)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            if ($currentRole !== 'advisor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ cố vấn mới được cập nhật ghi chú'
                ], 403);
            }

            $note = StudentMonitoringNote::find($id);

            if (!$note) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy ghi chú'
                ], 404);
            }

            // Advisor chỉ được cập nhật ghi chú do mình tạo
            if ($note->advisor_id !== $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ được cập nhật ghi chú do mình tạo'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|required|in:academic,personal,attendance,other',
                'title' => 'sometimes|required|string|max:255',
                'content' => 'sometimes|required|string|min:10|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cập nhật các trường được gửi lên
            if ($request->has('category')) {
                $note->category = $request->category;
            }
            if ($request->has('title')) {
                $note->title = $request->title;
            }
            if ($request->has('content')) {
                $note->content = $request->input('content');
            }

            $note->save();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật ghi chú thành công',
                'data' => $note->load(['student', 'advisor', 'semester'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật ghi chú: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/monitoring-notes/{id}
     * 
     * Xóa ghi chú theo dõi
     * 
     * Quyền: Advisor (chỉ xóa ghi chú do mình tạo)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            if ($currentRole !== 'advisor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ cố vấn mới được xóa ghi chú'
                ], 403);
            }

            $note = StudentMonitoringNote::find($id);

            if (!$note) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy ghi chú'
                ], 404);
            }

            // Advisor chỉ được xóa ghi chú do mình tạo
            if ($note->advisor_id !== $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ được xóa ghi chú do mình tạo'
                ], 403);
            }

            $note->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa ghi chú thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa ghi chú: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/monitoring-notes/student/{student_id}/timeline
     * 
     * Xem timeline các ghi chú theo dõi của một sinh viên
     * 
     * Quyền:
     * - Student: Chỉ xem timeline của mình
     * - Advisor: Xem timeline sinh viên trong lớp mình phụ trách
     */
    public function studentTimeline(Request $request, $student_id)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            // Kiểm tra quyền truy cập
            if ($currentRole === 'student') {
                if ($currentUserId != $student_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn chỉ được xem timeline của mình'
                    ], 403);
                }
            } elseif ($currentRole === 'advisor') {
                $advisor = Advisor::find($currentUserId);
                $advisorClasses = $advisor->classes()->pluck('class_id');

                $student = Student::find($student_id);

                if (!$student || !$advisorClasses->contains($student->class_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem timeline của sinh viên này'
                    ], 403);
                }
            }

            // Kiểm tra sinh viên tồn tại
            $student = Student::with('class')->find($student_id);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sinh viên'
                ], 404);
            }

            $notes = StudentMonitoringNote::where('student_id', $student_id)
                ->with([
                    'advisor:advisor_id,full_name,email',
                    'semester:semester_id,semester_name,academic_year'
                ])
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('category');

            $summary = [
                'student' => $student,
                'total_notes' => StudentMonitoringNote::where('student_id', $student_id)->count(),
                'by_category' => [
                    'academic' => StudentMonitoringNote::where('student_id', $student_id)->where('category', 'academic')->count(),
                    'personal' => StudentMonitoringNote::where('student_id', $student_id)->where('category', 'personal')->count(),
                    'attendance' => StudentMonitoringNote::where('student_id', $student_id)->where('category', 'attendance')->count(),
                    'other' => StudentMonitoringNote::where('student_id', $student_id)->where('category', 'other')->count(),
                ],
                'notes_by_category' => $notes
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy timeline: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/monitoring-notes/statistics
     * 
     * Thống kê ghi chú theo dõi
     * 
     * Quyền: Advisor (cho lớp mình phụ trách)
     * 
     * Query params:
     * - semester_id (optional)
     */
    public function statistics(Request $request)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            if ($currentRole !== 'advisor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có quyền truy cập'
                ], 403);
            }

            $query = StudentMonitoringNote::query();

            // Lọc theo quyền advisor
            $advisor = Advisor::find($currentUserId);
            $advisorClasses = $advisor->classes()->pluck('class_id');

            $query->whereHas('student', function ($q) use ($advisorClasses) {
                $q->whereIn('class_id', $advisorClasses);
            });

            // Áp dụng filters
            if ($request->has('semester_id')) {
                $query->where('semester_id', $request->semester_id);
            }

            $statistics = [
                'total' => $query->count(),
                'by_category' => [
                    'academic' => (clone $query)->where('category', 'academic')->count(),
                    'personal' => (clone $query)->where('category', 'personal')->count(),
                    'attendance' => (clone $query)->where('category', 'attendance')->count(),
                    'other' => (clone $query)->where('category', 'other')->count(),
                ],
                'by_semester' => (clone $query)
                    ->selectRaw('semester_id, category, COUNT(*) as count')
                    ->with('semester:semester_id,semester_name,academic_year')
                    ->groupBy('semester_id', 'category')
                    ->get()
                    ->groupBy('semester_id'),
                'recent_notes' => (clone $query)
                    ->with(['student:student_id,full_name', 'advisor:advisor_id,full_name'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thống kê: ' . $e->getMessage()
            ], 500);
        }
    }
}
