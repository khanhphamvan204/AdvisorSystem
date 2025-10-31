<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMonitoringNote extends Model
{
    protected $table = 'Student_Monitoring_Notes';
    public $timestamps = false;
    protected $primaryKey = 'note_id';

    protected $fillable = [
        'student_id',
        'advisor_id',
        'semester_id',
        'category',
        'title',
        'content'
    ];

    protected $casts = [
        'category' => 'string',
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