<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    protected $table = 'Meetings';
    protected $primaryKey = 'meeting_id';
    public $timestamps = false;

    protected $fillable = [
        'advisor_id',
        'class_id',
        'title',
        'summary',
        'class_feedback',
        'meeting_link',
        'location',
        'meeting_time',
        'end_time',
        'status',
        'minutes_file_path'
    ];

    protected $casts = [
        'meeting_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    // Relationships
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'advisor_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id', 'class_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingStudent::class, 'meeting_id', 'meeting_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(MeetingFeedback::class, 'meeting_id', 'meeting_id');
    }
}