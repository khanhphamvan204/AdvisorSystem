<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentMonitoringNote extends Model
{
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