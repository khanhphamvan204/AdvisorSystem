<?php

namespace App\Services;

use App\Models\Student;
use App\Models\ActivityRegistration;
use App\Models\ActivityRole;
use App\Models\Activity;
use App\Models\Semester;
use App\Models\SemesterReport;
use Illuminate\Support\Facades\DB;

class PointCalculationService
{
    /**
     * Tính điểm rèn luyện (DRL) cho một học kỳ cụ thể
     * Điểm ban đầu: 70
     * Attended: Cộng điểm
     * Absent: Trừ điểm
     * Registered: Không tính (chưa điểm danh)
     * 
     * @param int $studentId
     * @param int $semesterId
     * @return int
     */
    public static function calculateTrainingPoints($studentId, $semesterId)
    {
        // Lấy thông tin học kỳ
        $semester = Semester::find($semesterId);
        if (!$semester) {
            throw new \Exception("Học kỳ không tồn tại");
        }

        // Điểm ban đầu
        $basePoints = 70;

        // Lấy các hoạt động sinh viên đã tham gia hoặc vắng mặt TRONG HỌC KỲ
        // và có point_type = 'ren_luyen'
        $registrations = ActivityRegistration::where('student_id', $studentId)
            ->whereIn('status', ['attended', 'absent']) // Tính cả attended và absent
            ->whereHas('role', function ($query) {
                $query->where('point_type', 'ren_luyen');
            })
            ->whereHas('role.activity', function ($query) use ($semester) {
                // Hoạt động phải diễn ra trong khoảng thời gian học kỳ
                $query->whereBetween('start_time', [
                    $semester->start_date,
                    $semester->end_date
                ]);
            })
            ->with('role')
            ->get();

        // Tính tổng điểm: attended cộng, absent trừ
        $adjustmentPoints = $registrations->sum(function ($registration) {
            if ($registration->status === 'attended') {
                return $registration->role->points_awarded; // Cộng điểm
            } elseif ($registration->status === 'absent') {
                return -$registration->role->points_awarded; // Trừ điểm
            }
            return 0;
        });

        $totalPoints = $basePoints + $adjustmentPoints;

        // Đảm bảo điểm không âm
        return max(0, $totalPoints);
    }

    /**
     * Tính điểm công tác xã hội (CTXH) tích lũy từ đầu khóa học
     * Lấy TẤT CẢ các hoạt động từ khi sinh viên nhập học
     * 
     * @param int $studentId
     * @param int|null $upToSemesterId (tính đến học kỳ nào, null = tất cả)
     * @return int
     */
    public static function calculateSocialPoints($studentId, $upToSemesterId = null)
    {
        $query = ActivityRegistration::where('student_id', $studentId)
            ->where('status', 'attended')
            ->whereHas('role', function ($query) {
                $query->where('point_type', 'ctxh');
            });

        // Nếu chỉ định học kỳ, lấy tất cả hoạt động đến hết học kỳ đó
        if ($upToSemesterId) {
            $semester = Semester::find($upToSemesterId);
            if ($semester) {
                $query->whereHas('role.activity', function ($q) use ($semester) {
                    $q->where('start_time', '<=', $semester->end_date);
                });
            }
        }

        $registrations = $query->with('role')->get();

        // Tính tổng điểm CTXH tích lũy
        $totalPoints = $registrations->sum(function ($registration) {
            return $registration->role->points_awarded;
        });

        return $totalPoints;
    }

    /**
     * Tính chi tiết điểm cho một học kỳ
     * 
     * @param int $studentId
     * @param int $semesterId
     * @return array
     */
    public static function calculateSemesterPoints($studentId, $semesterId)
    {
        $semester = Semester::find($semesterId);
        if (!$semester) {
            throw new \Exception("Học kỳ không tồn tại");
        }

        // Điểm rèn luyện trong học kỳ
        $trainingPoints = self::calculateTrainingPoints($studentId, $semesterId);

        // Điểm CTXH tích lũy (từ đầu khóa đến hết học kỳ này)
        $socialPoints = self::calculateSocialPoints($studentId, $semesterId);

        // Lấy danh sách hoạt động chi tiết
        $trainingActivities = self::getTrainingActivitiesDetail($studentId, $semesterId);
        $socialActivities = self::getSocialActivitiesDetail($studentId, $semesterId);

        // Tính số hoạt động attended và absent
        $attendedCount = collect($trainingActivities)->where('status', 'attended')->count();
        $absentCount = collect($trainingActivities)->where('status', 'absent')->count();

        return [
            'training_points' => $trainingPoints,
            'social_points' => $socialPoints,
            'training_activities' => $trainingActivities,
            'social_activities' => $socialActivities,
            'summary' => [
                'base_training_points' => 70,
                'total_training_activities' => count($trainingActivities),
                'attended_activities' => $attendedCount,
                'absent_activities' => $absentCount,
                'total_social_activities' => count($socialActivities),
                'semester' => $semester->semester_name . ' ' . $semester->academic_year
            ]
        ];
    }

    /**
     * Lấy chi tiết các hoạt động rèn luyện trong học kỳ
     * Bao gồm cả hoạt động tham dự (attended) và vắng mặt (absent)
     */
    public static function getTrainingActivitiesDetail($studentId, $semesterId)
    {
        $semester = Semester::find($semesterId);

        $registrations = ActivityRegistration::where('student_id', $studentId)
            ->whereIn('status', ['attended', 'absent']) // Lấy cả attended và absent
            ->whereHas('role', function ($query) {
                $query->where('point_type', 'ren_luyen');
            })
            ->whereHas('role.activity', function ($query) use ($semester) {
                $query->whereBetween('start_time', [
                    $semester->start_date,
                    $semester->end_date
                ]);
            })
            ->with(['role.activity'])
            ->orderBy('registration_time', 'desc')
            ->get();

        return $registrations->map(function ($reg) {
            $pointsAwarded = $reg->role->points_awarded;
            // Nếu vắng mặt thì điểm là âm
            $actualPoints = $reg->status === 'attended' ? $pointsAwarded : -$pointsAwarded;

            return [
                'registration_id' => $reg->registration_id,
                'activity_id' => $reg->role->activity->activity_id,
                'activity_title' => $reg->role->activity->title,
                'role_name' => $reg->role->role_name,
                'status' => $reg->status,
                'status_text' => $reg->status === 'attended' ? 'Đã tham dự' : 'Vắng mặt',
                'points_awarded' => $pointsAwarded,
                'actual_points' => $actualPoints,
                'activity_date' => $reg->role->activity->start_time->format('d/m/Y'),
                'location' => $reg->role->activity->location,
                'registration_time' => $reg->registration_time->format('d/m/Y H:i')
            ];
        })->toArray();
    }

    /**
     * Lấy chi tiết TẤT CẢ các hoạt động CTXH từ đầu khóa đến học kỳ
     */
    public static function getSocialActivitiesDetail($studentId, $upToSemesterId = null)
    {
        $query = ActivityRegistration::where('student_id', $studentId)
            ->where('status', 'attended')
            ->whereHas('role', function ($query) {
                $query->where('point_type', 'ctxh');
            });

        if ($upToSemesterId) {
            $semester = Semester::find($upToSemesterId);
            if ($semester) {
                $query->whereHas('role.activity', function ($q) use ($semester) {
                    $q->where('start_time', '<=', $semester->end_date);
                });
            }
        }

        $registrations = $query->with(['role.activity'])->get();

        return $registrations->map(function ($reg) {
            return [
                'activity_id' => $reg->role->activity->activity_id,
                'activity_title' => $reg->role->activity->title,
                'role_name' => $reg->role->role_name,
                'points_awarded' => $reg->role->points_awarded,
                'activity_date' => $reg->role->activity->start_time->format('d/m/Y'),
                'location' => $reg->role->activity->location,
                'registration_time' => $reg->registration_time->format('d/m/Y H:i')
            ];
        })->toArray();
    }

    /**
     * Cập nhật điểm vào báo cáo học kỳ
     * 
     * @param int $studentId
     * @param int $semesterId
     * @return SemesterReport
     */
    public static function updatePointsInSemesterReport($studentId, $semesterId)
    {
        $trainingPoints = self::calculateTrainingPoints($studentId, $semesterId);
        $socialPoints = self::calculateSocialPoints($studentId, $semesterId);

        // Tìm hoặc tạo báo cáo học kỳ
        $report = SemesterReport::firstOrNew([
            'student_id' => $studentId,
            'semester_id' => $semesterId
        ]);

        $report->training_point_summary = $trainingPoints;
        $report->social_point_summary = $socialPoints;
        $report->save();

        return $report;
    }

    /**
     * Tính điểm cho tất cả sinh viên trong một học kỳ
     * Sử dụng cho việc cập nhật hàng loạt
     * 
     * @param int $semesterId
     * @param array|null $studentIds (null = tất cả sinh viên)
     * @return array
     */
    public static function batchUpdateSemesterPoints($semesterId, $studentIds = null)
    {
        $semester = Semester::find($semesterId);
        if (!$semester) {
            throw new \Exception("Học kỳ không tồn tại");
        }

        // Lấy danh sách sinh viên
        $query = Student::query();
        if ($studentIds) {
            $query->whereIn('student_id', $studentIds);
        }
        $students = $query->get();

        $results = [
            'success' => [],
            'errors' => [],
            'summary' => [
                'total_processed' => 0,
                'success_count' => 0,
                'error_count' => 0
            ]
        ];

        foreach ($students as $student) {
            try {
                $report = self::updatePointsInSemesterReport(
                    $student->student_id,
                    $semesterId
                );

                $results['success'][] = [
                    'student_id' => $student->student_id,
                    'user_code' => $student->user_code,
                    'full_name' => $student->full_name,
                    'training_points' => $report->training_point_summary,
                    'social_points' => $report->social_point_summary
                ];

                $results['summary']['success_count']++;

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'student_id' => $student->student_id,
                    'error' => $e->getMessage()
                ];
                $results['summary']['error_count']++;
            }

            $results['summary']['total_processed']++;
        }

        return $results;
    }

    /**
     * Lấy lịch sử tham gia hoạt động của sinh viên
     * 
     * @param int $studentId
     * @param string|null $pointType ('ren_luyen', 'ctxh', hoặc null = tất cả)
     * @return array
     */
    public static function getActivityHistory($studentId, $pointType = null)
    {
        $query = ActivityRegistration::where('student_id', $studentId)
            ->whereIn('status', ['attended', 'registered']);

        if ($pointType) {
            $query->whereHas('role', function ($q) use ($pointType) {
                $q->where('point_type', $pointType);
            });
        }

        $registrations = $query->with(['role.activity'])
            ->orderBy('registration_time', 'desc')
            ->get();

        return $registrations->map(function ($reg) {
            $pointsAwarded = $reg->role->points_awarded;
            $actualPoints = 0;
            $isCounted = false;

            // Với điểm rèn luyện: attended cộng, absent trừ
            if ($reg->role->point_type === 'ren_luyen') {
                if ($reg->status === 'attended') {
                    $actualPoints = $pointsAwarded;
                    $isCounted = true;
                } elseif ($reg->status === 'absent') {
                    $actualPoints = -$pointsAwarded;
                    $isCounted = true;
                }
            } else {
                // CTXH: chỉ cộng khi attended
                if ($reg->status === 'attended') {
                    $actualPoints = $pointsAwarded;
                    $isCounted = true;
                }
            }

            return [
                'registration_id' => $reg->registration_id,
                'activity_id' => $reg->role->activity->activity_id,
                'activity_title' => $reg->role->activity->title,
                'role_name' => $reg->role->role_name,
                'point_type' => $reg->role->point_type,
                'points_awarded' => $pointsAwarded,
                'actual_points' => $actualPoints,
                'status' => $reg->status,
                'activity_date' => $reg->role->activity->start_time->format('d/m/Y H:i'),
                'location' => $reg->role->activity->location,
                'registration_time' => $reg->registration_time->format('d/m/Y H:i'),
                'is_counted' => $isCounted
            ];
        })->toArray();
    }

    /**
     * Tính tổng điểm tích lũy của sinh viên (cả DRL và CTXH)
     * 
     * @param int $studentId
     * @param int|null $upToSemesterId
     * @return array
     */
    public static function getTotalAccumulatedPoints($studentId, $upToSemesterId = null)
    {
        // CTXH: Tích lũy từ đầu
        $socialPoints = self::calculateSocialPoints($studentId, $upToSemesterId);

        // DRL: Tổng của tất cả các học kỳ
        $trainingPointsBySemester = [];
        $totalTrainingPoints = 0;

        $query = Semester::orderBy('start_date', 'asc');
        if ($upToSemesterId) {
            $query->where('semester_id', '<=', $upToSemesterId);
        }
        $semesters = $query->get();

        foreach ($semesters as $semester) {
            $points = self::calculateTrainingPoints($studentId, $semester->semester_id);
            $trainingPointsBySemester[] = [
                'semester_id' => $semester->semester_id,
                'semester_name' => $semester->semester_name,
                'academic_year' => $semester->academic_year,
                'training_points' => $points
            ];
            $totalTrainingPoints += $points;
        }

        return [
            'student_id' => $studentId,
            'total_social_points' => $socialPoints,
            'total_training_points' => $totalTrainingPoints,
            'training_points_by_semester' => $trainingPointsBySemester,
            'summary' => [
                'total_points' => $totalTrainingPoints + $socialPoints,
                'total_semesters' => count($trainingPointsBySemester)
            ]
        ];
    }
}