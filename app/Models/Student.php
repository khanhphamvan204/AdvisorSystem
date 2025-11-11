<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model implements JWTSubject
{
    protected $table = 'Students';
    public $timestamps = false;
    protected $primaryKey = 'student_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_code',
        'full_name',
        'email',
        'password_hash',
        'phone_number',
        'avatar_url',
        'class_id',
        'status'
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'last_login' => 'datetime',
        'status' => 'string',
    ];

    // (Các hàm quan hệ của bạn giữ nguyên)
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id', 'class_id');
    }

    public function courseGrades(): HasMany
    {
        return $this->hasMany(CourseGrade::class, 'student_id', 'student_id');
    }

    public function semesterReports(): HasMany
    {
        return $this->hasMany(SemesterReport::class, 'student_id', 'student_id');
    }

    public function academicWarnings(): HasMany
    {
        return $this->hasMany(AcademicWarning::class, 'student_id', 'student_id');
    }

    public function pointFeedbacks(): HasMany
    {
        return $this->hasMany(PointFeedback::class, 'student_id', 'student_id');
    }

    public function activityRegistrations(): HasMany
    {
        return $this->hasMany(ActivityRegistration::class, 'student_id', 'student_id');
    }

    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class, 'student_id', 'student_id');
    }

    public function notificationResponses(): HasMany
    {
        return $this->hasMany(NotificationResponse::class, 'student_id', 'student_id');
    }

    public function meetingStudents(): HasMany
    {
        return $this->hasMany(MeetingStudent::class, 'student_id', 'student_id');
    }

    public function meetingFeedbacks(): HasMany
    {
        return $this->hasMany(MeetingFeedback::class, 'student_id', 'student_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'student_id', 'student_id')
            ->where('sender_type', 'student');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'student_id', 'student_id')
            ->where('sender_type', 'advisor');
    }

    public function monitoringNotes(): HasMany
    {
        return $this->hasMany(StudentMonitoringNote::class, 'student_id', 'student_id');
    }

    // === HÀM JWT (CỦA BẠN ĐÃ ĐÚNG) ===
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->student_id,
            'role' => 'student',
            'name' => $this->full_name
        ];
    }
}