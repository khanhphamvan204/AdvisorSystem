<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingFeedback extends Model
{
    protected $primaryKey = 'feedback_id';
    protected $fillable = ['meeting_id', 'student_id', 'feedback_content'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'user_id');
    }
}