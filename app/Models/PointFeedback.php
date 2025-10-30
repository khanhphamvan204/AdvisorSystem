<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointFeedback extends Model
{
    protected $primaryKey = 'feedback_id';
    protected $fillable = [
        'student_id',
        'semester_id',
        'feedback_content',
        'attachment_path',
        'status',
        'advisor_response',
        'advisor_id',
        'response_at'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'user_id');
    }
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }
    public function advisor()
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'user_id');
    }
}