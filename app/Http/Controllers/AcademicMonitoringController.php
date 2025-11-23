<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\SemesterReport;
use App\Models\CourseGrade;
use App\Models\AcademicWarning;
use App\Models\Semester;
use App\Models\ClassModel;
use App\Services\AcademicMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AcademicMonitoringController extends Controller
{
    /**
     * [STUDENT & ADVISOR] Xem báo cáo học kỳ của sinh viên
     * GET /api/academic/semester-report/{student_id}/{semester_id}
     */
    public function getSemesterReport(Request $request, $studentId, $semesterId)
    {
        $currentRole = $request->current_role;
        $currentUserId = $request->current_user_id;

        // Kiểm tra quyền truy cập
        if ($currentRole === 'student') {
            if ($currentUserId != $studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ được xem báo cáo của chính mình'
                ], 403);
            }
        } elseif ($currentRole === 'advisor') {
            // Advisor chỉ được xem sinh viên trong lớp mình quản lý
            $student = Student::with('class')->find($studentId);
            if (!$student || $student->class->advisor_id != $currentUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ được xem sinh viên trong lớp mình quản lý'
                ], 403);
            }
        }

        $report = SemesterReport::with(['student.class', 'semester'])
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->first();

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy báo cáo học kỳ'
            ], 404);
        }

        // Lấy danh sách môn học trong kỳ với điểm đã quy đổi
        $courseGrades = CourseGrade::with('course')
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->get()
            ->map(function ($grade) {
                return [
                    'course_code' => $grade->course->course_code,
                    'course_name' => $grade->course->course_name,
                    'credits' => $grade->course->credits,
                    'grade_10' => $grade->grade_value,
                    'grade_letter' => $grade->grade_letter,
                    'grade_4' => $grade->grade_4_scale,
                    'status' => $grade->status
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'student_info' => [
                    'student_id' => $report->student->student_id,
                    'user_code' => $report->student->user_code,
                    'full_name' => $report->student->full_name,
                    'class_name' => $report->student->class->class_name
                ],
                'semester_info' => [
                    'semester_name' => $report->semester->semester_name,
                    'academic_year' => $report->semester->academic_year
                ],
                'report' => [
                    'gpa' => $report->gpa,
                    'gpa_4_scale' => $report->gpa_4_scale,
                    'cpa_10_scale' => $report->cpa_10_scale,
                    'cpa_4_scale' => $report->cpa_4_scale,
                    'credits_registered' => $report->credits_registered,
                    'credits_passed' => $report->credits_passed,
                    'training_point_summary' => $report->training_point_summary,
                    'social_point_summary' => $report->social_point_summary,
                    'outcome' => $report->outcome
                ],
                'course_grades' => $courseGrades
            ]
        ]);
    }

    /**
     * [ADVISOR] Xem danh sách sinh viên có nguy cơ bỏ học
     * GET /api/academic/at-risk-students
     */
    public function getAtRiskStudents(Request $request)
    {
        $advisorId = $request->current_user_id;
        $semesterId = $request->query('semester_id');

        // Lấy danh sách lớp do advisor quản lý
        $classes = ClassModel::where('advisor_id', $advisorId)->pluck('class_id');

        // Nếu không chỉ định học kỳ, lấy học kỳ mới nhất
        if (!$semesterId) {
            $semester = Semester::orderBy('start_date', 'desc')->first();
            $semesterId = $semester ? $semester->semester_id : null;
        } else {
            $semester = Semester::find($semesterId);
        }

        if (!$semesterId || !$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy học kỳ'
            ], 404);
        }

        // Lấy sinh viên có vấn đề học tập
        $students = Student::whereIn('class_id', $classes)
            ->with([
                'class',
                'semesterReports' => function ($query) use ($semesterId) {
                    $query->where('semester_id', $semesterId);
                }
            ])
            ->get();

        $atRiskStudents = [];

        foreach ($students as $student) {
            $riskInfo = AcademicMonitoringService::checkDropoutRisk($student->student_id, $semesterId);

            if ($riskInfo['is_at_risk']) {
                // Lấy toàn bộ cảnh cáo học vụ của sinh viên trong kỳ này
                $warnings = AcademicWarning::where('student_id', $student->student_id)
                    ->where('semester_id', $semesterId)
                    ->with('semester')
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($warning) {
                        return [
                            'warning_id' => $warning->warning_id,
                            'title' => $warning->title,
                            'content' => $warning->content,
                            'advice' => $warning->advice,
                            'semester' => $warning->semester->semester_name . ' ' . $warning->semester->academic_year,
                            'created_at' => $warning->created_at->format('d/m/Y H:i')
                        ];
                    });

                $atRiskStudents[] = [
                    'student_id' => $student->student_id,
                    'user_code' => $student->user_code,
                    'full_name' => $student->full_name,
                    'class_name' => $student->class->class_name,
                    'status' => $student->status,
                    'cpa_4_scale' => $riskInfo['cpa_4'],
                    'warning_threshold' => $riskInfo['threshold'],
                    'risk_level' => $riskInfo['risk_level'],
                    'risk_reasons' => $riskInfo['reasons'],
                    'failed_courses_count' => $riskInfo['failed_courses_count'],
                    'has_academic_warning' => $warnings->isNotEmpty(),
                    'warnings' => $warnings,
                    'warnings_count' => $warnings->count()
                ];
            }
        }

        // Sắp xếp theo mức độ nguy hiểm
        $sorted = collect($atRiskStudents)->sortByDesc(function ($item) {
            $levels = ['critical' => 3, 'high' => 2, 'medium' => 1];
            return $levels[$item['risk_level']] ?? 0;
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'semester' => [
                    'semester_name' => $semester->semester_name,
                    'academic_year' => $semester->academic_year
                ],
                'at_risk_students' => $sorted,
                'summary' => [
                    'total' => $sorted->count(),
                    'critical' => $sorted->where('risk_level', 'critical')->count(),
                    'high' => $sorted->where('risk_level', 'high')->count(),
                    'medium' => $sorted->where('risk_level', 'medium')->count()
                ]
            ]
        ]);
    }

    /**
     * [ADVISOR] Tự động tạo cảnh cáo học vụ cho sinh viên
     * POST /api/academic/create-warnings
     */
    public function createAcademicWarnings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'semester_id' => 'required|exists:Semesters,semester_id',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:Students,student_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $advisorId = $request->current_user_id;
        $semesterId = $request->semester_id;
        $studentIds = $request->student_ids;

        $warnings = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($studentIds as $studentId) {
                // Kiểm tra quyền
                $student = Student::with('class')->find($studentId);

                if (!$student || $student->class->advisor_id != $advisorId) {
                    $errors[] = "Không có quyền tạo cảnh cáo cho sinh viên ID: {$studentId}";
                    continue;
                }

                // Lấy báo cáo học kỳ
                $report = SemesterReport::where('student_id', $studentId)
                    ->where('semester_id', $semesterId)
                    ->first();

                if (!$report) {
                    $errors[] = "Không tìm thấy báo cáo học kỳ cho sinh viên {$student->full_name}";
                    continue;
                }

                // Kiểm tra điều kiện cảnh cáo
                $threshold = AcademicMonitoringService::getWarningThreshold($studentId, $semesterId);
                $cpa4 = $report->cpa_4_scale;

                if ($cpa4 >= $threshold) {
                    $errors[] = "Sinh viên {$student->full_name} không đạt ngưỡng cảnh cáo (CPA: {$cpa4}, Ngưỡng: {$threshold})";
                    continue;
                }

                // Kiểm tra đã có cảnh cáo chưa
                $existingWarning = AcademicWarning::where('student_id', $studentId)
                    ->where('semester_id', $semesterId)
                    ->first();

                if ($existingWarning) {
                    $errors[] = "Sinh viên {$student->full_name} đã có cảnh cáo học vụ trong kỳ này";
                    continue;
                }

                // Tạo cảnh cáo
                $semester = Semester::find($semesterId);
                $warningLevel = AcademicMonitoringService::getWarningLevel($cpa4, $threshold);

                // Lấy thông tin rủi ro để tạo lời khuyên
                $riskInfo = AcademicMonitoringService::checkDropoutRisk($studentId, $semesterId);

                $warning = AcademicWarning::create([
                    'student_id' => $studentId,
                    'advisor_id' => $advisorId,
                    'semester_id' => $semesterId,
                    'title' => "Cảnh cáo học vụ mức {$warningLevel} - {$semester->semester_name} {$semester->academic_year}",
                    'content' => "Sinh viên {$student->full_name} (MSSV: {$student->user_code}) có CPA thang 4 là {$cpa4}, thấp hơn ngưỡng quy định {$threshold}. " .
                        "GPA học kỳ: {$report->gpa}. Tổng số tín chỉ đã qua: {$report->credits_passed}/{$report->credits_registered}." .
                        "\n\nLý do chi tiết: " . implode("; ", $riskInfo['reasons']),
                    'advice' => "Sinh viên cần:\n" .
                        "1. Đăng ký học lại các môn bị rớt để cải thiện CPA\n" .
                        "2. Tham gia các lớp học phụ đạo\n" .
                        "3. Gặp cố vấn học tập để được tư vấn chi tiết về kế hoạch học tập\n" .
                        "4. Quản lý thời gian học tập hiệu quả hơn\n" .
                        "5. Nâng cao GPA trong các học kỳ tiếp theo để tránh bị buộc thôi học" .
                        ($warningLevel >= 3 ? "\n\nCẢNH BÁO NGHIÊM TRỌNG: Nguy cơ bị buộc thôi học rất cao!" : "")
                ]);

                // Kiểm tra tổng số cảnh cáo của sinh viên
                $totalWarnings = AcademicWarning::where('student_id', $studentId)->count();

                $statusChanged = false;
                if ($totalWarnings >= 3) {
                    // Tự động chuyển trạng thái thành thôi học
                    $student->status = 'dropped';
                    $student->save();
                    $statusChanged = true;
                }

                $warnings[] = [
                    'student_name' => $student->full_name,
                    'user_code' => $student->user_code,
                    'cpa_4_scale' => $cpa4,
                    'threshold' => $threshold,
                    'warning_level' => $warningLevel,
                    'warning_id' => $warning->warning_id,
                    'total_warnings' => $totalWarnings,
                    'status_changed_to_dropped' => $statusChanged
                ];

                // Log
                Log::info('Academic warning created', [
                    'advisor_id' => $advisorId,
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'warning_level' => $warningLevel,
                    'cpa' => $cpa4,
                    'total_warnings' => $totalWarnings,
                    'status_changed' => $statusChanged
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo cảnh cáo học vụ thành công',
                'data' => [
                    'warnings_created' => $warnings,
                    'total_created' => count($warnings),
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create academic warnings', [
                'error' => $e->getMessage(),
                'advisor_id' => $advisorId,
                'semester_id' => $semesterId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo cảnh cáo học vụ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [STUDENT] Xem danh sách cảnh cáo học vụ của mình
     * GET /api/academic/my-warnings
     */
    public function getMyWarnings(Request $request)
    {
        $studentId = $request->current_user_id;

        $warnings = AcademicWarning::with(['advisor', 'semester'])
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($warning) {
                return [
                    'warning_id' => $warning->warning_id,
                    'title' => $warning->title,
                    'content' => $warning->content,
                    'advice' => $warning->advice,
                    'semester' => $warning->semester->semester_name . ' ' . $warning->semester->academic_year,
                    'advisor_name' => $warning->advisor->full_name,
                    'created_at' => $warning->created_at->format('d/m/Y H:i')
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'warnings' => $warnings,
                'total' => $warnings->count()
            ]
        ]);
    }

    /**
     * [ADVISOR] Xem danh sách cảnh cáo đã tạo
     * GET /api/academic/warnings-created
     */
    public function getWarningsCreated(Request $request)
    {
        $advisorId = $request->current_user_id;

        $warnings = AcademicWarning::with(['student.class', 'semester'])
            ->where('advisor_id', $advisorId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($warning) {
                return [
                    'warning_id' => $warning->warning_id,
                    'title' => $warning->title,
                    'student_name' => $warning->student->full_name,
                    'user_code' => $warning->student->user_code,
                    'class_name' => $warning->student->class->class_name,
                    'semester' => $warning->semester->semester_name . ' ' . $warning->semester->academic_year,
                    'created_at' => $warning->created_at->format('d/m/Y H:i')
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'warnings' => $warnings,
                'total' => $warnings->count()
            ]
        ]);
    }

    /**
     * [ADVISOR] Thống kê tổng quan học vụ
     * GET /api/academic/statistics
     */
    public function getAcademicStatistics(Request $request)
    {
        $advisorId = $request->current_user_id;
        $semesterId = $request->query('semester_id');

        // Lấy danh sách lớp do advisor quản lý
        $classes = ClassModel::where('advisor_id', $advisorId)->pluck('class_id');

        // Lấy tất cả sinh viên
        $studentIds = Student::whereIn('class_id', $classes)->pluck('student_id');

        // Nếu không chỉ định học kỳ, lấy học kỳ mới nhất
        if (!$semesterId) {
            $semester = Semester::orderBy('start_date', 'desc')->first();
            $semesterId = $semester ? $semester->semester_id : null;
        } else {
            $semester = Semester::find($semesterId);
        }

        if (!$semesterId || !$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy học kỳ'
            ], 404);
        }

        // Thống kê xếp loại
        $reports = SemesterReport::whereIn('student_id', $studentIds)
            ->where('semester_id', $semesterId)
            ->get();

        $stats = [
            'excellent' => 0,  // Giỏi (GPA >= 3.6)
            'good' => 0,       // Khá (3.0 <= GPA < 3.6)
            'average' => 0,    // TB (2.0 <= GPA < 3.0)
            'weak' => 0,       // Yếu (1.0 <= GPA < 2.0)
            'poor' => 0,       // Kém (GPA < 1.0)
            'warned' => 0,     // Bị cảnh cáo
            'dropout_risk' => 0 // Nguy cơ bỏ học cao
        ];

        foreach ($reports as $report) {
            $gpa4 = $report->gpa_4_scale;

            // Xếp loại theo GPA
            if ($gpa4 >= 3.6)
                $stats['excellent']++;
            elseif ($gpa4 >= 3.0)
                $stats['good']++;
            elseif ($gpa4 >= 2.0)
                $stats['average']++;
            elseif ($gpa4 >= 1.0)
                $stats['weak']++;
            else
                $stats['poor']++;

            // Kiểm tra cảnh cáo
            $threshold = AcademicMonitoringService::getWarningThreshold($report->student_id, $semesterId);
            if ($gpa4 < $threshold) {
                $stats['warned']++;
            }

            // Kiểm tra nguy cơ bỏ học
            $riskInfo = AcademicMonitoringService::checkDropoutRisk($report->student_id, $semesterId);
            if ($riskInfo['is_at_risk'] && in_array($riskInfo['risk_level'], ['high', 'critical'])) {
                $stats['dropout_risk']++;
            }
        }

        $totalReports = max($reports->count(), 1); // Tránh chia cho 0

        return response()->json([
            'success' => true,
            'data' => [
                'semester' => [
                    'semester_name' => $semester->semester_name,
                    'academic_year' => $semester->academic_year
                ],
                'total_students' => $studentIds->count(),
                'statistics' => $stats,
                'percentages' => [
                    'excellent' => round(($stats['excellent'] / $totalReports) * 100, 2),
                    'good' => round(($stats['good'] / $totalReports) * 100, 2),
                    'average' => round(($stats['average'] / $totalReports) * 100, 2),
                    'weak' => round(($stats['weak'] / $totalReports) * 100, 2),
                    'poor' => round(($stats['poor'] / $totalReports) * 100, 2),
                    'warned' => round(($stats['warned'] / $totalReports) * 100, 2),
                    'dropout_risk' => round(($stats['dropout_risk'] / $totalReports) * 100, 2)
                ]
            ]
        ]);
    }

    /**
     * [ADVISOR] Cập nhật báo cáo học kỳ (tính lại GPA, CPA, điểm DRL/CTXH)
     * POST /api/academic/update-semester-report
     */
    public function updateSemesterReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:Students,student_id',
            'semester_id' => 'required|exists:Semesters,semester_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $advisorId = $request->current_user_id;
        $studentId = $request->student_id;
        $semesterId = $request->semester_id;

        // Kiểm tra quyền
        $student = Student::with('class')->find($studentId);
        if (!$student || $student->class->advisor_id != $advisorId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ được cập nhật báo cáo cho sinh viên trong lớp mình quản lý'
            ], 403);
        }

        try {
            $report = AcademicMonitoringService::updateSemesterReport($studentId, $semesterId);

            Log::info('Semester report updated', [
                'advisor_id' => $advisorId,
                'student_id' => $studentId,
                'semester_id' => $semesterId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật báo cáo học kỳ thành công',
                'data' => [
                    'student_name' => $student->full_name,
                    'user_code' => $student->user_code,
                    'report' => [
                        'gpa' => $report->gpa,
                        'gpa_4_scale' => $report->gpa_4_scale,
                        'cpa_10_scale' => $report->cpa_10_scale,
                        'cpa_4_scale' => $report->cpa_4_scale,
                        'credits_registered' => $report->credits_registered,
                        'credits_passed' => $report->credits_passed,
                        'training_point_summary' => $report->training_point_summary,
                        'social_point_summary' => $report->social_point_summary,
                        'outcome' => $report->outcome
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update semester report', [
                'error' => $e->getMessage(),
                'student_id' => $studentId,
                'semester_id' => $semesterId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật báo cáo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADVISOR] Cập nhật báo cáo hàng loạt cho cả lớp
     * POST /api/academic/batch-update-semester-reports
     */
    public function batchUpdateSemesterReports(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:Classes,class_id',
            'semester_id' => 'required|exists:Semesters,semester_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $advisorId = $request->current_user_id;
        $classId = $request->class_id;
        $semesterId = $request->semester_id;

        // Kiểm tra quyền
        $class = ClassModel::find($classId);
        if (!$class || $class->advisor_id != $advisorId) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chỉ được cập nhật báo cáo cho lớp mình quản lý'
            ], 403);
        }

        try {
            $students = Student::where('class_id', $classId)->get();
            $results = [
                'success' => [],
                'errors' => []
            ];

            foreach ($students as $student) {
                try {
                    $report = AcademicMonitoringService::updateSemesterReport(
                        $student->student_id,
                        $semesterId
                    );

                    $results['success'][] = [
                        'student_id' => $student->student_id,
                        'user_code' => $student->user_code,
                        'full_name' => $student->full_name,
                        'gpa' => $report->gpa,
                        'cpa_4_scale' => $report->cpa_4_scale,
                        'training_points' => $report->training_point_summary,
                        'social_points' => $report->social_point_summary
                    ];

                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'student_id' => $student->student_id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            Log::info('Batch semester reports updated', [
                'advisor_id' => $advisorId,
                'class_id' => $classId,
                'semester_id' => $semesterId,
                'success_count' => count($results['success']),
                'error_count' => count($results['errors'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật báo cáo hàng loạt hoàn tất',
                'data' => [
                    'class_name' => $class->class_name,
                    'results' => $results['success'],
                    'errors' => $results['errors'],
                    'summary' => [
                        'total_processed' => $students->count(),
                        'success_count' => count($results['success']),
                        'error_count' => count($results['errors'])
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to batch update semester reports', [
                'error' => $e->getMessage(),
                'class_id' => $classId,
                'semester_id' => $semesterId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật hàng loạt: ' . $e->getMessage()
            ], 500);
        }
    }
}