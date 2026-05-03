<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointFeedback extends Model
{
    protected $table = 'Point_Feedbacks';
    public $timestamps = false;
    protected $primaryKey = 'feedback_id';

    protected $fillable = [
        'student_id',
        'semester_id',
        'feedback_content',
        'attachment_path',
        'status',
        'advisor_response',
        'advisor_id',
        'response_at'
    ];

    protected $casts = [
        'status' => 'string', // pending, approved, rejected
        'response_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Serialize datetime theo timezone hiện tại thay vì UTC
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'advisor_id');
    }
}