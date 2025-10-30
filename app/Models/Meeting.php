<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $primaryKey = 'meeting_id';
    protected $fillable = [
        'advisor_id',
        'class_id',
        'title',
        'summary',
        'meeting_link',
        'location',
        'meeting_time',
        'status',
        'minutes_file_path'
    ];

    protected $casts = [
        'meeting_time' => 'datetime',
    ];

    public function advisor()
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'user_id');
    }

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function attendees()
    {
        return $this->hasMany(MeetingStudent::class, 'meeting_id');
    }

    public function feedbacks()
    {
        return $this->hasMany(MeetingFeedback::class, 'meeting_id');
    }
}