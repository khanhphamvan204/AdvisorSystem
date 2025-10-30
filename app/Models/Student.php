<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['user_id', 'class_id', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }
    public function grades()
    {
        return $this->hasMany(CourseGrade::class, 'student_id', 'user_id');
    }
    public function reports()
    {
        return $this->hasMany(SemesterReport::class, 'student_id', 'user_id');
    }
    public function warnings()
    {
        return $this->hasMany(AcademicWarning::class, 'student_id', 'user_id');
    }
    public function feedbacks()
    {
        return $this->hasMany(PointFeedback::class, 'student_id', 'user_id');
    }
    public function registrations()
    {
        return $this->hasMany(ActivityRegistration::class, 'student_id', 'user_id');
    }
    public function cancellationRequests()
    {
        return $this->hasMany(CancellationRequest::class, 'registration_id');
    }
    public function notificationRecipients()
    {
        return $this->hasMany(NotificationRecipient::class, 'student_id', 'user_id');
    }
    public function notificationResponses()
    {
        return $this->hasMany(NotificationResponse::class, 'student_id', 'user_id');
    }
    public function meetingAttendance()
    {
        return $this->hasMany(MeetingStudent::class, 'student_id', 'user_id');
    }
    public function meetingFeedbacks()
    {
        return $this->hasMany(MeetingFeedback::class, 'student_id', 'user_id');
    }
    public function monitoringNotes()
    {
        return $this->hasMany(StudentMonitoringNote::class, 'student_id', 'user_id');
    }
}