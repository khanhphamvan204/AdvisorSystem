<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicWarning extends Model
{
    protected $table = 'Academic_Warnings';
    protected $primaryKey = 'warning_id';
    public $timestamps = false;

    protected $fillable = ['student_id', 'advisor_id', 'semester_id', 'title', 'content', 'advice'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'advisor_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }
}