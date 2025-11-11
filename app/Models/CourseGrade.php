<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseGrade extends Model
{
    protected $table = 'Course_Grades';
    public $timestamps = false;
    protected $primaryKey = 'grade_id';

    protected $fillable = [
        'student_id',
        'course_id',
        'semester_id',
        'grade_value',
        'status',
        'grade_letter',
        'grade_4_scale'
    ];

    protected $casts = [
        'grade_value' => 'decimal:2',
        'status' => 'string',
        'grade_letter' => 'string',
        'grade_4_scale' => 'decimal:2'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }
}