<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicWarning extends Model
{
    protected $primaryKey = 'warning_id';
    protected $fillable = ['student_id', 'advisor_id', 'semester_id', 'title', 'content', 'advice'];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'user_id');
    }
    public function advisor()
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'user_id');
    }
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }
}