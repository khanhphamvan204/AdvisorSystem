<?php

namespace App\Jobs;

use App\Services\EmailService;
use App\Models\Student;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Số lần tự động retry nếu job thất bại
     */
    public $tries = 3;

    /**
     * Timeout cho job (giây)
     */
    public $timeout = 60;

    /**
     * Student ID để gửi email
     */
    protected $studentId;

    /**
     * Notification ID để gửi email
     */
    protected $notificationId;

    /**
     * Create a new job instance.
     */
    public function __construct($student, $notification)
    {
        // Lưu chỉ ID thay vì toàn bộ object để tránh serialization issues
        $this->studentId = is_object($student) ? $student->student_id : $student['student_id'];
        $this->notificationId = is_object($notification) ? $notification->notification_id : $notification['notification_id'];
    }

    /**
     * Execute the job.
     */
    public function handle(EmailService $emailService): void
    {
        try {
            // Query fresh data từ database
            $student = Student::find($this->studentId);
            $notification = Notification::find($this->notificationId);

            // Kiểm tra data còn tồn tại
            if (!$student) {
                Log::error('Queue job: Student not found', [
                    'student_id' => $this->studentId,
                ]);
                return;
            }

            if (!$notification) {
                Log::error('Queue job: Notification not found', [
                    'notification_id' => $this->notificationId,
                ]);
                return;
            }

            // Gửi email
            $emailService->sendNotificationEmail($student, $notification);

            Log::info('Queue job: Email sent successfully', [
                'student_id' => $student->student_id,
                'notification_id' => $notification->notification_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Queue job: Failed to send email', [
                'student_id' => $this->studentId,
                'notification_id' => $this->notificationId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw exception để Laravel có thể retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Queue job: Permanently failed after all retries', [
            'student_id' => $this->studentId,
            'notification_id' => $this->notificationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
