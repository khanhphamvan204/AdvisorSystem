<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationResponse extends Model
{
    protected $primaryKey = 'response_id';
    public $timestamps = false;
    protected $fillable = [
        'notification_id',
        'student_id',
        'content',
        'status',
        'advisor_response',
        'advisor_id',
        'response_at'
    ];

    protected $casts = [
        'response_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'user_id');
    }

    public function advisor()
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'user_id');
    }
    public function advisorUser()
    {
        return $this->belongsTo(User::class, 'advisor_id', 'user_id');
    }
}