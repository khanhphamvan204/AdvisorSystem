<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PointFeedback;
use App\Models\Student;
use App\Models\Advisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

/**
 * PointFeedbackController
 * 
 * Quản lý phản hồi về điểm rèn luyện/CTXH của sinh viên
 * 
 * Luồng nghiệp vụ:
 * 1. Sinh viên gửi phản hồi yêu cầu cộng điểm
 * 2. Cố vấn của lớp xem và phê duyệt/từ chối
 * 3. Admin có thể xem tất cả
 */
class PointFeedbackController extends Controller
{
    /**
     * GET /api/point-feedbacks
     * 
     * Lấy danh sách phản hồi điểm
     * 
     * Quyền:
     * - Student: Chỉ xem phản hồi của chính mình
     * - Advisor: Xem phản hồi của sinh viên trong các lớp mình phụ trách
     * - Admin: Xem tất cả
     */
    public function index(Request $request)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            $query = PointFeedback::with([
                'student:student_id,user_code,full_name,email,class_id',
                'student.class:class_id,class_name',
                'semester:semester_id,semester_name,academic_year',
                'advisor:advisor_id,full_name,email'
            ]);

            // Kiểm tra quyền và lọc dữ liệu
            if ($currentRole === 'student') {
                // Sinh viên chỉ xem của mình
                $query->where('student_id', $currentUserId);

            } elseif ($currentRole === 'advisor') {
                // Cố vấn xem sinh viên trong các lớp mình phụ trách
                $advisor = Advisor::find($currentUserId);
                $advisorClasses = $advisor->classes()->pluck('class_id');

                $query->whereHas('student', function ($q) use ($advisorClasses) {
                    $q->whereIn('class_id', $advisorClasses);
                });

            } elseif ($currentRole !== 'advisor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Áp dụng filters
            if ($request->has('semester_id')) {
                $query->where('semester_id', $request->semester_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Advisor có thể lọc theo student_id
            if ($request->has('student_id') && $currentRole === 'advisor') {
                $query->where('student_id', $request->student_id);
            }

            $feedbacks = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $feedbacks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách phản hồi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/point-feedbacks/{id}
     * 
     * Xem chi tiết một phản hồi
     * 
     * Quyền:
     * - Student: Chỉ xem phản hồi của mình
     * - Advisor: Xem nếu sinh viên thuộc lớp mình phụ trách
     * - Admin: Xem tất cả
     */
    public function show(Request $request, $id)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            $feedback = PointFeedback::with([
                'student:student_id,user_code,full_name,email,phone_number,class_id',
                'student.class:class_id,class_name,advisor_id',
                'semester:semester_id,semester_name,academic_year',
                'advisor:advisor_id,full_name,email'
            ])->find($id);

            if (!$feedback) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feedback not found'
                ], 404);
            }

            // Kiểm tra quyền truy cập
            if ($currentRole === 'student') {
                if ($feedback->student_id !== $currentUserId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem phản hồi này'
                    ], 403);
                }

            } elseif ($currentRole === 'advisor') {
                $advisor = Advisor::find($currentUserId);
                $advisorClasses = $advisor->classes()->pluck('class_id');

                if (!$advisorClasses->contains($feedback->student->class_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xem phản hồi này'
                    ], 403);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $feedback
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin phản hồi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/point-feedbacks
     * 
     * Sinh viên tạo phản hồi mới
     * 
     * Quyền: Chỉ student
     * 
     */
    public function store(Request $request)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            // Chỉ sinh viên mới được tạo phản hồi
            if ($currentRole !== 'student') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ sinh viên mới được tạo phản hồi'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'semester_id' => 'required|exists:Semesters,semester_id',
                'feedback_content' => 'required|string|min:10|max:2000',
                'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = time() . '_' . $currentUserId . '_' . $file->getClientOriginalName();
                $attachmentPath = $file->storeAs('point_feedbacks', $filename, 'public');
            }

            $feedback = PointFeedback::create([
                'student_id' => $currentUserId,
                'semester_id' => $request->semester_id,
                'feedback_content' => $request->feedback_content,
                'attachment_path' => $attachmentPath,
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo phản hồi thành công',
                'data' => $feedback->load(['semester', 'student'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo phản hồi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/point-feedbacks/{id}
     * 
     * Sinh viên cập nhật phản hồi (chỉ khi status = pending)
     * 
     * Quyền: Student (chỉ cập nhật phản hồi của mình)
     */
    public function update(Request $request, $id)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            if ($currentRole !== 'student') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ sinh viên mới được cập nhật phản hồi'
                ], 403);
            }

            $feedback = PointFeedback::find($id);

            if (!$feedback) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feedback not found'
                ], 404);
            }

            // Kiểm tra quyền sở hữu
            if ($feedback->student_id !== $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền cập nhật phản hồi này'
                ], 403);
            }

            // Chỉ cho phép cập nhật khi status = pending
            if ($feedback->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể cập nhật phản hồi đã được xử lý'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'feedback_content' => 'sometimes|required|string|min:10|max:2000',
                'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->has('feedback_content')) {
                $feedback->feedback_content = $request->feedback_content;
            }

            if ($request->hasFile('attachment')) {
                // Xóa file cũ nếu có
                if ($feedback->attachment_path) {
                    Storage::disk('public')->delete($feedback->attachment_path);
                }

                $file = $request->file('attachment');
                $filename = time() . '_' . $currentUserId . '_' . $file->getClientOriginalName();
                $feedback->attachment_path = $file->storeAs('point_feedbacks', $filename, 'public');
            }

            $feedback->save();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật phản hồi thành công',
                'data' => $feedback
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật phản hồi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/point-feedbacks/{id}/respond
     * 
     * Cố vấn phản hồi và phê duyệt/từ chối
     * 
     * Quyền: Advisor (phụ trách lớp của sinh viên) hoặc Admin
     * 
     * Body:
     * - status: required|in:approved,rejected
     * - advisor_response: required|string|min:10
     */
    public function respond(Request $request, $id)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            if ($currentRole !== 'advisor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ cố vấn mới được phản hồi'
                ], 403);
            }

            $feedback = PointFeedback::with('student.class')->find($id);

            if (!$feedback) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feedback not found'
                ], 404);
            }

            // Advisor chỉ được xử lý feedback của sinh viên trong lớp mình phụ trách
            $advisor = Advisor::find($currentUserId);
            $advisorClasses = $advisor->classes()->pluck('class_id');

            if (!$advisorClasses->contains($feedback->student->class_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền phản hồi phản hồi này'
                ], 403);
            }

            // Không cho phép xử lý lại feedback đã xử lý
            if ($feedback->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Phản hồi đã được xử lý'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:approved,rejected',
                'advisor_response' => 'required|string|min:10|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $feedback->update([
                'status' => $request->status,
                'advisor_response' => $request->advisor_response,
                'advisor_id' => $currentUserId,
                'response_at' => now()
            ]);

            $statusText = $request->status === 'approved' ? 'phê duyệt' : 'từ chối';
            return response()->json([
                'success' => true,
                'message' => 'Đã ' . $statusText . ' phản hồi thành công',
                'data' => $feedback->load(['advisor', 'student', 'semester'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi phản hồi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/point-feedbacks/{id}
     * 
     * Xóa phản hồi
     * 
     * Quyền:
     * - Student: Chỉ xóa phản hồi của mình (status = pending)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $currentRole = $request->current_role;
            $currentUserId = $request->current_user_id;

            $feedback = PointFeedback::find($id);

            if (!$feedback) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feedback not found'
                ], 404);
            }

            // Kiểm tra quyền - chỉ student được xóa
            if ($currentRole !== 'student') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ sinh viên mới được xóa phản hồi'
                ], 403);
            }

            if ($feedback->student_id !== $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa phản hồi này'
                ], 403);
            }

            if ($feedback->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa phản hồi đã được xử lý'
                ], 400);
            }

            // Xóa file đính kèm nếu có
            if ($feedback->attachment_path) {
                Storage::disk('public')->delete($feedback->attachment_path);
            }

            $feedback->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa phản hồi thành công'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa phản hồi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/point-feedbacks/statistics
     * 
     * Thống kê phản hồi điểm
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
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $query = PointFeedback::query();

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
                'pending' => (clone $query)->where('status', 'pending')->count(),
                'approved' => (clone $query)->where('status', 'approved')->count(),
                'rejected' => (clone $query)->where('status', 'rejected')->count(),
                'by_semester' => (clone $query)
                    ->selectRaw('semester_id, status, COUNT(*) as count')
                    ->with('semester:semester_id,semester_name,academic_year')
                    ->groupBy('semester_id', 'status')
                    ->get()
                    ->groupBy('semester_id')
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