<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $primaryKey = 'semester_id';
    protected $fillable = ['semester_name', 'academic_year', 'start_date', 'end_date'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function grades()
    {
        return $this->hasMany(CourseGrade::class, 'semester_id');
    }
    public function reports()
    {
        return $this->hasMany(SemesterReport::class, 'semester_id');
    }
    public function warnings()
    {
        return $this->hasMany(AcademicWarning::class, 'semester_id');
    }
    public function feedbacks()
    {
        return $this->hasMany(PointFeedback::class, 'semester_id');
    }
    public function monitoringNotes()
    {
        return $this->hasMany(StudentMonitoringNote::class, 'semester_id');
    }
}