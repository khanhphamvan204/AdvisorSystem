<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemesterReport extends Model
{
    protected $table = 'Semester_Reports';
    public $timestamps = false;
    protected $primaryKey = 'report_id';

    protected $fillable = [
        'student_id',
        'semester_id',
        'gpa',
        'gpa_4_scale',
        'cpa_10_scale',
        'cpa_4_scale',
        'credits_registered',
        'credits_passed',
        'training_point_summary',
        'social_point_summary',
        'outcome'
    ];

    protected $casts = [
        'gpa' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }
}