<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semester extends Model
{
    protected $table = 'Semesters';
    public $timestamps = false;
    protected $primaryKey = 'semester_id';

    protected $fillable = ['semester_name', 'academic_year', 'start_date', 'end_date'];

    protected $dates = ['start_date', 'end_date'];

    public function courseGrades(): HasMany
    {
        return $this->hasMany(CourseGrade::class, 'semester_id', 'semester_id');
    }

    public function semesterReports(): HasMany
    {
        return $this->hasMany(SemesterReport::class, 'semester_id', 'semester_id');
    }

    public function academicWarnings(): HasMany
    {
        return $this->hasMany(AcademicWarning::class, 'semester_id', 'semester_id');
    }

    public function pointFeedbacks(): HasMany
    {
        return $this->hasMany(PointFeedback::class, 'semester_id', 'semester_id');
    }

    public function monitoringNotes(): HasMany
    {
        return $this->hasMany(StudentMonitoringNote::class, 'semester_id', 'semester_id');
    }
}