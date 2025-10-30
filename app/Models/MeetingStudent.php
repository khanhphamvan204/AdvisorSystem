<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingStudent extends Model
{
    protected $primaryKey = 'meeting_student_id';
    protected $fillable = ['meeting_id', 'student_id', 'attended'];

    protected $casts = [
        'attended' => 'boolean',
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