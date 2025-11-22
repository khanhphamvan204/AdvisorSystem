<?php

namespace App\Services;

use App\Models\Student;
use App\Models\SemesterReport;
use App\Models\CourseGrade;
use App\Models\AcademicWarning;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;

// === THÊM DÒNG NÀY ĐỂ GỌI SERVICE TÍNH ĐIỂM HOẠT ĐỘNG ===
use App\Services\PointCalculationService;

class AcademicMonitoringService
{
    /**
     * Quy đổi điểm từ thang 10 sang thang 4 và điểm chữ
     */
    public static function convertGrade($grade10)
    {
        if ($grade10 >= 8.5)
            return ['letter' => 'A', 'scale4' => 4.0, 'rank' => 'Giỏi'];
        if ($grade10 >= 8.0)
            return ['letter' => 'B+', 'scale4' => 3.5, 'rank' => 'Khá'];
        if ($grade10 >= 7.0)
            return ['letter' => 'B', 'scale4' => 3.0, 'rank' => 'Khá'];
        if ($grade10 >= 6.5)
            return ['letter' => 'C+', 'scale4' => 2.5, 'rank' => 'TB'];
        if ($grade10 >= 5.5)
            return ['letter' => 'C', 'scale4' => 2.0, 'rank' => 'TB'];
        if ($grade10 >= 5.0)
            return ['letter' => 'D+', 'scale4' => 1.5, 'rank' => 'TB yếu'];
        if ($grade10 >= 4.0)
            return ['letter' => 'D', 'scale4' => 1.0, 'rank' => 'TB yếu'];
        return ['letter' => 'F', 'scale4' => 0, 'rank' => 'Kém'];
    }

    /**
     * Tính điểm trung bình học kỳ (GPA)
     */
    public static function calculateGPA($studentId, $semesterId)
    {
        $grades = CourseGrade::with('course')
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereNotNull('grade_value')
            ->get();

        if ($grades->isEmpty()) {
            return [
                'gpa_10' => 0,
                'gpa_4' => 0,
                'credits_registered' => 0,
                'credits_passed' => 0
            ];
        }

        $totalCredits = 0;
        $totalGradePoints10 = 0;
        $totalGradePoints4 = 0;
        $creditsPassed = 0;

        foreach ($grades as $grade) {
            $credits = $grade->course->credits;
            $gradeValue = $grade->grade_value;

            $totalCredits += $credits;
            $totalGradePoints10 += $gradeValue * $credits;

            $converted = self::convertGrade($gradeValue);
            $totalGradePoints4 += $converted['scale4'] * $credits;

            if ($gradeValue >= 4.0) {
                $creditsPassed += $credits;
            }
        }

        // Tránh chia cho 0
        if ($totalCredits == 0) {
            return [
                'gpa_10' => 0,
                'gpa_4' => 0,
                'credits_registered' => 0,
                'credits_passed' => 0
            ];
        }

        return [
            'gpa_10' => round($totalGradePoints10 / $totalCredits, 2),
            'gpa_4' => round($totalGradePoints4 / $totalCredits, 2),
            'credits_registered' => $totalCredits,
            'credits_passed' => $creditsPassed
        ];
    }

    /**
     * Tính điểm trung bình tích lũy (CPA)
     */
    public static function calculateCPA($studentId, $upToSemesterId = null)
    {
        $query = CourseGrade::with('course')
            ->where('student_id', $studentId)
            ->whereNotNull('grade_value');

        if ($upToSemesterId) {
            $query->where('semester_id', '<=', $upToSemesterId);
        }

        $grades = $query->get();

        if ($grades->isEmpty()) {
            return [
                'cpa_10' => 0,
                'cpa_4' => 0,
                'total_credits_passed' => 0
            ];
        }

        $totalCredits = 0;
        $totalGradePoints10 = 0;
        $totalGradePoints4 = 0;
        $totalCreditsPassed = 0;

        foreach ($grades as $grade) {
            $credits = $grade->course->credits;
            $gradeValue = $grade->grade_value;

            $totalCredits += $credits;
            $totalGradePoints10 += $gradeValue * $credits;

            $converted = self::convertGrade($gradeValue);
            $totalGradePoints4 += $converted['scale4'] * $credits;

            if ($gradeValue >= 4.0) {
                $totalCreditsPassed += $credits;
            }
        }

        // Tránh chia cho 0
        if ($totalCredits == 0) {
            return [
                'cpa_10' => 0,
                'cpa_4' => 0,
                'total_credits_passed' => 0
            ];
        }

        return [
            'cpa_10' => round($totalGradePoints10 / $totalCredits, 2),
            'cpa_4' => round($totalGradePoints4 / $totalCredits, 2),
            'total_credits_passed' => $totalCreditsPassed
        ];
    }

    /**
     * Cập nhật điểm chữ và thang 4 cho CourseGrade
     */
    public static function updateCourseGradeConversions($studentId, $semesterId)
    {
        $grades = CourseGrade::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereNotNull('grade_value')
            ->get();

        foreach ($grades as $grade) {
            $converted = self::convertGrade($grade->grade_value);

            $grade->grade_letter = $converted['letter'];
            $grade->grade_4_scale = $converted['scale4'];
            $grade->status = $grade->grade_value >= 4.0 ? 'passed' : 'failed';
            $grade->save();
        }

        return true;
    }

    /**
     * Tạo hoặc cập nhật báo cáo học kỳ
     */
    public static function updateSemesterReport($studentId, $semesterId)
    {
        DB::beginTransaction();
        try {
            // Cập nhật điểm chữ và thang 4 cho các môn
            self::updateCourseGradeConversions($studentId, $semesterId);

            // Tính GPA học kỳ
            $gpaData = self::calculateGPA($studentId, $semesterId);

            // Tính CPA tích lũy (tính đến hết học kỳ này)
            $cpaData = self::calculateCPA($studentId, $semesterId);

            // Lấy điểm rèn luyện (theo kỳ) và CTXH (tích lũy)
            $trainingPoints = PointCalculationService::calculateTrainingPoints($studentId, $semesterId);
            $socialPoints = PointCalculationService::calculateSocialPoints($studentId, $semesterId);
            // === KẾT THÚC SỬA ===

            // Xác định kết quả học kỳ
            $outcome = self::determineOutcome($studentId, $semesterId, $cpaData['cpa_4']);

            // Tạo hoặc cập nhật báo cáo
            $report = SemesterReport::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'semester_id' => $semesterId
                ],
                [
                    'gpa' => $gpaData['gpa_10'],
                    'gpa_4_scale' => $gpaData['gpa_4'],
                    'cpa_10_scale' => $cpaData['cpa_10'],
                    'cpa_4_scale' => $cpaData['cpa_4'],
                    'credits_registered' => $gpaData['credits_registered'],
                    'credits_passed' => $gpaData['credits_passed'],
                    'training_point_summary' => $trainingPoints, // <-- Đã cập nhật
                    'social_point_summary' => $socialPoints,   // <-- Đã cập nhật
                    'outcome' => $outcome
                ]
            );

            DB::commit();
            return $report;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Xác định ngưỡng cảnh cáo học vụ theo năm học
     */
    public static function getWarningThreshold($studentId, $semesterId)
    {
        $completedSemesters = SemesterReport::where('student_id', $studentId)
            ->where('semester_id', '<=', $semesterId)
            ->count();

        // Đếm số học kỳ đã có báo cáo
        // (Nếu $completedSemesters = 0, nghĩa là đây là kỳ đầu tiên, $year = 1)
        $year = ceil(($completedSemesters + 1) / 2);

        if ($year == 1)
            return 1.20;
        if ($year == 2)
            return 1.40;
        if ($year == 3)
            return 1.60;
        return 1.80;
    }

    /**
     * Xác định kết quả học kỳ và cảnh báo
     */
    public static function determineOutcome($studentId, $semesterId, $cpa4)
    {
        $threshold = self::getWarningThreshold($studentId, $semesterId);

        if ($cpa4 < $threshold) {
            $level = self::getWarningLevel($cpa4, $threshold);
            return "Cảnh cáo học vụ mức {$level} (CPA: {$cpa4}, Ngưỡng: {$threshold})";
        }
        if ($cpa4 >= 3.6) {
            return "Học tiếp (Khen thưởng - Xuất sắc)";
        }
        if ($cpa4 >= 3.2) {
            return "Học tiếp (Khen thưởng)";
        }

        return "Học tiếp";
    }

    /**
     * Xác định mức độ cảnh cáo
     */
    public static function getWarningLevel($cpa4, $threshold)
    {
        $gap = $threshold - $cpa4;

        if ($gap >= 0.5)
            return 3; // Cảnh cáo nặng
        if ($gap >= 0.3)
            return 2; // Cảnh cáo vừa
        return 1; // Cảnh cáo nhẹ
    }

    /**
     * Kiểm tra sinh viên có nguy cơ bỏ học không
     */
    public static function checkDropoutRisk($studentId, $semesterId)
    {
        $report = SemesterReport::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->first();

        if (!$report) {
            return [
                'is_at_risk' => false,
                'risk_level' => 'none',
                'reasons' => []
            ];
        }

        $threshold = self::getWarningThreshold($studentId, $semesterId);
        $risks = [];
        $riskLevel = 'low';

        // Kiểm tra CPA
        if ($report->cpa_4_scale < $threshold) {
            $risks[] = "CPA ({$report->cpa_4_scale}) dưới ngưỡng cảnh cáo ({$threshold})";
            $riskLevel = 'medium';
        }

        // Kiểm tra GPA học kỳ
        if ($report->gpa_4_scale < 1.0) { // Dùng GPA hệ 4 để so sánh
            $risks[] = "GPA học kỳ quá thấp ({$report->gpa_4_scale})";
            $riskLevel = 'high';
        }

        // Kiểm tra số môn rớt
        $failedCount = CourseGrade::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('status', 'failed')
            ->count();

        if ($failedCount >= 3) {
            $risks[] = "Rớt {$failedCount} môn trong học kỳ";
            $riskLevel = 'high';
        }

        // Kiểm tra tỷ lệ tín chỉ đạt
        if ($report->credits_registered > 0) {
            $passRate = ($report->credits_passed / $report->credits_registered) * 100;
            if ($passRate < 50) {
                $risks[] = "Tỷ lệ tín chỉ đạt thấp (" . round($passRate, 2) . "%)";
                $riskLevel = 'high';
            }
        } else {
            $risks[] = "Không đăng ký tín chỉ nào trong học kỳ";
            $riskLevel = 'high';
        }


        return [
            'is_at_risk' => !empty($risks),
            'risk_level' => $riskLevel,
            'reasons' => $risks,
            'failed_courses_count' => $failedCount,
            'cpa_4' => $report->cpa_4_scale,
            'threshold' => $threshold
        ];
    }

    /**
     * Tự động phát hiện và cảnh báo sinh viên bỏ học
     */
    public static function autoDetectAndWarn($advisorId, $semesterId)
    {
        $semester = Semester::find($semesterId);
        if (!$semester) {
            throw new \Exception("Học kỳ không tồn tại");
        }

        // Lấy danh sách sinh viên trong lớp của advisor
        $students = Student::whereHas('class', function ($query) use ($advisorId) {
            $query->where('advisor_id', $advisorId);
        })->get();

        $warnings = [];
        $atRiskStudents = [];

        foreach ($students as $student) {
            // Cập nhật báo cáo học kỳ trước khi kiểm tra
            // (Đảm bảo dữ liệu là mới nhất)
            self::updateSemesterReport($student->student_id, $semesterId);

            $riskInfo = self::checkDropoutRisk($student->student_id, $semesterId);

            if ($riskInfo['is_at_risk'] && in_array($riskInfo['risk_level'], ['medium', 'high'])) {
                $atRiskStudents[] = [
                    'student' => $student,
                    'risk_info' => $riskInfo
                ];

                // Tự động tạo cảnh cáo nếu chưa có
                $existingWarning = AcademicWarning::where('student_id', $student->student_id)
                    ->where('semester_id', $semesterId)
                    ->first();

                if (!$existingWarning) {
                    $warning = AcademicWarning::create([
                        'student_id' => $student->student_id,
                        'advisor_id' => $advisorId,
                        'semester_id' => $semesterId,
                        'title' => "Cảnh báo học vụ tự động - {$semester->semester_name} {$semester->academic_year}",
                        'content' => "Sinh viên {$student->full_name} (MSSV: {$student->user_code}) có nguy cơ học vụ. " .
                            "Lý do: " . implode("; ", $riskInfo['reasons']),
                        'advice' => self::generateAdvice($riskInfo)
                    ]);

                    $warnings[] = $warning;
                }
            }
        }

        return [
            'at_risk_students' => $atRiskStudents,
            'warnings_created' => $warnings,
            'summary' => [
                'total_at_risk' => count($atRiskStudents),
                'high_risk' => collect($atRiskStudents)->where('risk_info.risk_level', 'high')->count(),
                'medium_risk' => collect($atRiskStudents)->where('risk_info.risk_level', 'medium')->count()
            ]
        ];
    }

    /**
     * Tạo lời khuyên dựa trên thông tin rủi ro
     */
    private static function generateAdvice($riskInfo)
    {
        $advice = "Sinh viên cần:\n";

        if ($riskInfo['cpa_4'] < $riskInfo['threshold']) {
            $advice .= "- Nâng cao điểm CPA bằng cách học tập chăm chỉ hơn.\n";
            $advice .= "- Đăng ký học lại các môn điểm thấp để cải thiện CPA.\n";
        }

        if ($riskInfo['failed_courses_count'] > 0) {
            $advice .= "- Đăng ký học lại {$riskInfo['failed_courses_count']} môn bị rớt ngay trong học kỳ tới.\n";
        }

        if (in_array('Không đăng ký tín chỉ nào trong học kỳ', $riskInfo['reasons'])) {
            $advice .= "- Liên hệ ngay với phòng đào tạo và CVHT để làm rõ lý do không đăng ký học tập.\n";
        }

        $advice .= "- Gặp cố vấn học tập để được tư vấn chi tiết về kế hoạch học tập.\n";

        if ($riskInfo['risk_level'] === 'high') {
            $advice .= "\nCẢNH BÁO: Nguy cơ bị buộc thôi học rất cao. Cần có hành động khắc phục ngay lập tức!";
        }

        return $advice;
    }

    /**
     * Phân loại học lực theo GPA (hệ 10)
     * @param \Illuminate\Support\Collection $reports - Collection các SemesterReport
     * @return array - Mảng thống kê học lực
     */
    public static function classifyAcademicPerformance($reports)
    {
        return [
            'excellent' => $reports->where('gpa', '>=', 9.0)->count(), // Xuất sắc: >= 9.0
            'good' => $reports->whereBetween('gpa', [8.0, 8.99])->count(), // Giỏi: 8.0 - 8.99
            'fair' => $reports->whereBetween('gpa', [7.0, 7.99])->count(), // Khá: 7.0 - 7.99
            'average' => $reports->whereBetween('gpa', [5.0, 6.99])->count(), // Trung bình: 5.0 - 6.99
            'weak' => $reports->where('gpa', '<', 5.0)->count() // Yếu: < 5.0
        ];
    }
}