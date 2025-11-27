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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\IOFactory;



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
    /**
     * [ADMIN] Download template Excel để import cảnh cáo học vụ
     * GET /api/academic/download-warnings-template
     * 
     * Template bao gồm:
     * - Header với định dạng và màu sắc
     * - Dòng mẫu với dữ liệu ví dụ
     * - Ghi chú hướng dẫn
     */
    public function downloadWarningsTemplate(Request $request)
    {
        // Kiểm tra quyền admin
        if ($request->current_role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ admin mới có quyền tải template'
            ], 403);
        }

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Thiết lập tiêu đề
            $sheet->setTitle('Cảnh cáo học vụ');

            // Header
            $headers = [
                'A1' => 'Mã SV',
                'B1' => 'Họ tên',
                'C1' => 'Lớp',
                'D1' => 'Học kỳ',
                'E1' => 'Năm học',
                'F1' => 'Tiêu đề',
                'G1' => 'Nội dung',
                'H1' => 'Lời khuyên'
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }

            // Style cho header
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ];

            $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

            // Thiết lập độ rộng cột
            $sheet->getColumnDimension('A')->setWidth(12);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(15);
            $sheet->getColumnDimension('D')->setWidth(12);
            $sheet->getColumnDimension('E')->setWidth(12);
            $sheet->getColumnDimension('F')->setWidth(40);
            $sheet->getColumnDimension('G')->setWidth(50);
            $sheet->getColumnDimension('H')->setWidth(50);

            // Thiết lập chiều cao header
            $sheet->getRowDimension(1)->setRowHeight(25);

            // Dữ liệu mẫu
            $sampleData = [
                [
                    '210001',
                    'Nguyễn Văn A',
                    'DH21CNTT',
                    'Học kỳ 1',
                    '2024-2025',
                    'Cảnh cáo học vụ mức 1',
                    'Sinh viên có GPA thấp hơn mức quy định. CPA hiện tại: 1.8/4.0, ngưỡng yêu cầu: 2.0/4.0',
                    'Sinh viên cần đăng ký học lại các môn bị rớt, tham gia lớp học phụ đạo, và cải thiện phương pháp học tập'
                ],
                [
                    '210002',
                    'Trần Thị B',
                    'DH21CNTT',
                    'Học kỳ 1',
                    '2024-2025',
                    'Cảnh cáo học vụ mức 2',
                    'Sinh viên có nhiều môn học bị rớt. Đã rớt 5 môn trong học kỳ này, CPA: 1.5/4.0',
                    'Sinh viên cần gặp cố vấn học tập để lập kế hoạch học tập chi tiết. Cần cải thiện đáng kể kết quả học tập để tránh bị buộc thôi học'
                ]
            ];

            $row = 2;
            foreach ($sampleData as $data) {
                $col = 'A';
                foreach ($data as $value) {
                    $sheet->setCellValue($col . $row, $value);
                    $col++;
                }
                $row++;
            }

            // Style cho dữ liệu
            $dataStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true
                ]
            ];

            $sheet->getStyle('A2:H' . ($row - 1))->applyFromArray($dataStyle);

            // Thiết lập chiều cao cho dòng dữ liệu
            for ($i = 2; $i < $row; $i++) {
                $sheet->getRowDimension($i)->setRowHeight(40);
            }

            // Thêm sheet hướng dẫn
            $guideSheet = $spreadsheet->createSheet();
            $guideSheet->setTitle('Hướng dẫn');

            $instructions = [
                ['HƯỚNG DẪN SỬ DỤNG TEMPLATE IMPORT CẢNH CÁO HỌC VỤ'],
                [''],
                ['1. CẤU TRÚC FILE:'],
                ['   - Sheet đầu tiên chứa dữ liệu cảnh cáo học vụ'],
                ['   - Dòng 1: Header (KHÔNG ĐƯỢC XÓA hoặc THAY ĐỔI)'],
                ['   - Từ dòng 2 trở đi: Dữ liệu sinh viên cần cảnh cáo'],
                [''],
                ['2. CÁC CỘT BẮT BUỘC:'],
                ['   - Mã SV: Mã số sinh viên (VD: 210001)'],
                ['   - Học kỳ: Tên học kỳ (VD: Học kỳ 1, Học kỳ 2, Học kỳ hè)'],
                ['   - Năm học: Năm học (VD: 2024-2025)'],
                ['   - Tiêu đề: Tiêu đề cảnh cáo (VD: Cảnh cáo học vụ mức 1)'],
                ['   - Nội dung: Nội dung chi tiết cảnh cáo'],
                [''],
                ['3. CÁC CỘT TÙY CHỌN (dùng để kiểm tra):'],
                ['   - Họ tên: Tên sinh viên (hệ thống sẽ cảnh báo nếu không khớp)'],
                ['   - Lớp: Tên lớp (hệ thống sẽ cảnh báo nếu không khớp)'],
                ['   - Lời khuyên: Lời khuyên cho sinh viên (nếu để trống, hệ thống tạo tự động)'],
                [''],
                ['4. LƯU Ý QUAN TRỌNG:'],
                ['   - Mã sinh viên phải tồn tại trong hệ thống'],
                ['   - Học kỳ và năm học phải khớp với dữ liệu trong hệ thống'],
                ['   - Hệ thống sẽ tự động tìm cố vấn học tập từ lớp của sinh viên'],
                ['   - Không tạo trùng cảnh cáo cho cùng sinh viên trong cùng học kỳ'],
                ['   - File Excel phải có định dạng .xlsx hoặc .xls'],
                ['   - Kích thước file tối đa: 10MB'],
                [''],
                ['5. QUY TRÌNH IMPORT:'],
                ['   - Bước 1: Điền đầy đủ thông tin vào sheet "Cảnh cáo học vụ"'],
                ['   - Bước 2: Kiểm tra lại dữ liệu'],
                ['   - Bước 3: Lưu file'],
                ['   - Bước 4: Upload file qua API POST /api/academic/import-warnings'],
                ['   - Bước 5: Kiểm tra kết quả trả về'],
                [''],
                ['6. XỬ LÝ LỖI:'],
                ['   - Hệ thống sẽ trả về danh sách chi tiết:'],
                ['     + success: Các cảnh cáo được tạo thành công'],
                ['     + errors: Các lỗi cần xử lý'],
                ['     + warnings: Các cảnh báo (dữ liệu không khớp)'],
                [''],
                ['7. VÍ DỤ DỮ LIỆU:'],
                ['   Xem sheet "Cảnh cáo học vụ" để tham khảo dữ liệu mẫu'],
                [''],
                ['8. HỖ TRỢ:'],
                ['   Nếu có vấn đề, liên hệ bộ phận IT để được hỗ trợ']
            ];

            $row = 1;
            foreach ($instructions as $instruction) {
                $guideSheet->setCellValue('A' . $row, $instruction[0]);
                $row++;
            }

            // Style cho sheet hướng dẫn
            $guideSheet->getStyle('A1')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => '4472C4']
                ]
            ]);

            $guideSheet->getColumnDimension('A')->setWidth(80);
            $guideSheet->getStyle('A1:A' . ($row - 1))->getAlignment()->setWrapText(true);

            // Tô màu cho các tiêu đề chính
            $titleRows = [3, 8, 14, 20, 26, 32, 38, 44];
            foreach ($titleRows as $titleRow) {
                $guideSheet->getStyle('A' . $titleRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E7E6E6']
                    ]
                ]);
            }

            // Quay lại sheet đầu tiên
            $spreadsheet->setActiveSheetIndex(0);

            // Tạo file và trả về
            $writer = new Xlsx($spreadsheet);
            $fileName = 'Template_Import_Canh_Cao_Hoc_Vu_' . date('Ymd_His') . '.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);

            $writer->save($tempFile);

            return response()->download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Failed to generate template', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * [ADMIN] Import cảnh cáo học vụ từ file Excel
     * POST /api/academic/import-warnings
     * 
     * Format Excel yêu cầu:
     * - Sheet đầu tiên: "Cảnh cáo học vụ"
     * - Header row (dòng 1): Mã SV | Họ tên | Lớp | Học kỳ | Năm học | Tiêu đề | Nội dung | Lời khuyên
     * - Data rows: Bắt đầu từ dòng 2
     */
    public function importAcademicWarnings(Request $request)
    {
        // Kiểm tra quyền admin
        if ($request->current_role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ admin mới có quyền import cảnh cáo học vụ'
            ], 403);
        }

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
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();

            // Lấy dữ liệu với calculated values
            $rows = [];
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();

            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cell = $worksheet->getCell($col . $row);
                    $rowData[] = $cell->getCalculatedValue();
                }
                $rows[] = $rowData;
            }

            // Kiểm tra file có dữ liệu không
            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'File Excel không có dữ liệu hoặc thiếu header'
                ], 422);
            }

            // Validate header
            $header = array_map('trim', $rows[0]);
            $requiredHeaders = ['Mã SV', 'Học kỳ', 'Năm học', 'Tiêu đề', 'Nội dung'];

            foreach ($requiredHeaders as $requiredHeader) {
                if (!in_array($requiredHeader, $header)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Thiếu cột bắt buộc: {$requiredHeader}",
                        'required_headers' => $requiredHeaders,
                        'found_headers' => $header
                    ], 422);
                }
            }

            // Tìm vị trí các cột
            $colIndexes = [
                'user_code' => array_search('Mã SV', $header),
                'full_name' => array_search('Họ tên', $header),
                'class_name' => array_search('Lớp', $header),
                'semester_name' => array_search('Học kỳ', $header),
                'academic_year' => array_search('Năm học', $header),
                'title' => array_search('Tiêu đề', $header),
                'content' => array_search('Nội dung', $header),
                'advice' => array_search('Lời khuyên', $header)
            ];

            $results = [
                'success' => [],
                'errors' => [],
                'warnings' => []
            ];

            DB::beginTransaction();

            // Xử lý từng dòng (bỏ qua header)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $rowNumber = $i + 1;

                try {
                    // Lấy dữ liệu từ các cột
                    $userCode = trim($row[$colIndexes['user_code']] ?? '');
                    $fullName = $colIndexes['full_name'] !== false ? trim($row[$colIndexes['full_name']] ?? '') : '';
                    $className = $colIndexes['class_name'] !== false ? trim($row[$colIndexes['class_name']] ?? '') : '';
                    $semesterName = trim($row[$colIndexes['semester_name']] ?? '');
                    $academicYear = trim($row[$colIndexes['academic_year']] ?? '');
                    $title = trim($row[$colIndexes['title']] ?? '');
                    $content = trim($row[$colIndexes['content']] ?? '');
                    $advice = $colIndexes['advice'] !== false ? trim($row[$colIndexes['advice']] ?? '') : '';

                    // Bỏ qua dòng trống
                    if (empty($userCode) && empty($semesterName) && empty($title)) {
                        continue;
                    }

                    // Validate dữ liệu bắt buộc
                    if (empty($userCode)) {
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'error' => 'Mã sinh viên không được để trống'
                        ];
                        continue;
                    }

                    if (empty($semesterName) || empty($academicYear)) {
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'user_code' => $userCode,
                            'error' => 'Thiếu thông tin học kỳ hoặc năm học'
                        ];
                        continue;
                    }

                    if (empty($title) || empty($content)) {
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'user_code' => $userCode,
                            'error' => 'Thiếu tiêu đề hoặc nội dung cảnh cáo'
                        ];
                        continue;
                    }

                    // Tìm sinh viên theo mã số
                    $student = Student::with(['class.advisor'])
                        ->where('user_code', $userCode)
                        ->first();

                    if (!$student) {
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'user_code' => $userCode,
                            'error' => 'Không tìm thấy sinh viên với mã số này'
                        ];
                        continue;
                    }

                    // Kiểm tra sinh viên có thuộc lớp nào không
                    if (!$student->class) {
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'user_code' => $userCode,
                            'error' => 'Sinh viên chưa được phân lớp'
                        ];
                        continue;
                    }

                    // Kiểm tra lớp có cố vấn không
                    if (!$student->class->advisor) {
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'user_code' => $userCode,
                            'error' => "Lớp {$student->class->class_name} chưa có cố vấn học tập"
                        ];
                        continue;
                    }

                    // Kiểm tra tên sinh viên (cảnh báo nếu không khớp)
                    if (!empty($fullName) && $fullName !== $student->full_name) {
                        $results['warnings'][] = [
                            'row' => $rowNumber,
                            'user_code' => $userCode,
                            'warning' => "Tên trong file ({$fullName}) khác với tên trong hệ thống ({$student->full_name})"
                        ];
                    }

                    // Kiểm tra lớp (cảnh báo nếu không khớp)
                    if (!empty($className) && $className !== $student->class->class_name) {
                        $results['warnings'][] = [
                            'row' => $rowNumber,
                            'user_code' => $userCode,
                            'warning' => "Lớp trong file ({$className}) khác với lớp trong hệ thống ({$student->class->class_name})"
                        ];
                    }

                    // Tìm học kỳ
                    $semester = Semester::where('semester_name', $semesterName)
                        ->where('academic_year', $academicYear)
                        ->first();

                    if (!$semester) {
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'user_code' => $userCode,
                            'error' => "Không tìm thấy học kỳ: {$semesterName} {$academicYear}"
                        ];
                        continue;
                    }

                    // Kiểm tra đã có cảnh cáo trong học kỳ này chưa
                    $existingWarning = AcademicWarning::where('student_id', $student->student_id)
                        ->where('semester_id', $semester->semester_id)
                        ->where('title', $title)
                        ->first();

                    if ($existingWarning) {
                        $results['warnings'][] = [
                            'row' => $rowNumber,
                            'user_code' => $userCode,
                            'warning' => "Cảnh cáo tương tự đã tồn tại cho sinh viên này trong học kỳ {$semesterName} {$academicYear}"
                        ];
                        continue;
                    }

                    // Tạo lời khuyên mặc định nếu không có
                    if (empty($advice)) {
                        $advice = "Sinh viên cần:\n" .
                            "1. Đăng ký học lại các môn bị rớt để cải thiện điểm tích lũy\n" .
                            "2. Tham gia các lớp học phụ đạo và tư vấn học tập\n" .
                            "3. Gặp cố vấn học tập để được hướng dẫn chi tiết\n" .
                            "4. Cải thiện phương pháp và thái độ học tập\n" .
                            "5. Nâng cao kết quả học tập trong các học kỳ tiếp theo";
                    }

                    // Tạo cảnh cáo mới
                    $warning = AcademicWarning::create([
                        'student_id' => $student->student_id,
                        'advisor_id' => $student->class->advisor_id,
                        'semester_id' => $semester->semester_id,
                        'title' => $title,
                        'content' => $content,
                        'advice' => $advice
                    ]);

                    $results['success'][] = [
                        'row' => $rowNumber,
                        'user_code' => $userCode,
                        'student_name' => $student->full_name,
                        'class_name' => $student->class->class_name,
                        'advisor_name' => $student->class->advisor->full_name,
                        'semester' => "{$semesterName} {$academicYear}",
                        'warning_id' => $warning->warning_id
                    ];

                    Log::info('Academic warning imported', [
                        'warning_id' => $warning->warning_id,
                        'student_id' => $student->student_id,
                        'user_code' => $userCode,
                        'advisor_id' => $student->class->advisor_id,
                        'semester_id' => $semester->semester_id,
                        'imported_by' => $request->current_user_id
                    ]);

                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'row' => $rowNumber,
                        'user_code' => $userCode ?? 'N/A',
                        'error' => 'Lỗi xử lý: ' . $e->getMessage()
                    ];

                    Log::error('Error processing warning import row', [
                        'row' => $rowNumber,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            $totalProcessed = count($results['success']) + count($results['errors']);

            Log::info('Academic warnings import completed', [
                'total_processed' => $totalProcessed,
                'success_count' => count($results['success']),
                'error_count' => count($results['errors']),
                'warning_count' => count($results['warnings']),
                'imported_by' => $request->current_user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Import cảnh cáo học vụ hoàn tất',
                'data' => [
                    'summary' => [
                        'total_rows_processed' => $totalProcessed,
                        'success_count' => count($results['success']),
                        'error_count' => count($results['errors']),
                        'warning_count' => count($results['warnings'])
                    ],
                    'details' => $results
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to import academic warnings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'imported_by' => $request->current_user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import file: ' . $e->getMessage()
            ], 500);
        }
    }
}