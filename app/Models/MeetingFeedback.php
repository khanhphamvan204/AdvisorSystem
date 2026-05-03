<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingFeedback extends Model
{
    protected $table = 'Meeting_Feedbacks';
    public $timestamps = false;
    protected $primaryKey = 'feedback_id';

    protected $fillable = ['meeting_id', 'student_id', 'feedback_content'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Serialize datetime theo timezone hiện tại thay vì UTC
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id', 'meeting_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}