<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingStudent extends Model
{
    protected $table = 'Meeting_Student';
    public $timestamps = false;
    protected $primaryKey = 'meeting_student_id';

    protected $fillable = ['meeting_id', 'student_id', 'attended'];

    protected $casts = [
        'attended' => 'boolean',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id', 'meeting_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}