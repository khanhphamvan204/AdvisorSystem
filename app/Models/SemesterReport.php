<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SemesterReport extends Model
{
    protected $primaryKey = 'report_id';
    protected $fillable = [
        'student_id',
        'semester_id',
        'gpa',
        'credits_registered',
        'credits_passed',
        'training_point_summary',
        'social_point_summary',
        'outcome'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'user_id');
    }
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }
}