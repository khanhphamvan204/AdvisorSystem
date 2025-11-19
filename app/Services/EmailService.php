<?php

namespace App\Services;

use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Gửi email thông báo cho sinh viên
     */
    public function sendNotificationEmail($student, $notification)
    {
        try {
            $data = [
                'type' => 'notification',
                'subject' => 'Thông báo: ' . $notification->title,
                'studentName' => $student->full_name,
                'notificationTitle' => $notification->title,
                'notificationContent' => $notification->summary,
                'notificationLink' => $notification->link ?? url('/notifications/' . $notification->notification_id),
            ];

            Mail::to($student->email)->send(new NotificationMail($data));

            Log::info('Email notification sent', [
                'student_id' => $student->student_id,
                'notification_id' => $notification->notification_id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send notification email', [
                'student_id' => $student->student_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Gửi email về hoạt động mới
     */
    public function sendActivityEmail($student, $activity, $role = null)
    {
        try {
            $data = [
                'type' => 'activity',
                'subject' => 'Hoạt động mới: ' . $activity->title,
                'studentName' => $student->full_name,
                'activityTitle' => $activity->title,
                'activityDescription' => $activity->general_description,
                'activityLocation' => $activity->location,
                'activityTime' => $activity->start_time ? $activity->start_time->format('H:i d/m/Y') : null,
                'activityPoints' => $role ? $role->points_awarded : null,
                'activityLink' => url('/activities/' . $activity->activity_id),
            ];

            Mail::to($student->email)->send(new NotificationMail($data));

            Log::info('Activity email sent', [
                'student_id' => $student->student_id,
                'activity_id' => $activity->activity_id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send activity email', [
                'student_id' => $student->student_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Gửi email cảnh báo học vụ
     */
    public function sendAcademicWarningEmail($student, $warning)
    {
        try {
            $data = [
                'type' => 'warning',
                'subject' => 'Cảnh báo học vụ: ' . $warning->title,
                'studentName' => $student->full_name,
                'warningTitle' => $warning->title,
                'warningContent' => $warning->content,
                'warningAdvice' => $warning->advice,
            ];

            Mail::to($student->email)->send(new NotificationMail($data));

            Log::info('Warning email sent', [
                'student_id' => $student->student_id,
                'warning_id' => $warning->warning_id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send warning email', [
                'student_id' => $student->student_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Gửi email về cuộc họp lớp
     */
    public function sendMeetingEmail($student, $meeting)
    {
        try {
            $data = [
                'type' => 'meeting',
                'subject' => 'Thông báo họp lớp: ' . $meeting->title,
                'studentName' => $student->full_name,
                'meetingTitle' => $meeting->title,
                'meetingSummary' => $meeting->summary,
                'meetingLocation' => $meeting->location,
                'meetingTime' => $meeting->meeting_time ? $meeting->meeting_time->format('H:i d/m/Y') : '',
                'meetingLink' => $meeting->meeting_link ?? url('/meetings/' . $meeting->meeting_id),
            ];

            Mail::to($student->email)->send(new NotificationMail($data));

            Log::info('Meeting email sent', [
                'student_id' => $student->student_id,
                'meeting_id' => $meeting->meeting_id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send meeting email', [
                'student_id' => $student->student_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Gửi email hàng loạt cho nhiều sinh viên
     */
    public function sendBulkEmails($students, $type, $data)
    {
        $successCount = 0;
        $failCount = 0;

        foreach ($students as $student) {
            try {
                switch ($type) {
                    case 'notification':
                        $result = $this->sendNotificationEmail($student, $data);
                        break;
                    case 'activity':
                        $result = $this->sendActivityEmail($student, $data);
                        break;
                    case 'warning':
                        $result = $this->sendAcademicWarningEmail($student, $data);
                        break;
                    case 'meeting':
                        $result = $this->sendMeetingEmail($student, $data);
                        break;
                    default:
                        $result = false;
                }

                if ($result) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (\Exception $e) {
                $failCount++;
                Log::error('Bulk email failed for student', [
                    'student_id' => $student->student_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => $successCount,
            'failed' => $failCount,
            'total' => count($students)
        ];
    }
}